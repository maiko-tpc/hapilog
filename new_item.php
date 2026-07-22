<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


$statuses = ['使用可', '使用中', '貸出中', '修理中', '故障', '廃棄予定', '不明'];

$errors = [];

/*
 * 既存装置から基本情報をコピーするための処理
 */
$copy_from_id = isset($_GET['copy_from_id']) ? (int)$_GET['copy_from_id'] : 0;
$copy_item = null;
$copy_photo_count = 0;
$copy_manual_count = 0;

if ($copy_from_id > 0) {
    $stmt = $pdo->prepare("
        SELECT *
        FROM items
        WHERE id = :id
    ");
    $stmt->execute([':id' => $copy_from_id]);
    $copy_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($copy_item) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM item_photos
            WHERE item_id = :item_id
        ");
        $stmt->execute([':item_id' => $copy_from_id]);
        $copy_photo_count = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM item_manuals
            WHERE item_id = :item_id
        ");
        $stmt->execute([':item_id' => $copy_from_id]);
        $copy_manual_count = (int)$stmt->fetchColumn();
    }
}

/*
 * コピー元候補一覧
 */
$stmt = $pdo->query("
    SELECT id, asset_tag, model, name, manufacturer
    FROM items
    ORDER BY asset_tag ASC
");
$copy_candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * 場所候補
 * 過去の移動先とデフォルト置き場から候補を作る。
 */
$stmt = $pdo->query("
    SELECT DISTINCT place
    FROM (
        SELECT location AS place
        FROM movements
        WHERE TRIM(COALESCE(location, '')) <> ''

        UNION

        SELECT default_location AS place
        FROM items
        WHERE TRIM(COALESCE(default_location, '')) <> ''
    )
    ORDER BY place ASC
");
$location_candidates = $stmt->fetchAll(PDO::FETCH_COLUMN);

/*
 * フォーム初期値
 */
$default_asset_tag = $_POST['asset_tag'] ?? '';
$default_model = $_POST['model'] ?? ($copy_item['model'] ?? '');
$default_name = $_POST['name'] ?? ($copy_item['name'] ?? '');
$default_category = $_POST['category'] ?? ($copy_item['category'] ?? '');
$default_manufacturer = $_POST['manufacturer'] ?? ($copy_item['manufacturer'] ?? '');
$default_property_number = $_POST['property_number'] ?? '';
$default_default_location = $_POST['default_location'] ?? '';
$default_purchase_date = $_POST['purchase_date'] ?? '';
$default_manual_url = $_POST['manual_url'] ?? ($copy_item['manual_url'] ?? '');
$default_serial_number = $_POST['serial_number'] ?? '';
$default_note = $_POST['note'] ?? '';

$default_location = $_POST['location'] ?? '';
$default_user_name = $_POST['user_name'] ?? '';
$default_status = $_POST['status'] ?? '使用可';
$default_move_memo = $_POST['move_memo'] ?? '';

$default_photo_caption = $_POST['photo_caption'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $default_copy_photos = isset($_POST['copy_photos']);
    $default_copy_manuals = isset($_POST['copy_manuals']);
} else {
    $default_copy_photos = ($copy_item && $copy_photo_count > 0);
    $default_copy_manuals = ($copy_item && $copy_manual_count > 0);
}

/*
 * 写真アップロード設定
 */
$uploaded_photo_info = null;
$allowed_mime_types = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/gif'  => 'gif',
    'image/webp' => 'webp',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $asset_tag = trim($_POST['asset_tag'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $manufacturer = trim($_POST['manufacturer'] ?? '');
    $property_number = trim($_POST['property_number'] ?? '');
    $default_location_value = trim($_POST['default_location'] ?? '');
    $purchase_date = trim($_POST['purchase_date'] ?? '');
    $manual_url = trim($_POST['manual_url'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $note = trim($_POST['note'] ?? '');

    $location = trim($_POST['location'] ?? '');
    $user_name = trim($_POST['user_name'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $move_memo = trim($_POST['move_memo'] ?? '');

    $copy_photos = isset($_POST['copy_photos']) && $copy_item && $copy_photo_count > 0;
    $copy_manuals = isset($_POST['copy_manuals']) && $copy_item && $copy_manual_count > 0;

    $photo_caption = trim($_POST['photo_caption'] ?? '');

    if ($asset_tag === '') {
        $errors[] = '管理番号を入力してください。';
    }

    if ($name === '') {
        $errors[] = '名称を入力してください。';
    }

    if ($location === '') {
        $errors[] = '初期所在地を入力してください。';
    }

    if ($status === '') {
        $errors[] = '状態を選択してください。';
    } elseif (!in_array($status, $statuses, true)) {
        $errors[] = '状態の値が不正です。';
    }

    if ($manual_url !== '' && !preg_match('/^https?:\/\//', $manual_url)) {
        $errors[] = 'マニュアルURLは http:// または https:// で始まるURLを入力してください。';
    }

    /*
     * 新しい写真が選択されている場合だけチェックする。
     * 写真なしでも新規登録はできる。
     */
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['photo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = '写真のアップロードに失敗しました。エラーコード: ' . $file['error'];
        } else {
            $max_size = (int)$config['photo_max_size'];

            if ($file['size'] > $max_size) {
                $errors[] = '写真ファイルは' . format_file_size_limit($max_size) . '以下にしてください。';
            }

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime_type = $finfo->file($file['tmp_name']);

            if (!isset($allowed_mime_types[$mime_type])) {
                $errors[] = '写真は JPEG, PNG, GIF, WebP の画像ファイルだけ登録できます。';
            } else {
                $uploaded_photo_info = [
                    'tmp_name' => $file['tmp_name'],
                    'original_name' => $file['name'],
                    'mime_type' => $mime_type,
                    'file_size' => $file['size'],
                    'ext' => $allowed_mime_types[$mime_type],
                    'caption' => $photo_caption,
                ];
            }
        }
    }

    if (count($errors) === 0) {
        $saved_photo_path = null;

        try {
            $pdo->beginTransaction();

            $now = date('Y-m-d H:i:s');

            $stmt = $pdo->prepare("
                INSERT INTO items
                    (
                        asset_tag,
                        model,
                        name,
                        category,
                        manufacturer,
                        property_number,
                        default_location,
                        purchase_date,
                        manual_url,
                        serial_number,
                        note,
                        created_at,
                        updated_at
                    )
                VALUES
                    (
                        :asset_tag,
                        :model,
                        :name,
                        :category,
                        :manufacturer,
                        :property_number,
                        :default_location,
                        :purchase_date,
                        :manual_url,
                        :serial_number,
                        :note,
                        :created_at,
                        :updated_at
                    )
            ");

            $stmt->execute([
                ':asset_tag' => $asset_tag,
                ':model' => $model,
                ':name' => $name,
                ':category' => $category,
                ':manufacturer' => $manufacturer,
                ':property_number' => $property_number,
                ':default_location' => $default_location_value,
                ':purchase_date' => $purchase_date,
                ':manual_url' => $manual_url,
                ':serial_number' => $serial_number,
                ':note' => $note,
                ':created_at' => $now,
                ':updated_at' => $now,
            ]);

            $item_id = (int)$pdo->lastInsertId();

            $stmt = $pdo->prepare("
                INSERT INTO movements
                    (item_id, location, user_name, status, memo, moved_at)
                VALUES
                    (:item_id, :location, :user_name, :status, :memo, :moved_at)
            ");

            $stmt->execute([
                ':item_id' => $item_id,
                ':location' => $location,
                ':user_name' => $user_name,
                ':status' => $status,
                ':memo' => $move_memo,
                ':moved_at' => $now,
            ]);

            /*
             * コピー元装置の写真を新規装置にも関連付ける。
             * 実ファイルはコピーしない。
             */
            if ($copy_photos) {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM item_photos
                    WHERE item_id = :item_id
                    ORDER BY uploaded_at ASC, id ASC
                ");
                $stmt->execute([':item_id' => $copy_from_id]);
                $source_photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt_insert_photo = $pdo->prepare("
                    INSERT INTO item_photos
                        (item_id, original_name, stored_name, mime_type, file_size, caption, uploaded_at)
                    VALUES
                        (:item_id, :original_name, :stored_name, :mime_type, :file_size, :caption, :uploaded_at)
                ");

                foreach ($source_photos as $photo) {
                    $stmt_insert_photo->execute([
                        ':item_id' => $item_id,
                        ':original_name' => $photo['original_name'],
                        ':stored_name' => $photo['stored_name'],
                        ':mime_type' => $photo['mime_type'],
                        ':file_size' => $photo['file_size'],
                        ':caption' => $photo['caption'],
                        ':uploaded_at' => $now,
                    ]);
                }
            }

            /*
             * コピー元装置のマニュアルを新規装置にも関連付ける。
             * 実ファイルはコピーしない。
             */
            if ($copy_manuals) {
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM item_manuals
                    WHERE item_id = :item_id
                    ORDER BY uploaded_at ASC, id ASC
                ");
                $stmt->execute([':item_id' => $copy_from_id]);
                $source_manuals = $stmt->fetchAll(PDO::FETCH_ASSOC);

                $stmt_insert_manual = $pdo->prepare("
                    INSERT INTO item_manuals
                        (item_id, original_name, stored_name, mime_type, file_size, title, uploaded_at)
                    VALUES
                        (:item_id, :original_name, :stored_name, :mime_type, :file_size, :title, :uploaded_at)
                ");

                foreach ($source_manuals as $manual) {
                    $stmt_insert_manual->execute([
                        ':item_id' => $item_id,
                        ':original_name' => $manual['original_name'],
                        ':stored_name' => $manual['stored_name'],
                        ':mime_type' => $manual['mime_type'],
                        ':file_size' => $manual['file_size'],
                        ':title' => $manual['title'],
                        ':uploaded_at' => $now,
                    ]);
                }
            }

            /*
             * 新しい写真が指定されている場合は、ファイル保存して写真レコードを追加する。
             */
            if ($uploaded_photo_info !== null) {
                $photo_dir = $config['photo_directory'];

                if (!is_dir($photo_dir)) {
                    if (!mkdir($photo_dir, 0700, true)) {
                        throw new Exception('写真保存用ディレクトリを作成できません。');
                    }
                }

                $random = bin2hex(random_bytes(8));
                $stored_name = 'item_' . $item_id . '_' . date('Ymd_His') . '_' . $random . '.' . $uploaded_photo_info['ext'];
                $dest = $photo_dir . '/' . $stored_name;

                if (!move_uploaded_file($uploaded_photo_info['tmp_name'], $dest)) {
                    throw new Exception('写真ファイルを保存できませんでした。');
                }

                chmod($dest, 0600);
                $saved_photo_path = $dest;

                $stmt = $pdo->prepare("
                    INSERT INTO item_photos
                        (item_id, original_name, stored_name, mime_type, file_size, caption, uploaded_at)
                    VALUES
                        (:item_id, :original_name, :stored_name, :mime_type, :file_size, :caption, :uploaded_at)
                ");

                $stmt->execute([
                    ':item_id' => $item_id,
                    ':original_name' => $uploaded_photo_info['original_name'],
                    ':stored_name' => $stored_name,
                    ':mime_type' => $uploaded_photo_info['mime_type'],
                    ':file_size' => $uploaded_photo_info['file_size'],
                    ':caption' => $uploaded_photo_info['caption'],
                    ':uploaded_at' => $now,
                ]);
            }

            $pdo->commit();

            header('Location: item.php?id=' . urlencode($item_id));
            exit;
        } catch (Exception $e) {
            $pdo->rollBack();

            /*
             * DB登録失敗時に、もし写真ファイルだけ保存済みなら消しておく。
             */
            if ($saved_photo_path !== null && is_file($saved_photo_path)) {
                unlink($saved_photo_path);
            }

            if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                $errors[] = 'この管理番号はすでに登録されています。';
            } else {
                $errors[] = '登録に失敗しました: ' . $e->getMessage();
            }
        }
    }

    /*
     * エラー時にPOST内容を再表示するため、初期値を更新する。
     */
    $default_asset_tag = $asset_tag;
    $default_model = $model;
    $default_name = $name;
    $default_category = $category;
    $default_manufacturer = $manufacturer;
    $default_property_number = $property_number;
    $default_default_location = $default_location_value;
    $default_purchase_date = $purchase_date;
    $default_manual_url = $manual_url;
    $default_serial_number = $serial_number;
    $default_note = $note;

    $default_location = $location;
    $default_user_name = $user_name;
    $default_status = $status;
    $default_move_memo = $move_memo;
    $default_copy_photos = isset($_POST['copy_photos']);
    $default_copy_manuals = isset($_POST['copy_manuals']);
    $default_photo_caption = $photo_caption;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>新規装置登録 - <?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>新規装置登録</h1>

<div class="menu">
    <a href="index.php">一覧へ戻る</a>
    <a href="board.php">掲示板</a>
</div>

<div class="box">
    <h2>既存装置から基本情報をコピー</h2>

    <form method="get" action="new_item.php">
        <table>
            <tr>
                <th>コピー元装置</th>
                <td>
                    <select name="copy_from_id">
                        <option value="">選択してください</option>
                        <?php foreach ($copy_candidates as $c): ?>
                            <option value="<?php echo h($c['id']); ?>"
                                <?php if ($copy_from_id === (int)$c['id']) echo 'selected'; ?>>
                                <?php echo h($c['asset_tag']); ?>
                                ／ <?php echo h($c['model']); ?>
                                ／ <?php echo h($c['name']); ?>
                                ／ <?php echo h($c['manufacturer']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="submit">コピー</button>

                    <?php if ($copy_item): ?>
                        <div class="note">
                            コピー中：
                            <?php echo h($copy_item['asset_tag']); ?>
                            ／ <?php echo h($copy_item['model']); ?>
                            ／ <?php echo h($copy_item['name']); ?>
                            <?php if ($copy_photo_count > 0): ?>
                                ／ 写真 <?php echo h($copy_photo_count); ?> 枚あり
                            <?php else: ?>
                                ／ 写真なし
                            <?php endif; ?>
                            <?php if ($copy_manual_count > 0): ?>
                                ／ マニュアル <?php echo h($copy_manual_count); ?> 件あり
                            <?php else: ?>
                                ／ マニュアルなし
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </form>

    <p class="note">
        型番、名称、カテゴリ、メーカー、マニュアルURLだけをコピーします。
        管理番号、備品番号、シリアル番号、購入年月、置き場、使用者、状態、メモはコピーしません。
    </p>
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

<form method="post"
      enctype="multipart/form-data"
      action="new_item.php<?php echo $copy_from_id > 0 ? '?copy_from_id=' . h($copy_from_id) : ''; ?>">

    <div class="box">
        <h2>基本情報</h2>

        <table>
            <tr>
                <th>管理番号 *</th>
                <td>
                    <input type="text" name="asset_tag" value="<?php echo h($default_asset_tag); ?>" required>
                </td>
            </tr>
            <tr>
                <th>型番</th>
                <td>
                    <input type="text" name="model" value="<?php echo h($default_model); ?>">
                </td>
            </tr>
            <tr>
                <th>名称 *</th>
                <td>
                    <input type="text" name="name" value="<?php echo h($default_name); ?>" required>
                </td>
            </tr>
            <tr>
                <th>カテゴリ</th>
                <td>
                    <input type="text" name="category" value="<?php echo h($default_category); ?>">
                </td>
            </tr>
            <tr>
                <th>メーカー</th>
                <td>
                    <input type="text" name="manufacturer" value="<?php echo h($default_manufacturer); ?>">
                </td>
            </tr>
            <tr>
                <th>備品番号</th>
                <td>
                    <input type="text" name="property_number" value="<?php echo h($default_property_number); ?>">
                </td>
            </tr>
            <tr>
                <th>デフォルト置き場</th>
                <td>
                    <input type="text"
                           name="default_location"
                           list="location_candidates"
                           value="<?php echo h($default_default_location); ?>">
                    <div class="note">
                        通常戻すべき置き場を入力します。過去の場所候補から選ぶこともできます。
                    </div>
                </td>
            </tr>
            <tr>
                <th>購入年月</th>
                <td>
                    <input type="text" name="purchase_date" value="<?php echo h($default_purchase_date); ?>" placeholder="例: 2026-06">
                </td>
            </tr>
            <tr>
                <th>マニュアルURL</th>
                <td>
                    <input type="url" name="manual_url" value="<?php echo h($default_manual_url); ?>" placeholder="https://...">
                </td>
            </tr>
            <tr>
                <th>シリアル番号</th>
                <td>
                    <input type="text" name="serial_number" value="<?php echo h($default_serial_number); ?>">
                </td>
            </tr>
            <tr>
                <th>メモ</th>
                <td>
                    <textarea name="note" rows="4"><?php echo h($default_note); ?></textarea>
                </td>
            </tr>

            <?php if ($copy_item && $copy_photo_count > 0): ?>
                <tr>
                    <th>コピー元写真</th>
                    <td>
                        <label>
                            <input type="checkbox" name="copy_photos" value="1"
                                <?php if ($default_copy_photos) echo 'checked'; ?>>
                            コピー元装置の写真 <?php echo h($copy_photo_count); ?> 枚もこの装置に登録する
                        </label>
                        <div class="note">
                            写真ファイルはコピーせず、同じ写真を共有します。
                        </div>
                    </td>
                </tr>
            <?php endif; ?>

            <?php if ($copy_item && $copy_manual_count > 0): ?>
                <tr>
                    <th>コピー元マニュアル</th>
                    <td>
                        <label>
                            <input type="checkbox" name="copy_manuals" value="1"
                                <?php if ($default_copy_manuals) echo 'checked'; ?>>
                            コピー元装置のマニュアル <?php echo h($copy_manual_count); ?> 件もこの装置に登録する
                        </label>
                        <div class="note">
                            マニュアルファイルはコピーせず、同じPDFを共有します。
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>

    <div class="box">
        <h2>新しい写真</h2>

        <table>
            <tr>
                <th>写真ファイル</th>
                <td>
                    <input type="file" name="photo" accept="image/*">
                    <div class="note">
                        登録時に新しい写真も追加できます。JPEG, PNG, GIF, WebP に対応、最大<?php echo h(format_file_size_limit($config['photo_max_size'])); ?>です。
                    </div>
                </td>
            </tr>
            <tr>
                <th>写真の説明</th>
                <td>
                    <input type="text" name="photo_caption" value="<?php echo h($default_photo_caption); ?>">
                    <div class="note">
                        例：前面パネル、背面コネクタ、銘板、付属品など
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h2>初期所在地</h2>

        <table>
            <tr>
                <th>初期所在地 *</th>
                <td>
                    <input type="text"
                           name="location"
                           list="location_candidates"
                           value="<?php echo h($default_location); ?>"
                           required>
                    <div class="note">
                        過去の場所候補から選ぶこともできます。候補にない場所も直接入力できます。
                    </div>
                </td>
            </tr>
            <tr>
                <th>使用者</th>
                <td>
                    <input type="text" name="user_name" value="<?php echo h($default_user_name); ?>">
                </td>
            </tr>
            <tr>
                <th>状態</th>
                <td>
                    <select name="status">
                        <?php foreach ($statuses as $s): ?>
                            <option value="<?php echo h($s); ?>"
                                <?php if ($default_status === $s) echo 'selected'; ?>>
                                <?php echo h($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>移動メモ</th>
                <td>
                    <textarea name="move_memo" rows="3"><?php echo h($default_move_memo); ?></textarea>
                </td>
            </tr>
        </table>
    </div>

    <p>
        <button type="submit">登録</button>
    </p>

    <datalist id="location_candidates">
        <?php foreach ($location_candidates as $place): ?>
            <option value="<?php echo h($place); ?>">
        <?php endforeach; ?>
    </datalist>
</form>

</body>
</html>
