<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM items WHERE id = :id");
$stmt->execute([':id' => $id]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    http_response_code(404);
    echo "指定された装置が見つかりません。";
    exit;
}

$errors = [];

$asset_tag = $item['asset_tag'];
$model = $item['model'];
$name = $item['name'];
$category = $item['category'];
$manufacturer = $item['manufacturer'];
$property_number = $item['property_number'];
$default_location = $item['default_location'];
$purchase_date = $item['purchase_date'];
$manual_url = $item['manual_url'];
$serial_number = $item['serial_number'];
$note = $item['note'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_tag = trim($_POST['asset_tag'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $property_number = trim($_POST['property_number'] ?? '');
    $default_location = trim($_POST['default_location'] ?? '');
    $purchase_date = trim($_POST['purchase_date'] ?? '');
    $manual_url = trim($_POST['manual_url'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $note = trim($_POST['note'] ?? '');

    if ($asset_tag === '') {
        $errors[] = '管理番号を入力してください。';
    }
    if ($name === '') {
        $errors[] = '名称を入力してください。';
    }

    if ($manual_url !== '' && !preg_match('/^https?:\/\//', $manual_url)) {
        $errors[] = 'マニュアルURLは http:// または https:// で始まるURLにしてください。';
    }

    if (count($errors) === 0) {
        try {
            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                UPDATE items
                SET
                    asset_tag = :asset_tag,
                    model = :model,
                    name = :name,
                    category = :category,
                    manufacturer = :manufacturer,
                    property_number = :property_number,
                    default_location = :default_location,
                    purchase_date = :purchase_date,
                    manual_url = :manual_url,
                    serial_number = :serial_number,
                    note = :note,
                    updated_at = :updated_at
                WHERE id = :id
            ");
            $stmt->execute([
                ':asset_tag' => $asset_tag,
                ':model' => $model,
                ':name' => $name,
                ':category' => $category,
                ':manufacturer' => $manufacturer,
                ':property_number' => $property_number,
                ':default_location' => $default_location,
                ':purchase_date' => $purchase_date,
                ':manual_url' => $manual_url,
                ':serial_number' => $serial_number,
                ':note' => $note,
                ':updated_at' => $now,
                ':id' => $id,
            ]);

            header('Location: item.php?id=' . urlencode($id));
            exit;
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $errors[] = 'この管理番号はすでに使われています。';
            } else {
                $errors[] = '更新に失敗しました: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>装置情報編集 - <?php echo h($item['asset_tag']); ?> - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>装置情報編集</h1>

<div class="menu">
    <a href="item.php?id=<?php echo h($id); ?>">詳細へ戻る</a>
    <a href="index.php">一覧へ戻る</a>
</div>

<?php if (count($errors) > 0): ?>
    <div class="errors">
        <ul>
            <?php foreach ($errors as $error): ?>
                <li><?php echo h($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form method="post" action="edit_item.php?id=<?php echo h($id); ?>">
    <div class="row">
        <label>管理番号 *</label>
        <input type="text" name="asset_tag" value="<?php echo h($asset_tag); ?>">
        <div class="hint">原則として、一度貼った管理番号は変更しない運用がおすすめです。</div>
    </div>

    <div class="row">
        <label>型番</label>
        <input type="text" name="model" value="<?php echo h($model); ?>">
    </div>

    <div class="row">
        <label>名称 *</label>
        <input type="text" name="name" value="<?php echo h($name); ?>">
    </div>

    <div class="row">
        <label>カテゴリ</label>
        <input type="text" name="category" value="<?php echo h($category); ?>">
    </div>

    <div class="row">
        <label>メーカー</label>
        <input type="text" name="manufacturer" value="<?php echo h($manufacturer); ?>">
    </div>

    <div class="row">
        <label>備品番号</label>
        <input type="text" name="property_number" value="<?php echo h($property_number); ?>">
    </div>

    <div class="row">
        <label>デフォルト置き場</label>
        <input type="text" name="default_location" value="<?php echo h($default_location); ?>">
        <div class="hint">通常戻すべき場所を入れます。現在地とは別に管理します。</div>
    </div>

    <div class="row">
        <label>購入年月</label>
        <input type="text" name="purchase_date" value="<?php echo h($purchase_date); ?>" placeholder="例: 2024-04, 2024年4月, 不明">
    </div>

    <div class="row">
        <label>マニュアルURL</label>
        <input type="text" name="manual_url" value="<?php echo h($manual_url); ?>" placeholder="例: https://example.com/manual.pdf">
        <div class="hint">メーカーPDF、研究室Wiki、仕様書ページなどのURLを入れます。</div>
    </div>

    <div class="row">
        <label>シリアル番号</label>
        <input type="text" name="serial_number" value="<?php echo h($serial_number); ?>">
    </div>

    <div class="row">
        <label>メモ</label>
        <textarea name="note"><?php echo h($note); ?></textarea>
    </div>

    <button type="submit">更新</button>
</form>

</body>
</html>
