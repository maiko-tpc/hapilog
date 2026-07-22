<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

/*
 * 戻り先URL。
 *
 * 外部サイトへ飛ばないように、index.php で始まるものだけ許可する。
 */
$redirect_to = trim($_POST['redirect_to'] ?? 'index.php');

if (
    $redirect_to === ''
    || strpos($redirect_to, 'index.php') !== 0
    || preg_match('/^https?:\/\//i', $redirect_to)
    || strpos($redirect_to, '//') === 0
) {
    $redirect_to = 'index.php';
}

/*
 * 選択された装置IDを整理する。
 */
$raw_ids = $_POST['item_ids'] ?? [];

if (!is_array($raw_ids)) {
    $raw_ids = [];
}

$item_ids = [];

foreach ($raw_ids as $raw_id) {
    $id = (int)$raw_id;

    if ($id > 0) {
        $item_ids[] = $id;
    }
}

$item_ids = array_values(array_unique($item_ids));

$requested_count = count($item_ids);
$moved_count = 0;
$skipped_count = 0;

if ($requested_count === 0) {
    $query_separator = strpos($redirect_to, '?') === false ? '?' : '&';

    header(
        'Location: '
        . $redirect_to
        . $query_separator
        . http_build_query([
            'bulk_return' => 'done',
            'bulk_requested' => 0,
            'bulk_moved' => 0,
            'bulk_skipped' => 0,
        ])
    );
    exit;
}

/*
 * 選択された装置を取得する。
 *
 * 最新の移動履歴も同時に見る。
 */
$placeholders = [];

foreach ($item_ids as $index => $id) {
    $placeholders[] = ':id' . $index;
}

$sql = "
    SELECT
        items.id,
        items.default_location,
        latest.location AS current_location
    FROM items
    LEFT JOIN movements latest
        ON latest.id = (
            SELECT m2.id
            FROM movements m2
            WHERE m2.item_id = items.id
            ORDER BY m2.moved_at DESC, m2.id DESC
            LIMIT 1
        )
    WHERE items.id IN (" . implode(', ', $placeholders) . ")
";

$stmt = $pdo->prepare($sql);

foreach ($item_ids as $index => $id) {
    $stmt->bindValue(':id' . $index, $id, PDO::PARAM_INT);
}

$stmt->execute();
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

/*
 * 見つからなかったIDもスキップに数える。
 */
$found_count = count($items);
$skipped_count += $requested_count - $found_count;

try {
    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');

    $insert_stmt = $pdo->prepare("
        INSERT INTO movements
            (
                item_id,
                location,
                user_name,
                status,
                memo,
                moved_at
            )
        VALUES
            (
                :item_id,
                :location,
                :user_name,
                :status,
                :memo,
                :moved_at
            )
    ");

    foreach ($items as $item) {
        $item_id = (int)$item['id'];

        $default_location =
            trim((string)($item['default_location'] ?? ''));

        $current_location =
            trim((string)($item['current_location'] ?? ''));

        /*
         * デフォルト置き場が未設定ならスキップ。
         */
        if ($default_location === '') {
            $skipped_count++;
            continue;
        }

        /*
         * すでにデフォルト置き場にあるなら、
         * 不要な移動履歴を増やさない。
         */
        if ($current_location !== '' && $current_location === $default_location) {
            $skipped_count++;
            continue;
        }

        $insert_stmt->execute([
            ':item_id' => $item_id,
            ':location' => $default_location,
            ':user_name' => '',
            ':status' => '使用可',
            ':memo' => '一括でデフォルト置き場に戻した',
            ':moved_at' => $now,
        ]);

        $moved_count++;
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo "一括デフォルト戻しに失敗しました。";
    echo "<br>";
    echo h($e->getMessage());
    echo "<br>";
    echo '<a href="index.php">一覧へ戻る</a>';

    exit;
}

$query_separator = strpos($redirect_to, '?') === false ? '?' : '&';

header(
    'Location: '
    . $redirect_to
    . $query_separator
    . http_build_query([
        'bulk_return' => 'done',
        'bulk_requested' => $requested_count,
        'bulk_moved' => $moved_count,
        'bulk_skipped' => $skipped_count,
    ])
);
exit;
