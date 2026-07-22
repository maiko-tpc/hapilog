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
    echo "Photo not found.";
    exit;
}

$photo_dir = $config['photo_directory'];
$file = $photo_dir . '/' . $photo['stored_name'];

if (!is_file($file)) {
    http_response_code(404);
    echo "Photo file not found.";
    exit;
}

$allowed = [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp',
];

$mime_type = $photo['mime_type'];

if (!in_array($mime_type, $allowed, true)) {
    http_response_code(403);
    echo "Invalid file type.";
    exit;
}

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file));
header('X-Content-Type-Options: nosniff');

readfile($file);
exit;
