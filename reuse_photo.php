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
 * 写真を現在の装置に関連付ける処理
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $photo_id = isset($_POST['photo_id']) ? (int)$_POST['photo_id'] : 0;

    $stmt = $pdo->prepare("
        SELECT *
        FROM item_photos
        WHERE id = :id
    ");
    $stmt->execute([':id' => $photo_id]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$photo) {
        $errors[] = '指定された写真が見つかりません。';
    } else {
        /*
         * すでに同じ写真ファイルがこの装置に関連付けられている場合は、
         * 重複登録しない。
         */
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM item_photos
            WHERE item_id = :item_id
              AND stored_name = :stored_name
        ");
        $stmt->execute([
            ':item_id' => $item_id,
            ':stored_name' => $photo['stored_name'],
        ]);
        $exists = (int)$stmt->fetchColumn();

        if ($exists > 0) {
            $errors[] = 'この写真はすでにこの装置に登録されています。';
        }
    }

    if (count($errors) === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO item_photos
                (item_id, original_name, stored_name, mime_type, file_size, caption, uploaded_at)
            VALUES
                (:item_id, :original_name, :stored_name, :mime_type, :file_size, :caption, :uploaded_at)
        ");

        $caption = $photo['caption'];
        if (trim((string)$caption) === '') {
            $caption = '既存写真を利用';
        }

        $stmt->execute([
            ':item_id' => $item_id,
            ':original_name' => $photo['original_name'],
            ':stored_name' => $photo['stored_name'],
            ':mime_type' => $photo['mime_type'],
            ':file_size' => $photo['file_size'],
            ':caption' => $caption,
            ':uploaded_at' => date('Y-m-d H:i:s'),
        ]);

        header('Location: item.php?id=' . urlencode($item_id));
        exit;
    }
}

/*
 * 写真が登録されている装置一覧
 * 現在の装置自身は除外する。
 */
$stmt = $pdo->prepare("
    SELECT
        items.id,
        items.asset_tag,
        items.name,
        items.model,
        items.manufacturer,
        COUNT(item_photos.id) AS photo_count
    FROM items
    INNER JOIN item_photos
        ON items.id = item_photos.item_id
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
$source_photos = [];

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
            FROM item_photos
            WHERE item_id = :item_id
            ORDER BY uploaded_at DESC, id DESC
        ");
        $stmt->execute([':item_id' => $source_item_id]);
        $source_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>既存写真を利用 - <?php echo h($item['asset_tag']); ?> - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>既存写真を利用</h1>

<div class="menu">
    <a href="item.php?id=<?php echo h($item['id']); ?>">詳細へ戻る</a>
    <a href="index.php">一覧へ戻る</a>
</div>

<div class="box">
    <h2>写真を追加する装置</h2>
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
    <h2>写真が登録されている装置を選ぶ</h2>

    <?php if (count($source_items) === 0): ?>
        <p>写真が登録されている他の装置はありません。</p>
    <?php else: ?>
        <table>
            <tr>
                <th>管理番号</th>
                <th>型番</th>
                <th>名称</th>
                <th>メーカー</th>
                <th>写真数</th>
                <th>操作</th>
            </tr>
            <?php foreach ($source_items as $src): ?>
                <tr>
                    <td><?php echo h($src['asset_tag']); ?></td>
                    <td><?php echo h($src['model']); ?></td>
                    <td><?php echo h($src['name']); ?></td>
                    <td><?php echo h($src['manufacturer']); ?></td>
                    <td><?php echo h($src['photo_count']); ?></td>
                    <td>
                        <a href="reuse_photo.php?id=<?php echo h($item_id); ?>&source_item_id=<?php echo h($src['id']); ?>">
                            この装置の写真を見る
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
            の写真
        </h2>

        <?php if (count($source_photos) === 0): ?>
            <p>この装置には写真がありません。</p>
        <?php else: ?>
            <div class="photo-list">
                <?php foreach ($source_photos as $photo): ?>
                    <div class="photo-card">
                        <a href="photo.php?id=<?php echo h($photo['id']); ?>" target="_blank">
                            <img src="photo.php?id=<?php echo h($photo['id']); ?>" alt="<?php echo h($photo['caption']); ?>">
                        </a>

                        <div class="photo-caption">
                            <?php echo h($photo['caption']); ?>
                        </div>

                        <div class="photo-meta">
                            <?php echo h($photo['original_name']); ?><br>
                            <?php echo h($photo['uploaded_at']); ?>
                        </div>

                        <div class="photo-actions">
                            <form method="post" action="reuse_photo.php?id=<?php echo h($item_id); ?>&source_item_id=<?php echo h($source_item_id); ?>">
                                <input type="hidden" name="photo_id" value="<?php echo h($photo['id']); ?>">
                                <button type="submit"
                                        onclick="return confirm('この写真を現在の装置にも登録します。実ファイルはコピーされません。よろしいですか？');">
                                    この写真を利用
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
