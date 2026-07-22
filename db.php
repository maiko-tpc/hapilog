<?php

/*
 * 設定ファイルの読み込み
 */
$config_file = __DIR__ . '/config.php';

if (!is_file($config_file)) {
    throw new RuntimeException(
        '設定ファイルが見つかりません: ' . $config_file
    );
}

$config = require $config_file;

if (!is_array($config)) {
    throw new RuntimeException(
        'config.php の戻り値が配列ではありません。'
    );
}

/*
 * 必須設定の確認
 */
$required_config_keys = [
    'system_name',
    'system_short_name',
    'timezone',
    'database_path',
    'photo_directory',
    'manual_directory',
    'backup_directory',
    'backup_keep',
    'photo_max_size',
    'manual_max_size',
];

foreach ($required_config_keys as $key) {
    if (!array_key_exists($key, $config)) {
        throw new RuntimeException(
            'config.php に設定項目がありません: ' . $key
        );
    }
}

/*
 * タイムゾーン
 */
if (!date_default_timezone_set($config['timezone'])) {
    throw new RuntimeException(
        'タイムゾーンの設定が不正です: '
        . $config['timezone']
    );
}

/*
 * データ保存ディレクトリ
 */
$database_directory = dirname($config['database_path']);

$directories = [
    $database_directory,
    $config['photo_directory'],
    $config['manual_directory'],
    $config['backup_directory'],
];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        if (!mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new RuntimeException(
                'ディレクトリを作成できません: '
                . $directory
            );
        }
    }

    if (!is_readable($directory)) {
        throw new RuntimeException(
            'ディレクトリを読み込めません: '
            . $directory
        );
    }

    if (!is_writable($directory)) {
        throw new RuntimeException(
            'ディレクトリに書き込めません: '
            . $directory
        );
    }
}

/*
 * SQLite接続
 */
try {
    $pdo = new PDO(
        'sqlite:' . $config['database_path'],
        null,
        null,
        [
            PDO::ATTR_ERRMODE =>
                PDO::ERRMODE_EXCEPTION,

            PDO::ATTR_DEFAULT_FETCH_MODE =>
                PDO::FETCH_ASSOC,

            PDO::ATTR_EMULATE_PREPARES =>
                false,
        ]
    );

    /*
     * SQLiteのロック待ち時間
     */
    $pdo->exec('PRAGMA busy_timeout = 5000');

    /*
     * 外部キー制約
     */
    $pdo->exec('PRAGMA foreign_keys = ON');

} catch (PDOException $e) {
    throw new RuntimeException(
        'データベース接続に失敗しました: '
        . $e->getMessage(),
        0,
        $e
    );
}
