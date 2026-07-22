<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$manual_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM item_manuals
    WHERE id = :id
");
$stmt->execute([':id' => $manual_id]);
$manual = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$manual) {
    http_response_code(404);
    echo "指定されたマニュアルが見つかりません。";
    exit;
}

$item_id = (int)$manual['item_id'];
$stored_name = $manual['stored_name'];

$manual_dir = $config['manual_directory'];
$file = $manual_dir . '/' . $stored_name;

try {
    $pdo->beginTransaction();

    /*
     * まず、この装置との関連付けだけを削除する。
     */
    $stmt = $pdo->prepare("
        DELETE FROM item_manuals
        WHERE id = :id
    ");
    $stmt->execute([':id' => $manual_id]);

    /*
     * 同じ stored_name を参照しているマニュアルレコードが
     * 他に残っているか確認する。
     */
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM item_manuals
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
    echo "マニュアルの削除に失敗しました。<br>";
    echo h($e->getMessage());
    echo "<br>";
    echo '<a href="item.php?id=' . h($item_id) . '">詳細へ戻る</a>';
    exit;
}
