<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$photo_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM item_photos
    WHERE id = :id
");
$stmt->execute([':id' => $photo_id]);
$photo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$photo) {
    http_response_code(404);
    echo "指定された写真が見つかりません。";
    exit;
}

$item_id = (int)$photo['item_id'];
$stored_name = $photo['stored_name'];

$photo_dir = $config['photo_directory'];
$file = $photo_dir . '/' . $stored_name;

try {
    $pdo->beginTransaction();

    /*
     * まず、この写真レコードだけを削除する。
     * 同じ stored_name を使っている他の装置があっても、
     * その関連付けは残す。
     */
    $stmt = $pdo->prepare("
        DELETE FROM item_photos
        WHERE id = :id
    ");
    $stmt->execute([':id' => $photo_id]);

    /*
     * 同じ stored_name を参照している写真レコードが
     * 他に残っているか確認する。
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM item_photos
        WHERE stored_name = :stored_name
    ");
    $stmt->execute([
        ':stored_name' => $stored_name,
    ]);
    $remaining_count = (int)$stmt->fetchColumn();

    /*
     * もう誰も参照していない場合だけ、実ファイルを削除する。
     */
    if ($remaining_count === 0 && is_file($file)) {
        unlink($file);
    }

    $pdo->commit();

    header('Location: item.php?id=' . urlencode($item_id));
    exit;
} catch (Exception $e) {
    $pdo->rollBack();

    http_response_code(500);
    echo "写真の削除に失敗しました。<br>";
    echo h($e->getMessage());
    echo "<br>";
    echo '<a href="item.php?id=' . h($item_id) . '">詳細へ戻る</a>';
    exit;
}
