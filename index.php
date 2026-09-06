<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';


/*
 * 日時を分単位で表示する。
 */
function format_datetime_minute($s) {
    $s = trim((string)$s);

    if ($s === '') {
        return '';
    }

    $timestamp = strtotime($s);

    if ($timestamp === false) {
        return $s;
    }

    return date('Y/m/d H:i', $timestamp);
}

/*
 * 複数の日時候補から最も新しい日時を返す。
 */
function find_latest_datetime(array $values) {
    $latest_value = '';
    $latest_timestamp = false;

    foreach ($values as $value) {
        $value = trim((string)$value);

        if ($value === '') {
            continue;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            continue;
        }

        if ($latest_timestamp === false || $timestamp > $latest_timestamp) {
            $latest_timestamp = $timestamp;
            $latest_value = $value;
        }
    }

    return $latest_value;
}

/*
 * 備品番号を表内で見やすく表示する。
 *
 * カンマ区切りで複数登録されている場合は、
 * 1件ずつ改行して表示する。
 */
function format_property_number_for_table($s) {
    $s = trim((string)$s);

    if ($s === '') {
        return '';
    }

    $parts = preg_split('/\s*,\s*/', $s);
    $parts = array_filter($parts, function ($part) {
        return trim((string)$part) !== '';
    });

    if (count($parts) === 0) {
        return $s;
    }

    return implode("\n", $parts);
}

/*
 * 検索・絞り込み条件
 */
$q = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$status = trim($_GET['status'] ?? '');
$location = trim($_GET['location'] ?? '');
$sort = trim($_GET['sort'] ?? 'asset_tag');
$off_default = isset($_GET['off_default']) && $_GET['off_default'] === '1';

$allowed_sorts = [
    'asset_tag',
    'updated_desc',
    'updated_asc',
];

if (!in_array($sort, $allowed_sorts, true)) {
    $sort = 'asset_tag';
}

/*
 * 一括デフォルト戻し結果
 */
$bulk_return = trim($_GET['bulk_return'] ?? '');
$bulk_requested = isset($_GET['bulk_requested']) ? (int)$_GET['bulk_requested'] : 0;
$bulk_moved = isset($_GET['bulk_moved']) ? (int)$_GET['bulk_moved'] : 0;
$bulk_skipped = isset($_GET['bulk_skipped']) ? (int)$_GET['bulk_skipped'] : 0;

/*
 * 一括移動登録結果
 */
$bulk_move = trim($_GET['bulk_move'] ?? '');
$bulk_move_requested = isset($_GET['bulk_move_requested']) ? (int)$_GET['bulk_move_requested'] : 0;
$bulk_move_moved = isset($_GET['bulk_move_moved']) ? (int)$_GET['bulk_move_moved'] : 0;
$bulk_move_skipped = isset($_GET['bulk_move_skipped']) ? (int)$_GET['bulk_move_skipped'] : 0;

/*
 * 一括処理後に戻るURL。
 * 一括処理結果のGETパラメータは引き継がない。
 */
$return_query = [];

if ($q !== '') {
    $return_query['q'] = $q;
}

if ($category !== '') {
    $return_query['category'] = $category;
}

if ($status !== '') {
    $return_query['status'] = $status;
}

if ($location !== '') {
    $return_query['location'] = $location;
}

if ($sort !== 'asset_tag') {
    $return_query['sort'] = $sort;
}

if ($off_default) {
    $return_query['off_default'] = '1';
}

$return_url = 'index.php';

if (count($return_query) > 0) {
    $return_url .= '?' . http_build_query($return_query);
}

/*
 * 各装置の最新移動履歴を取得するためのJOIN。
 * moved_atが同じ履歴でも、idが最大の1件だけを取得する。
 */
$latest_join = "
    LEFT JOIN movements latest
        ON latest.id = (
            SELECT m2.id
            FROM movements m2
            WHERE m2.item_id = items.id
            ORDER BY m2.moved_at DESC, m2.id DESC
            LIMIT 1
        )
";

/*
 * 装置情報としての更新日時。
 *
 * updated_at が空でなければ updated_at、
 * 空なら created_at を使う。
 */
$item_updated_expr = "
    CASE
        WHEN TRIM(COALESCE(items.updated_at, '')) <> ''
        THEN items.updated_at
        ELSE items.created_at
    END
";

/*
 * 一覧上の最終更新日時。
 *
 * 装置情報の更新日時と最新移動履歴日時を比較して、
 * より新しい方を使う。
 */
$row_updated_expr = "
    CASE
        WHEN TRIM(COALESCE(latest.moved_at, '')) = ''
        THEN {$item_updated_expr}

        WHEN TRIM(COALESCE({$item_updated_expr}, '')) = ''
        THEN latest.moved_at

        WHEN latest.moved_at >= {$item_updated_expr}
        THEN latest.moved_at

        ELSE {$item_updated_expr}
    END
";

/*
 * 簡易サマリー
 */
$summary_sql = "
    SELECT
        COUNT(items.id) AS total_count,

        SUM(
            CASE
                WHEN latest.status = '使用可' THEN 1
                ELSE 0
            END
        ) AS available_count,

        SUM(
            CASE
                WHEN latest.status = '使用中' THEN 1
                ELSE 0
            END
        ) AS in_use_count,

        SUM(
            CASE
                WHEN latest.status = '貸出中' THEN 1
                ELSE 0
            END
        ) AS loaned_count,

        SUM(
            CASE
                WHEN latest.status = '修理中' THEN 1
                ELSE 0
            END
        ) AS repair_count,

        SUM(
            CASE
                WHEN latest.status = '故障' THEN 1
                ELSE 0
            END
        ) AS broken_count,

        SUM(
            CASE
                WHEN TRIM(COALESCE(items.default_location, '')) <> ''
                 AND TRIM(COALESCE(latest.location, '')) <> ''
                 AND TRIM(items.default_location) <> TRIM(latest.location)
                THEN 1
                ELSE 0
            END
        ) AS off_default_count

    FROM items
    {$latest_join}
";

$stmt = $pdo->query($summary_sql);
$summary_result = $stmt->fetch(PDO::FETCH_ASSOC);

$summary = [
    'total_count' => (int)($summary_result['total_count'] ?? 0),
    'available_count' => (int)($summary_result['available_count'] ?? 0),
    'in_use_count' => (int)($summary_result['in_use_count'] ?? 0),
    'loaned_count' => (int)($summary_result['loaned_count'] ?? 0),
    'repair_count' => (int)($summary_result['repair_count'] ?? 0),
    'broken_count' => (int)($summary_result['broken_count'] ?? 0),
    'off_default_count' => (int)($summary_result['off_default_count'] ?? 0),
];

/*
 * システム内の最終更新日時。
 *
 * 各テーブルの最新日時を個別に取得し、
 * PHP側で最も新しいものを選ぶ。
 */
$stmt = $pdo->query("
    SELECT MAX(
        CASE
            WHEN TRIM(COALESCE(updated_at, '')) <> ''
            THEN updated_at
            ELSE created_at
        END
    )
    FROM items
");
$latest_item_datetime = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT MAX(moved_at)
    FROM movements
");
$latest_movement_datetime = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT MAX(uploaded_at)
    FROM item_photos
");
$latest_photo_datetime = $stmt->fetchColumn();

$stmt = $pdo->query("
    SELECT MAX(uploaded_at)
    FROM item_manuals
");
$latest_manual_datetime = $stmt->fetchColumn();

$system_last_updated = find_latest_datetime([
    $latest_item_datetime,
    $latest_movement_datetime,
    $latest_photo_datetime,
    $latest_manual_datetime,
]);

$system_last_updated_display =
    format_datetime_minute($system_last_updated);

/*
 * 絞り込み候補
 */
$stmt = $pdo->query("
    SELECT DISTINCT category
    FROM items
    WHERE TRIM(COALESCE(category, '')) <> ''
    ORDER BY category ASC
");
$category_candidates = $stmt->fetchAll(PDO::FETCH_COLUMN);

$stmt = $pdo->query("
    SELECT DISTINCT status
    FROM movements
    WHERE TRIM(COALESCE(status, '')) <> ''
    ORDER BY status ASC
");
$status_candidates = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
 * 一覧のWHERE条件
 */
$where = [];
$params = [];

if ($q !== '') {
    $where[] = "
        (
            items.asset_tag LIKE :q
            OR items.model LIKE :q
            OR items.name LIKE :q
            OR items.category LIKE :q
            OR items.manufacturer LIKE :q
            OR items.property_number LIKE :q
            OR items.default_location LIKE :q
            OR items.purchase_date LIKE :q
            OR items.manual_url LIKE :q
            OR items.serial_number LIKE :q
            OR items.note LIKE :q

            OR latest.location LIKE :q
            OR latest.user_name LIKE :q
            OR latest.status LIKE :q
            OR latest.memo LIKE :q

            OR EXISTS (
                SELECT 1
                FROM movements mh
                WHERE mh.item_id = items.id
                  AND (
                        mh.location LIKE :q
                        OR mh.user_name LIKE :q
                        OR mh.status LIKE :q
                        OR mh.memo LIKE :q
                        OR mh.moved_at LIKE :q
                  )
            )

            OR EXISTS (
                SELECT 1
                FROM item_photos ph
                WHERE ph.item_id = items.id
                  AND (
                        ph.original_name LIKE :q
                        OR ph.stored_name LIKE :q
                        OR ph.caption LIKE :q
                        OR ph.uploaded_at LIKE :q
                  )
            )

            OR EXISTS (
                SELECT 1
                FROM item_manuals ma
                WHERE ma.item_id = items.id
                  AND (
                        ma.original_name LIKE :q
                        OR ma.stored_name LIKE :q
                        OR ma.title LIKE :q
                        OR ma.uploaded_at LIKE :q
                  )
            )
        )
    ";

    $params[':q'] = '%' . $q . '%';
}

if ($category !== '') {
    $where[] = 'items.category = :category';
    $params[':category'] = $category;
}

if ($status !== '') {
    $where[] = 'latest.status = :status';
    $params[':status'] = $status;
}

if ($location !== '') {
    $where[] = 'latest.location = :location';
    $params[':location'] = $location;
}

if ($off_default) {
    $where[] = "
        TRIM(COALESCE(items.default_location, '')) <> ''
        AND TRIM(COALESCE(latest.location, '')) <> ''
        AND TRIM(items.default_location) <> TRIM(latest.location)
    ";
}

$where_sql = '';

if (count($where) > 0) {
    $where_sql = 'WHERE ' . implode(' AND ', $where);
}

/*
 * 表示順
 *
 * updated_desc / updated_asc では、
 * 最新移動履歴だけでなく、装置情報の更新日時も考慮する。
 */
switch ($sort) {
    case 'updated_desc':
        $order_sql = "
            CASE
                WHEN TRIM(COALESCE(row_updated_at, '')) = ''
                THEN 1
                ELSE 0
            END ASC,
            row_updated_at DESC,
            items.asset_tag ASC
        ";
        break;

    case 'updated_asc':
        $order_sql = "
            CASE
                WHEN TRIM(COALESCE(row_updated_at, '')) = ''
                THEN 1
                ELSE 0
            END ASC,
            row_updated_at ASC,
            items.asset_tag ASC
        ";
        break;

    case 'asset_tag':
    default:
        $order_sql = 'items.asset_tag ASC';
        break;
}

/*
 * 装置一覧
 */
$list_sql = "
    SELECT
        items.*,
        latest.location,
        latest.user_name,
        latest.status,
        latest.memo AS latest_memo,
        latest.moved_at,
        {$item_updated_expr} AS item_updated_at,
        {$row_updated_expr} AS row_updated_at
    FROM items
    {$latest_join}
    {$where_sql}
    ORDER BY {$order_sql}
";

$stmt = $pdo->prepare($list_sql);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$display_count = count($items);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title><?php echo h(system_name()); ?></title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="page-header">
    <h1><?php echo h(system_name()); ?></h1>

    <div class="compact-summary">
        <div>
            登録 <?php echo h($summary['total_count']); ?>
            ／ 使用可 <?php echo h($summary['available_count']); ?>
            ／ 使用中 <?php echo h($summary['in_use_count']); ?>
            ／ 貸出中 <?php echo h($summary['loaned_count']); ?>
            ／ 修理中 <?php echo h($summary['repair_count']); ?>
            ／ 故障 <?php echo h($summary['broken_count']); ?>
            ／ 置き場外 <?php echo h($summary['off_default_count']); ?>
        </div>

        <div>
            最終更新
            <?php if ($system_last_updated_display !== ''): ?>
                <?php echo h($system_last_updated_display); ?>
            <?php else: ?>
                -
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="menu">
    <a href="index.php">一覧</a>
    <a href="new_item.php">新規装置登録</a>
    <a href="export_csv.php">CSV出力</a>
    <a href="board.php">掲示板</a>
</div>

<?php if ($bulk_return === 'done'): ?>
    <div class="box">
        <strong>一括デフォルト戻し:</strong>

        選択
        <?php echo h($bulk_requested); ?>
        件 ／ 移動登録
        <?php echo h($bulk_moved); ?>
        件 ／ スキップ
        <?php echo h($bulk_skipped); ?>
        件
    </div>
<?php endif; ?>

<?php if ($bulk_move === 'done'): ?>
    <div class="box">
        <strong>一括移動登録:</strong>

        選択
        <?php echo h($bulk_move_requested); ?>
        件 ／ 移動登録
        <?php echo h($bulk_move_moved); ?>
        件 ／ スキップ
        <?php echo h($bulk_move_skipped); ?>
        件
    </div>
<?php endif; ?>

<div class="box">
    <h2>検索</h2>

    <form method="get" action="index.php" class="search">
        <input type="text"
               name="q"
               value="<?php echo h($q); ?>"
               placeholder="管理番号、型番、名称、メーカー、場所、使用者、履歴メモ、写真キャプションなど">

        <input type="hidden"
               name="category"
               value="<?php echo h($category); ?>">

        <input type="hidden"
               name="status"
               value="<?php echo h($status); ?>">

        <input type="hidden"
               name="location"
               value="<?php echo h($location); ?>">

        <input type="hidden"
               name="sort"
               value="<?php echo h($sort); ?>">

        <?php if ($off_default): ?>
            <input type="hidden"
                   name="off_default"
                   value="1">
        <?php endif; ?>

        <button type="submit">検索</button>

        <?php if (
            $q !== ''
            || $category !== ''
            || $status !== ''
            || $location !== ''
            || $off_default
            || $sort !== 'asset_tag'
        ): ?>
            <a href="index.php">条件を解除</a>
        <?php endif; ?>
    </form>
</div>

<div class="box">
    <h2>絞り込み・表示順</h2>

    <form method="get"
          action="index.php"
          class="filters">

        <input type="hidden"
               name="q"
               value="<?php echo h($q); ?>">

        <label>
            カテゴリ
            <select name="category">
                <option value="">すべて</option>

                <?php foreach ($category_candidates as $candidate): ?>
                    <option value="<?php echo h($candidate); ?>"
                        <?php if ($category === $candidate) echo 'selected'; ?>>
                        <?php echo h($candidate); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            状態
            <select name="status">
                <option value="">すべて</option>

                <?php foreach ($status_candidates as $candidate): ?>
                    <option value="<?php echo h($candidate); ?>"
                        <?php if ($status === $candidate) echo 'selected'; ?>>
                        <?php echo h($candidate); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            現在地
            <select name="location">
                <option value="">すべて</option>

                <?php foreach ($location_candidates as $candidate): ?>
                    <option value="<?php echo h($candidate); ?>"
                        <?php if ($location === $candidate) echo 'selected'; ?>>
                        <?php echo h($candidate); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            表示順
            <select name="sort">
                <option value="asset_tag"
                    <?php if ($sort === 'asset_tag') echo 'selected'; ?>>
                    管理番号順
                </option>

                <option value="updated_desc"
                    <?php if ($sort === 'updated_desc') echo 'selected'; ?>>
                    最終更新が新しい順
                </option>

                <option value="updated_asc"
                    <?php if ($sort === 'updated_asc') echo 'selected'; ?>>
                    最終更新が古い順
                </option>
            </select>
        </label>

        <label>
            <input type="checkbox"
                   name="off_default"
                   value="1"
                <?php if ($off_default) echo 'checked'; ?>>

            デフォルト置き場と現在地が違う装置のみ
        </label>

        <button type="submit">適用</button>
    </form>
</div>

<div class="box">
    <h2>装置一覧</h2>

    <p>
        表示中：
        <strong><?php echo h($display_count); ?></strong>
        件
        ／ 全
        <strong><?php echo h($summary['total_count']); ?></strong>
        件
    </p>

    <?php if ($display_count === 0): ?>
        <div class="empty">
            条件に一致する装置はありません。
        </div>
    <?php else: ?>
        <form
            method="post"
            action="bulk_move.php"
        >
            <input
                type="hidden"
                name="redirect_to"
                value="<?php echo h($return_url); ?>"
            >

            <p>
                <button
                    type="submit"
                    formaction="bulk_move.php"
                >
                    チェックした装置を同じ内容で移動登録
                </button>

                <button
                    type="submit"
                    formaction="bulk_return_default.php"
                    onclick="return confirm('チェックした装置をデフォルト置き場に戻します。よろしいですか？');"
                >
                    チェックした装置をデフォルト置き場に戻す
                </button>
            </p>

            <table>
                <tr>
                    <th></th>
                    <th>管理番号</th>
                    <th>型番</th>
                    <th>名称</th>
                    <th>カテゴリ</th>
                    <th>メーカー</th>
                    <th>備品番号</th>
                    <th>デフォルト置き場</th>
                    <th>現在地</th>
                    <th>使用者</th>
                    <th>最終更新</th>
                </tr>

                <?php foreach ($items as $item): ?>
                    <tr>
                        <td>
                            <input
                                type="checkbox"
                                name="item_ids[]"
                                value="<?php echo h($item['id']); ?>"
                            >
                        </td>

                        <td>
                            <a href="item.php?id=<?php echo h($item['id']); ?>">
                                <?php echo h($item['asset_tag']); ?>
                            </a>
                        </td>

                        <td><?php echo h($item['model']); ?></td>
                        <td><?php echo h($item['name']); ?></td>
                        <td><?php echo h($item['category']); ?></td>
                        <td><?php echo h($item['manufacturer']); ?></td>
                        <td><?php echo nl2br(h(format_property_number_for_table($item['property_number']))); ?></td>
                        <td><?php echo h($item['default_location']); ?></td>
                        <td><?php echo h($item['location']); ?></td>
                        <td><?php echo h($item['user_name']); ?></td>
                        <td class="nowrap">
                            <?php echo h(
                                format_datetime_minute($item['row_updated_at'])
                            ); ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
