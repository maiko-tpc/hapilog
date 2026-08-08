<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$statuses = ['使用可', '使用中', '貸出中', '修理中', '故障', '廃棄予定', '不明'];

/*
 * 戻り先URL。
 *
 * 外部サイトへ飛ばないように、index.php で始まるものだけ許可する。
 */
$redirect_to = trim($_POST['redirect_to'] ?? 'index.php');

if (
    $redirect_to === ''
    || strpos($redirect_to, 'index.php') !== 0
    || preg_match('/^https?:\/\//i', $redirect_to)
    || strpos($redirect_to, '//') === 0
) {
    $redirect_to = 'index.php';
}

/*
 * 選択された装置IDを整理する。
 */
$raw_ids = $_POST['item_ids'] ?? [];

if (!is_array($raw_ids)) {
    $raw_ids = [];
}

$item_ids = [];

foreach ($raw_ids as $raw_id) {
    $id = (int)$raw_id;

    if ($id > 0) {
        $item_ids[] = $id;
    }
}

$item_ids = array_values(array_unique($item_ids));

$requested_count = count($item_ids);

/*
 * 選択なしの場合は一覧へ戻る。
 */
if ($requested_count === 0) {
    $query_separator = strpos($redirect_to, '?') === false ? '?' : '&';

    header(
        'Location: '
        . $redirect_to
        . $query_separator
        . http_build_query([
            'bulk_move' => 'done',
            'bulk_move_requested' => 0,
            'bulk_move_moved' => 0,
            'bulk_move_skipped' => 0,
        ])
    );
    exit;
}

/*
 * 移動先候補。
 * 過去の移動先とデフォルト置き場から候補を作る。
 */
$stmt = $pdo->query("
    SELECT DISTINCT place
    FROM (
        SELECT location AS place
        FROM movements
        WHERE TRIM(COALESCE(location, '')) <> ''

        UNION

        SELECT default_location AS place
        FROM items
        WHERE TRIM(COALESCE(default_location, '')) <> ''
    )
    ORDER BY place ASC
");
$location_candidates = $stmt->fetchAll(PDO::FETCH_COLUMN);

/*
 * 選択された装置を取得する。
 *
 * 最新の移動履歴も同時に見る。
 */
$placeholders = [];

foreach ($item_ids as $index => $id) {
    $placeholders[] = ':id' . $index;
}

$sql = "
    SELECT
        items.id,
        items.asset_tag,
        items.model,
        items.name,
        items.manufacturer,
        items.default_location,
        latest.location AS current_location,
        latest.user_name AS current_user_name,
        latest.status AS current_status,
        latest.moved_at AS current_moved_at
    FROM items
    LEFT JOIN movements latest
        ON latest.id = (
            SELECT m2.id
            FROM movements m2
            WHERE m2.item_id = items.id
            ORDER BY m2.moved_at DESC, m2.id DESC
            LIMIT 1
        )
    WHERE items.id IN (" . implode(', ', $placeholders) . ")
    ORDER BY items.asset_tag ASC
";

$stmt = $pdo->prepare($sql);

foreach ($item_ids as $index => $id) {
    $stmt->bindValue(':id' . $index, $id, PDO::PARAM_INT);
}

$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$found_count = count($items);
$missing_count = $requested_count - $found_count;

$errors = [];

/*
 * フォーム初期値
 */
$default_location = trim($_POST['location'] ?? '');
$default_user_name = trim($_POST['user_name'] ?? '');
$default_status = trim($_POST['status'] ?? '使用可');
$default_memo = trim($_POST['memo'] ?? '一括移動登録');

/*
 * 一括移動登録の実行
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['do_move'])) {
    $location = trim($_POST['location'] ?? '');
    $user_name = trim($_POST['user_name'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $memo = trim($_POST['memo'] ?? '');

    if ($location === '') {
        $errors[] = '移動先を入力してください。';
    }

    if ($status === '') {
        $errors[] = '状態を選択してください。';
    } elseif (!in_array($status, $statuses, true)) {
        $errors[] = '状態の値が不正です。';
    }

    if ($found_count === 0) {
        $errors[] = '移動登録できる装置がありません。';
    }

    if (count($errors) === 0) {
        $moved_count = 0;
        $skipped_count = $missing_count;

        try {
            $pdo->beginTransaction();

            $now = date('Y-m-d H:i:s');

            $insert_stmt = $pdo->prepare("
                INSERT INTO movements
                    (
                        item_id,
                        location,
                        user_name,
                        status,
                        memo,
                        moved_at
                    )
                VALUES
                    (
                        :item_id,
                        :location,
                        :user_name,
                        :status,
                        :memo,
                        :moved_at
                    )
            ");

            foreach ($items as $item) {
                $insert_stmt->execute([
                    ':item_id' => (int)$item['id'],
                    ':location' => $location,
                    ':user_name' => $user_name,
                    ':status' => $status,
                    ':memo' => $memo,
                    ':moved_at' => $now,
                ]);

                $moved_count++;
            }

            $pdo->commit();

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $errors[] = '一括移動登録に失敗しました: ' . $e->getMessage();
        }

        if (count($errors) === 0) {
            $query_separator = strpos($redirect_to, '?') === false ? '?' : '&';

            header(
                'Location: '
                . $redirect_to
                . $query_separator
                . http_build_query([
                    'bulk_move' => 'done',
                    'bulk_move_requested' => $requested_count,
                    'bulk_move_moved' => $moved_count,
                    'bulk_move_skipped' => $skipped_count,
                ])
            );
            exit;
        }
    }

    /*
     * エラー時にPOST内容を再表示する。
     */
    $default_location = $location;
    $default_user_name = $user_name;
    $default_status = $status;
    $default_memo = $memo;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>一括移動登録 - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>一括移動登録</h1>

<div class="menu">
    <a href="<?php echo h($redirect_to); ?>">一覧へ戻る</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo h($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="box">
    <h2>対象装置</h2>

    <p>
        選択
        <strong><?php echo h($requested_count); ?></strong>
        件 ／ 登録対象
        <strong><?php echo h($found_count); ?></strong>
        件
        <?php if ($missing_count > 0): ?>
            ／ 見つからない装置
            <strong><?php echo h($missing_count); ?></strong>
            件
        <?php endif; ?>
    </p>

    <?php if ($found_count === 0): ?>
        <div class="empty">
            移動登録できる装置がありません。
        </div>
    <?php else: ?>
        <table>
            <tr>
                <th>管理番号</th>
                <th>型番</th>
                <th>名称</th>
                <th>メーカー</th>
                <th>デフォルト置き場</th>
                <th>現在地</th>
                <th>使用者</th>
                <th>状態</th>
            </tr>

            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?php echo h($item['asset_tag']); ?></td>
                    <td><?php echo h($item['model']); ?></td>
                    <td><?php echo h($item['name']); ?></td>
                    <td><?php echo h($item['manufacturer']); ?></td>
                    <td><?php echo h($item['default_location']); ?></td>
                    <td><?php echo h($item['current_location']); ?></td>
                    <td><?php echo h($item['current_user_name']); ?></td>
                    <td><?php echo h($item['current_status']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php if ($found_count > 0): ?>
    <form method="post" action="bulk_move.php">
        <input
            type="hidden"
            name="redirect_to"
            value="<?php echo h($redirect_to); ?>"
        >

        <input
            type="hidden"
            name="do_move"
            value="1"
        >

        <?php foreach ($item_ids as $item_id): ?>
            <input
                type="hidden"
                name="item_ids[]"
                value="<?php echo h($item_id); ?>"
            >
        <?php endforeach; ?>

        <div class="box">
            <h2>移動内容</h2>

            <table>
                <tr>
                    <th>To *</th>
                    <td>
                        <input
                            type="text"
                            name="location"
                            list="location_candidates"
                            value="<?php echo h($default_location); ?>"
                            required
                        >

                        <datalist id="location_candidates">
                            <?php foreach ($location_candidates as $place): ?>
                                <option value="<?php echo h($place); ?>">
                            <?php endforeach; ?>
                        </datalist>

                        <div class="note">
                            選択したすべての装置に、同じ移動先を登録します。
                            候補にない場所も直接入力できます。
                        </div>
                    </td>
                </tr>

                <tr>
                    <th>使用者</th>
                    <td>
                        <input
                            type="text"
                            name="user_name"
                            value="<?php echo h($default_user_name); ?>"
                        >

                        <div class="note">
                            空欄のまま登録することもできます。
                        </div>
                    </td>
                </tr>

                <tr>
                    <th>状態</th>
                    <td>
                        <select name="status">
                            <?php foreach ($statuses as $s): ?>
                                <option value="<?php echo h($s); ?>"
                                    <?php if ($default_status === $s) echo 'selected'; ?>>
                                    <?php echo h($s); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>

                <tr>
                    <th>メモ</th>
                    <td>
                        <textarea name="memo" rows="3"><?php echo h($default_memo); ?></textarea>

                        <div class="note">
                            選択したすべての装置の移動履歴に、同じメモが登録されます。
                        </div>
                    </td>
                </tr>
            </table>
        </div>

        <p>
            <button
                type="submit"
                onclick="return confirm('選択した装置すべてに同じ移動履歴を登録します。よろしいですか？');"
            >
                一括移動登録
            </button>

            <a href="<?php echo h($redirect_to); ?>">キャンセル</a>
        </p>
    </form>
<?php endif; ?>

</body>
</html>
