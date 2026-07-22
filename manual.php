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

$manual_dir = $config['manual_directory'];
$file = $manual_dir . '/' . $manual['stored_name'];

if (!is_file($file)) {
    http_response_code(404);
    echo "マニュアルファイルが見つかりません。";
    exit;
}

$mime_type = $manual['mime_type'] ?: 'application/pdf';
$original_name = $manual['original_name'] ?: 'manual.pdf';

/*
 * 念のため、改行や特殊文字を除去してヘッダ用ファイル名を安全化する。
 */
$safe_name = str_replace(["\r", "\n", '"'], '', $original_name);

header('Content-Type: ' . $mime_type);
header('Content-Length: ' . filesize($file));
header('Content-Disposition: inline; filename="' . $safe_name . '"');
header('X-Content-Type-Options: nosniff');

readfile($file);
exit;
