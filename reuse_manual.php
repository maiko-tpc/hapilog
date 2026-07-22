<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$source_item_id = isset($_GET['source_item_id']) ? (int)$_GET['source_item_id'] : 0;

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

$errors = [];

/*
 * マニュアルを現在の装置に関連付ける処理
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $manual_id = isset($_POST['manual_id']) ? (int)$_POST['manual_id'] : 0;

    $stmt = $pdo->prepare("
        SELECT *
        FROM item_manuals
        WHERE id = :id
    ");
    $stmt->execute([':id' => $manual_id]);
    $manual = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$manual) {
        $errors[] = '指定されたマニュアルが見つかりません。';
    } else {
        /*
         * すでに同じマニュアルファイルがこの装置に関連付けられている場合は、
         * 重複登録しない。
         */
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM item_manuals
            WHERE item_id = :item_id
              AND stored_name = :stored_name
        ");
        $stmt->execute([
            ':item_id' => $item_id,
            ':stored_name' => $manual['stored_name'],
        ]);
        $exists = (int)$stmt->fetchColumn();

        if ($exists > 0) {
            $errors[] = 'このマニュアルはすでにこの装置に登録されています。';
        }
    }

    if (count($errors) === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO item_manuals
                (item_id, original_name, stored_name, mime_type, file_size, title, uploaded_at)
            VALUES
                (:item_id, :original_name, :stored_name, :mime_type, :file_size, :title, :uploaded_at)
        ");

        $stmt->execute([
            ':item_id' => $item_id,
            ':original_name' => $manual['original_name'],
            ':stored_name' => $manual['stored_name'],
            ':mime_type' => $manual['mime_type'],
            ':file_size' => $manual['file_size'],
            ':title' => $manual['title'],
            ':uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        header('Location: item.php?id=' . urlencode($item_id));
        exit;
    }
}

/*
 * マニュアルが登録されている装置一覧
 * 現在の装置自身は除外する。
 */
$stmt = $pdo->prepare("
    SELECT
        items.id,
        items.asset_tag,
        items.name,
        items.model,
        items.manufacturer,
        COUNT(item_manuals.id) AS manual_count
    FROM items
    INNER JOIN item_manuals
        ON items.id = item_manuals.item_id
    WHERE items.id <> :item_id
    GROUP BY
        items.id,
        items.asset_tag,
        items.name,
        items.model,
        items.manufacturer
    ORDER BY
        items.asset_tag ASC
");
$stmt->execute([':item_id' => $item_id]);
$source_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$source_item = null;
$source_manuals = [];

if ($source_item_id > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM items
        WHERE id = :id
    ");
    $stmt->execute([':id' => $source_item_id]);
    $source_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($source_item) {
        $stmt = $pdo->prepare("
            SELECT *
            FROM item_manuals
            WHERE item_id = :item_id
            ORDER BY uploaded_at DESC, id DESC
        ");
        $stmt->execute([':item_id' => $source_item_id]);
        $source_manuals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>既存マニュアルを利用 - <?php echo h($item['asset_tag']); ?> - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>既存マニュアルを利用</h1>

<div class="menu">
    <a href="item.php?id=<?php echo h($item['id']); ?>">詳細へ戻る</a>
    <a href="upload_manual.php?id=<?php echo h($item['id']); ?>">マニュアル登録へ戻る</a>
    <a href="index.php">一覧へ戻る</a>
</div>

<div class="box">
    <h2>マニュアルを追加する装置</h2>
    <table>
        <tr>
            <th>管理番号</th>
            <td><?php echo h($item['asset_tag']); ?></td>
        </tr>
        <tr>
            <th>名称</th>
            <td><?php echo h($item['name']); ?></td>
        </tr>
        <tr>
            <th>型番</th>
            <td><?php echo h($item['model']); ?></td>
        </tr>
        <tr>
            <th>メーカー</th>
            <td><?php echo h($item['manufacturer']); ?></td>
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

<div class="box">
    <h2>マニュアルが登録されている装置を選ぶ</h2>

    <?php if (count($source_items) === 0): ?>
        <p>マニュアルが登録されている他の装置はありません。</p>
    <?php else: ?>
        <table>
            <tr>
                <th>管理番号</th>
                <th>型番</th>
                <th>名称</th>
                <th>メーカー</th>
                <th>マニュアル数</th>
                <th>操作</th>
            </tr>
            <?php foreach ($source_items as $src): ?>
                <tr>
                    <td><?php echo h($src['asset_tag']); ?></td>
                    <td><?php echo h($src['model']); ?></td>
                    <td><?php echo h($src['name']); ?></td>
                    <td><?php echo h($src['manufacturer']); ?></td>
                    <td><?php echo h($src['manual_count']); ?></td>
                    <td>
                        <a href="reuse_manual.php?id=<?php echo h($item_id); ?>&source_item_id=<?php echo h($src['id']); ?>">
                            この装置のマニュアルを見る
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<?php if ($source_item): ?>
    <div class="box">
        <h2>
            <?php echo h($source_item['asset_tag']); ?>：
            <?php echo h($source_item['name']); ?>
            のマニュアル
        </h2>

        <?php if (count($source_manuals) === 0): ?>
            <p>この装置にはマニュアルがありません。</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>表示名</th>
                    <th>元ファイル名</th>
                    <th>サイズ</th>
                    <th>登録日時</th>
                    <th>操作</th>
                </tr>
                <?php foreach ($source_manuals as $manual): ?>
                    <?php
                    $manual_title = trim($manual['title'] ?? '');
                    if ($manual_title === '') {
                        $manual_title = $manual['original_name'];
                    }

                    $size_mb = '';
                    if ((int)$manual['file_size'] > 0) {
                        $size_mb = round((int)$manual['file_size'] / 1024 / 1024, 2) . ' MB';
                    }
                    ?>
                    <tr>
                        <td><?php echo h($manual_title); ?></td>
                        <td><?php echo h($manual['original_name']); ?></td>
                        <td><?php echo h($size_mb); ?></td>
                        <td><?php echo h($manual['uploaded_at']); ?></td>
                        <td>
                            <a href="manual.php?id=<?php echo h($manual['id']); ?>" target="_blank">
                                表示
                            </a>
                            |
                            <form method="post"
                                  action="reuse_manual.php?id=<?php echo h($item_id); ?>&source_item_id=<?php echo h($source_item_id); ?>"
                                  style="display:inline;">
                                <input type="hidden" name="manual_id" value="<?php echo h($manual['id']); ?>">
                                <button type="submit"
                                        onclick="return confirm('このマニュアルを現在の装置にも登録します。実ファイルはコピーされません。よろしいですか？');">
                                    このマニュアルを利用
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
