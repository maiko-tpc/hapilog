<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$statuses = ['使用可', '使用中', '貸出中', '修理中', '故障', '廃棄予定', '不明'];

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM items
    WHERE id = :id
");
$stmt->execute([':id' => $item_id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    echo "指定された装置が見つかりません。";
    exit;
}

/*
 * 最新の移動履歴
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM movements
    WHERE item_id = :item_id
    ORDER BY moved_at DESC, id DESC
    LIMIT 1
");
$stmt->execute([':item_id' => $item_id]);
$latest = $stmt->fetch(PDO::FETCH_ASSOC);

$current_location = $latest['location'] ?? '';

/*
 * 移動先候補
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

$errors = [];

/*
 * フォーム初期値
 */
$default_location = $_POST['location'] ?? '';
$default_user_name = $_POST['user_name'] ?? ($latest['user_name'] ?? '');
$default_status = $_POST['status'] ?? ($latest['status'] ?? '使用可');
$default_memo = $_POST['memo'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    if (count($errors) === 0) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO movements
                    (item_id, location, user_name, status, memo, moved_at)
                VALUES
                    (:item_id, :location, :user_name, :status, :memo, :moved_at)
            ");

            $stmt->execute([
                ':item_id' => $item_id,
                ':location' => $location,
                ':user_name' => $user_name,
                ':status' => $status,
                ':memo' => $memo,
                ':moved_at' => date('Y-m-d H:i:s'),
            ]);

            header('Location: item.php?id=' . urlencode($item_id));
            exit;
        } catch (Exception $e) {
            $errors[] = '移動登録に失敗しました: ' . $e->getMessage();
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
    <title>移動登録 - <?php echo h($item['asset_tag']); ?> - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>移動登録</h1>

<div class="menu">
    <a href="item.php?id=<?php echo h($item['id']); ?>">詳細へ戻る</a>
    <a href="index.php">一覧へ戻る</a>
</div>

<div class="box">
    <h2>対象装置</h2>

    <table>
        <tr>
            <th>管理番号</th>
            <td><?php echo h($item['asset_tag']); ?></td>
        </tr>
        <tr>
            <th>型番</th>
            <td><?php echo h($item['model']); ?></td>
        </tr>
        <tr>
            <th>名称</th>
            <td><?php echo h($item['name']); ?></td>
        </tr>
        <tr>
            <th>メーカー</th>
            <td><?php echo h($item['manufacturer']); ?></td>
        </tr>
        <tr>
            <th>デフォルト置き場</th>
            <td><?php echo h($item['default_location']); ?></td>
        </tr>
    </table>
</div>

<div class="box current">
    <h2>現在の状態</h2>

    <table>
        <tr>
            <th>現在地</th>
            <td><?php echo h($current_location); ?></td>
        </tr>
        <tr>
            <th>使用者</th>
            <td><?php echo h($latest['user_name'] ?? ''); ?></td>
        </tr>
        <tr>
            <th>状態</th>
            <td><?php echo h($latest['status'] ?? ''); ?></td>
        </tr>
        <tr>
            <th>最終更新</th>
            <td><?php echo h($latest['moved_at'] ?? ''); ?></td>
        </tr>
        <tr>
            <th>最新メモ</th>
            <td><?php echo nl2br(h($latest['memo'] ?? '')); ?></td>
        </tr>
    </table>
</div>

<?php if (count($errors) > 0): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?php echo h($e); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post">
    <div class="box">
        <h2>移動内容</h2>

        <table>
            <tr>
                <th>From</th>
                <td>
                    <?php echo h($current_location !== '' ? $current_location : '-'); ?>
                    <div class="note">
                        現在登録されている場所です。
                    </div>
                </td>
            </tr>
            <tr>
                <th>To *</th>
                <td>
                    <input type="text"
                           name="location"
                           list="location_candidates"
                           value="<?php echo h($default_location); ?>"
                           required>

                    <datalist id="location_candidates">
                        <?php foreach ($location_candidates as $place): ?>
                            <option value="<?php echo h($place); ?>">
                        <?php endforeach; ?>
                    </datalist>

                    <div class="note">
                        移動先を入力します。過去に使った場所やデフォルト置き場が候補として表示されます。
                        候補にない場所も直接入力できます。
                    </div>
                </td>
            </tr>
            <tr>
                <th>使用者</th>
                <td>
                    <input type="text" name="user_name" value="<?php echo h($default_user_name); ?>">
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
                </td>
            </tr>
        </table>
    </div>

    <p>
        <button type="submit">移動登録</button>
    </p>
</form>

</body>
</html>
