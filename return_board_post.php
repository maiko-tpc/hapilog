<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$post_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("
    SELECT *
    FROM board_posts
    WHERE id = :id
");
$stmt->execute([':id' => $post_id]);
$post = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$post) {
    http_response_code(404);
    echo "指定された投稿が見つかりません。";
    exit;
}

try {
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        UPDATE board_posts
        SET returned_at = :returned_at
        WHERE id = :id
    ");
    $stmt->execute([
        ':returned_at' => $now,
        ':id' => $post_id,
    ]);

    header('Location: board.php');
    exit;
} catch (Exception $e) {
    http_response_code(500);
    echo "返却完了の登録に失敗しました。<br>";
    echo htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo "<br>";
    echo '<a href="board.php">掲示板へ戻る</a>';
    exit;
}
