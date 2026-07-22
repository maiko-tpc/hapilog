<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM items
    WHERE id = :id
");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    echo "指定された装置が見つかりません。";
    exit;
}

$default_location = trim($item['default_location'] ?? '');

if ($default_location === '') {
    http_response_code(400);
    echo "この装置にはデフォルト置き場が設定されていません。";
    echo "<br>";
    echo '<a href="item.php?id=' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">詳細へ戻る</a>';
    exit;
}

try {
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        INSERT INTO movements
            (item_id, location, user_name, status, memo, moved_at)
        VALUES
            (:item_id, :location, :user_name, :status, :memo, :moved_at)
    ");

    $stmt->execute([
        ':item_id' => $id,
        ':location' => $default_location,
        ':user_name' => '',
        ':status' => '使用可',
        ':memo' => 'デフォルト置き場に戻した',
        ':moved_at' => $now,
    ]);

    header('Location: item.php?id=' . urlencode($id));
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "デフォルト置き場への移動登録に失敗しました。<br>";
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "<br>";
    echo '<a href="item.php?id=' . htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8') . '">詳細へ戻る</a>';
    exit;
}
