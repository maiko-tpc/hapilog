<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

$sql = "
    SELECT
        items.asset_tag,
        items.model,
        items.name,
        items.category,
        items.manufacturer,
        items.property_number,
        items.default_location,
        items.purchase_date,
        items.manual_url,
        latest.location,
        latest.user_name,
        latest.status,
        latest.moved_at,
        items.note
    FROM items
    LEFT JOIN (
        SELECT m1.*
        FROM movements m1
        INNER JOIN (
            SELECT item_id, MAX(moved_at) AS max_moved_at
            FROM movements
            GROUP BY item_id
        ) m2
        ON m1.item_id = m2.item_id AND m1.moved_at = m2.max_moved_at
    ) latest
    ON items.id = latest.item_id
    ORDER BY items.asset_tag ASC
";

$stmt = $pdo->query($sql);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filename = 'equipment_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// UTF-8 BOM for Excel
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

fputcsv($out, [
    '管理番号',
    '型番',
    '名称',
    'カテゴリ',
    'メーカー',
    '備品番号',
    'デフォルト置き場',
    '購入年月',
    'マニュアルURL',
    '現在地',
    '使用者',
    '状態',
    '最終更新',
    'メモ'
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['asset_tag'],
        $row['model'],
        $row['name'],
        $row['category'],
        $row['manufacturer'],
        $row['property_number'],
        $row['default_location'],
        $row['purchase_date'],
        $row['manual_url'],
        $row['location'],
        $row['user_name'],
        $row['status'],
        $row['moved_at'],
        $row['note']
    ]);
}

fclose($out);
exit;
