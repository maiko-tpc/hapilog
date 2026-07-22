<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');

    if (!isset($_FILES['manual']) || $_FILES['manual']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'マニュアルPDFを選択してください。';
    } else {
        $file = $_FILES['manual'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'アップロードに失敗しました。エラーコード: ' . $file['error'];
        } else {
            $max_size = (int)$config['manual_max_size'];

            if ($file['size'] > $max_size) {
                $errors[] = 'マニュアルPDFは' . format_file_size_limit($max_size) . '以下にしてください。';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);

            if ($mime_type !== 'application/pdf') {
                $errors[] = 'PDFファイルだけ登録できます。';
            }
        }
    }

    if (count($errors) === 0) {
        $manual_dir = $config['manual_directory'];

        if (!is_dir($manual_dir)) {
            if (!mkdir($manual_dir, 0700, true)) {
                $errors[] = 'マニュアル保存用ディレクトリを作成できません。';
            }
        }

        if (count($errors) === 0) {
            $random = bin2hex(random_bytes(8));
            $stored_name = 'manual_' . $item_id . '_' . date('Ymd_His') . '_' . $random . '.pdf';
            $dest = $manual_dir . '/' . $stored_name;

            if (!move_uploaded_file($file['tmp_name'], $dest)) {
                $errors[] = 'マニュアルファイルを保存できませんでした。';
            } else {
                chmod($dest, 0600);

                $stmt = $pdo->prepare("
                    INSERT INTO item_manuals
                        (item_id, original_name, stored_name, mime_type, file_size, title, uploaded_at)
                    VALUES
                        (:item_id, :original_name, :stored_name, :mime_type, :file_size, :title, :uploaded_at)
                ");

                $stmt->execute([
                    ':item_id' => $item_id,
                    ':original_name' => $file['name'],
                    ':stored_name' => $stored_name,
                    ':mime_type' => $mime_type,
                    ':file_size' => $file['size'],
                    ':title' => $title,
                    ':uploaded_at' => date('Y-m-d H:i:s'),
                ]);

                header('Location: item.php?id=' . urlencode($item_id));
                exit;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>マニュアルを登録 - <?php echo h($item['asset_tag']); ?> - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>マニュアルを登録</h1>

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

<div class="box">
    <h2>マニュアルの登録方法</h2>

    <p>
        新しいPDFマニュアルをアップロードするか、すでに他の装置に登録されているマニュアルを利用できます。
    </p>

    <div class="menu">
        <a href="reuse_manual.php?id=<?php echo h($item['id']); ?>">既存装置のマニュアルを利用</a>
    </div>
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
    <h2>新しいPDFマニュアルをアップロード</h2>

    <form method="post" enctype="multipart/form-data">
        <table>
            <tr>
                <th>PDFファイル *</th>
                <td>
                    <input type="file" name="manual" accept="application/pdf,.pdf" required>
                    <div class="note">
                        PDFのみ登録できます。最大<?php echo h(format_file_size_limit($config['manual_max_size'])); ?>です。
                    </div>
                </td>
            </tr>
            <tr>
                <th>表示名</th>
                <td>
                    <input type="text" name="title" value="<?php echo h($_POST['title'] ?? ''); ?>">
                    <div class="note">
                        空欄の場合は元ファイル名を使います。例：CAEN N1470 User Manual
                    </div>
                </td>
            </tr>
        </table>

        <p>
            <button type="submit">アップロードして登録</button>
        </p>
    </form>
</div>

</body>
</html>
