<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT
        items.*,
        latest.location,
        latest.user_name,
        latest.status,
        latest.memo AS latest_memo,
        latest.moved_at
    FROM items
    LEFT JOIN (
        SELECT m1.*
        FROM movements m1
        INNER JOIN (
            SELECT item_id, MAX(moved_at) AS max_moved_at
            FROM movements
            GROUP BY item_id
        ) m2
        ON m1.item_id = m2.item_id AND m1.moved_at = m2.max_moved_at
    ) latest
    ON items.id = latest.item_id
    WHERE items.id = :id
");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    echo "指定された装置が見つかりません。";
    exit;
}

/*
 * 移動履歴
 * 古い順で取得してから、From / To を計算し、
 * 表示は新しい順にする。
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM movements
    WHERE item_id = :item_id
    ORDER BY moved_at ASC, id ASC
");
$stmt->execute([':item_id' => $id]);
$movements_oldest_first = $stmt->fetchAll(PDO::FETCH_ASSOC);

$movement_rows = [];
$previous_location = '';

foreach ($movements_oldest_first as $m) {
    $m['from_location'] = $previous_location;
    $m['to_location'] = $m['location'];
    $movement_rows[] = $m;
    $previous_location = $m['location'];
}

$movement_rows = array_reverse($movement_rows);

/*
 * 写真
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM item_photos
    WHERE item_id = :item_id
    ORDER BY uploaded_at DESC, id DESC
");
$stmt->execute([':item_id' => $id]);
$photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * アップロード済みマニュアル
 */
$stmt = $pdo->prepare("
    SELECT *
    FROM item_manuals
    WHERE item_id = :item_id
    ORDER BY uploaded_at DESC, id DESC
");
$stmt->execute([':item_id' => $id]);
$manuals = $stmt->fetchAll(PDO::FETCH_ASSOC);

$manual_url = trim($item['manual_url'] ?? '');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo h($item['asset_tag']); ?> - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1><?php echo h($item['asset_tag']); ?>：<?php echo h($item['name']); ?></h1>

<div class="menu">
    <a href="index.php">一覧へ戻る</a>
    <a href="move.php?id=<?php echo h($item['id']); ?>">移動登録</a>
    <a href="edit_item.php?id=<?php echo h($item['id']); ?>">情報編集</a>
    <a href="upload_photo.php?id=<?php echo h($item['id']); ?>">写真を登録</a>
    <a href="upload_manual.php?id=<?php echo h($item['id']); ?>">マニュアルを登録</a>

    <?php if (trim($item['default_location'] ?? '') !== ''): ?>
        <a href="return_default.php?id=<?php echo h($item['id']); ?>"
           onclick="return confirm('現在地をデフォルト置き場に戻した履歴を追加します。よろしいですか？');">
            デフォルト置き場に戻す
        </a>
    <?php endif; ?>
</div>

<div class="box current">
    <h2>現在の状態</h2>
    <table>
        <tr>
            <th>デフォルト置き場</th>
            <td><?php echo h($item['default_location']); ?></td>
        </tr>
        <tr>
            <th>現在地</th>
            <td><?php echo h($item['location']); ?></td>
        </tr>
        <tr>
            <th>使用者</th>
            <td><?php echo h($item['user_name']); ?></td>
        </tr>
        <tr>
            <th>状態</th>
            <td><?php echo h($item['status']); ?></td>
        </tr>
        <tr>
            <th>最終更新</th>
            <td><?php echo h($item['moved_at']); ?></td>
        </tr>
        <tr>
            <th>最新メモ</th>
            <td><?php echo nl2br(h($item['latest_memo'])); ?></td>
        </tr>
    </table>
</div>

<div class="box">
    <h2>基本情報</h2>
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
            <th>カテゴリ</th>
            <td><?php echo h($item['category']); ?></td>
        </tr>
        <tr>
            <th>メーカー</th>
            <td><?php echo h($item['manufacturer']); ?></td>
        </tr>
        <tr>
            <th>備品番号</th>
            <td><?php echo h($item['property_number']); ?></td>
        </tr>
        <tr>
            <th>購入年月</th>
            <td><?php echo h($item['purchase_date']); ?></td>
        </tr>
        <tr>
            <th>マニュアルURL</th>
            <td>
                <?php if ($manual_url !== ''): ?>
                    <a href="<?php echo h($manual_url); ?>" target="_blank" rel="noopener noreferrer">
                        <?php echo h($manual_url); ?>
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <th>シリアル番号</th>
            <td><?php echo h($item['serial_number']); ?></td>
        </tr>
        <tr>
            <th>メモ</th>
            <td><?php echo nl2br(h($item['note'])); ?></td>
        </tr>
        <tr>
            <th>登録日時</th>
            <td><?php echo h($item['created_at']); ?></td>
        </tr>
        <tr>
            <th>情報更新日時</th>
            <td><?php echo h($item['updated_at']); ?></td>
        </tr>
    </table>
</div>

<div class="box">
    <h2>マニュアル</h2>

    <?php if ($manual_url !== ''): ?>
        <p>
            <strong>URLマニュアル：</strong>
            <a href="<?php echo h($manual_url); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo h($manual_url); ?>
            </a>
        </p>
    <?php endif; ?>

    <?php if (count($manuals) === 0): ?>
        <?php if ($manual_url === ''): ?>
            <p>マニュアルは登録されていません。</p>
        <?php endif; ?>
    <?php else: ?>
        <table>
            <tr>
                <th>表示名</th>
                <th>元ファイル名</th>
                <th>サイズ</th>
                <th>登録日時</th>
                <th>操作</th>
            </tr>
            <?php foreach ($manuals as $manual): ?>
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
                    <td>
                        <a href="manual.php?id=<?php echo h($manual['id']); ?>" target="_blank">
                            <?php echo h($manual['original_name']); ?>
                        </a>
                    </td>
                    <td><?php echo h($size_mb); ?></td>
                    <td><?php echo h($manual['uploaded_at']); ?></td>
                    <td>
                        <a href="manual.php?id=<?php echo h($manual['id']); ?>" target="_blank">
                            表示
                        </a>
                        |
                        <a href="delete_manual.php?id=<?php echo h($manual['id']); ?>"
                           onclick="return confirm('このマニュアルをこの装置から外します。よろしいですか？他の装置でも使われている場合、実ファイルは削除されません。');">
                            削除
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

<div class="box">
    <h2>写真</h2>

    <?php if (count($photos) === 0): ?>
        <p>写真は登録されていません。</p>
    <?php else: ?>
        <div class="photo-list">
            <?php foreach ($photos as $photo): ?>
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
                        <a href="delete_photo.php?id=<?php echo h($photo['id']); ?>"
                           onclick="return confirm('この写真を削除します。よろしいですか？');">
                            削除
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="box">
    <h2>移動履歴</h2>

    <?php if (count($movement_rows) === 0): ?>
        <p>移動履歴はありません。</p>
    <?php else: ?>
        <table class="history">
            <tr>
                <th>日時</th>
                <th>From</th>
                <th>To</th>
                <th>使用者</th>
                <th>状態</th>
                <th>メモ</th>
            </tr>
            <?php foreach ($movement_rows as $m): ?>
                <tr>
                    <td><?php echo h($m['moved_at']); ?></td>
                    <td><?php echo h($m['from_location'] !== '' ? $m['from_location'] : '-'); ?></td>
                    <td><?php echo h($m['to_location']); ?></td>
                    <td><?php echo h($m['user_name']); ?></td>
                    <td><?php echo h($m['status']); ?></td>
                    <td><?php echo nl2br(h($m['memo'])); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
</div>

</body>
</html>
