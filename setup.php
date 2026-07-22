<?php

/*
 * 装置管理システム CLIセットアップ
 *
 * 使用方法:
 *
 *   php setup.php
 *       ディレクトリとDB構造を準備する。
 *
 *   php setup.php --check
 *       環境と設定を確認するだけ。
 *       ファイルやDBを変更しない。
 *
 *   php setup.php --backup
 *       SQLite DBのバックアップだけを作成する。
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script can only be run from the command line.\n");
}

const MINIMUM_PHP_VERSION = '7.4.0';

$configFile = __DIR__ . '/config.php';
$schemaFile = __DIR__ . '/schema.sql';

$arguments = $argv;
array_shift($arguments);

$mode = 'setup';

if (in_array('--check', $arguments, true)) {
    $mode = 'check';
}

if (in_array('--backup', $arguments, true)) {
    $mode = 'backup';
}


/*
 * 表示用関数
 */
function print_ok($message)
{
    echo '[OK] ' . $message . PHP_EOL;
}

function print_check($message)
{
    echo '[CHECK] ' . $message . PHP_EOL;
}

function print_notice($message)
{
    echo '[NOTICE] ' . $message . PHP_EOL;
}

function fail($message, $exitCode = 1)
{
    fwrite(
        STDERR,
        '[ERROR] ' . $message . PHP_EOL
    );

    exit($exitCode);
}


/*
 * 設定値が空文字でないことを確認する。
 */
function require_non_empty_string(array $config, $key)
{
    if (!array_key_exists($key, $config)) {
        fail('Missing config key: ' . $key);
    }

    if (!is_string($config[$key])) {
        fail('Config value must be a string: ' . $key);
    }

    $value = trim($config[$key]);

    if ($value === '') {
        fail('Config value must not be empty: ' . $key);
    }

    return $value;
}


/*
 * 正の整数設定を確認する。
 */
function require_positive_integer(array $config, $key)
{
    if (!array_key_exists($key, $config)) {
        fail('Missing config key: ' . $key);
    }

    $value = filter_var(
        $config[$key],
        FILTER_VALIDATE_INT
    );

    if ($value === false || $value <= 0) {
        fail(
            'Config value must be a positive integer: '
            . $key
        );
    }

    return (int)$value;
}


/*
 * ディレクトリを作成し、読み書き可能か確認する。
 */
function prepare_directory($directory)
{
    if (is_dir($directory)) {
        print_ok('Directory exists: ' . $directory);
    } else {
        print_check('Creating directory: ' . $directory);

        if (
            !mkdir($directory, 0770, true)
            && !is_dir($directory)
        ) {
            fail(
                'Failed to create directory: '
                . $directory
            );
        }

        print_ok('Directory created: ' . $directory);
    }

    if (!is_readable($directory)) {
        fail(
            'Directory is not readable: '
            . $directory
        );
    }

    if (!is_writable($directory)) {
        fail(
            'Directory is not writable: '
            . $directory
        );
    }

    print_ok('Directory is readable and writable: ' . $directory);
}


/*
 * 既存ディレクトリの状態だけを確認する。
 * --check では新規作成しない。
 */
function check_directory_without_modifying($directory)
{
    if (!is_dir($directory)) {
        print_notice(
            'Directory does not exist yet: '
            . $directory
        );

        return;
    }

    print_ok('Directory exists: ' . $directory);

    if (!is_readable($directory)) {
        fail(
            'Directory is not readable: '
            . $directory
        );
    }

    if (!is_writable($directory)) {
        fail(
            'Directory is not writable: '
            . $directory
        );
    }

    print_ok('Directory is readable and writable: ' . $directory);
}


/*
 * DBバックアップ先。
 *
 * DBファイルと同じ親ディレクトリ内の backups/ を使う。
 */
function get_backup_directory($databasePath)
{
    return dirname($databasePath)
        . DIRECTORY_SEPARATOR
        . 'backups';
}


/*
 * SQLite DBをバックアップする。
 */
function create_database_backup($databasePath)
{
    if (!is_file($databasePath)) {
        print_notice(
            'Database does not exist. Backup was not required.'
        );

        return null;
    }

    if (!is_readable($databasePath)) {
        fail(
            'Database is not readable: '
            . $databasePath
        );
    }

    $backupDirectory =
        get_backup_directory($databasePath);

    prepare_directory($backupDirectory);

    $databaseBaseName =
        basename($databasePath);

    $backupPath =
        $backupDirectory
        . DIRECTORY_SEPARATOR
        . $databaseBaseName
        . '.'
        . date('Ymd_His')
        . '.bak';

    print_check(
        'Creating database backup: '
        . $backupPath
    );

    if (!copy($databasePath, $backupPath)) {
        fail(
            'Failed to create database backup: '
            . $backupPath
        );
    }

    @chmod($backupPath, 0660);

    if (!is_file($backupPath)) {
        fail(
            'Backup file was not created: '
            . $backupPath
        );
    }

    $sourceSize = filesize($databasePath);
    $backupSize = filesize($backupPath);

    if (
        $sourceSize === false
        || $backupSize === false
        || $sourceSize !== $backupSize
    ) {
        @unlink($backupPath);

        fail(
            'Backup size verification failed.'
        );
    }

    print_ok(
        'Database backup created: '
        . $backupPath
    );

    return $backupPath;
}


/*
 * DBテーブルの存在確認。
 */
function get_existing_tables(PDO $pdo)
{
    $stmt = $pdo->query("
        SELECT name
        FROM sqlite_master
        WHERE type = 'table'
          AND name NOT LIKE 'sqlite_%'
        ORDER BY name
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}


/*
 * 必須テーブルを確認する。
 */
function verify_required_tables(PDO $pdo)
{
    $requiredTables = [
        'items',
        'movements',
        'item_photos',
        'item_manuals',
        'board_posts',
    ];

    $existingTables =
        get_existing_tables($pdo);

    foreach ($requiredTables as $table) {
        if (!in_array($table, $existingTables, true)) {
            fail(
                'Required table was not created: '
                . $table
            );
        }

        print_ok('Table is available: ' . $table);
    }
}


/*
 * PHP環境確認
 */
print_check('PHP version');

if (
    version_compare(
        PHP_VERSION,
        MINIMUM_PHP_VERSION,
        '<'
    )
) {
    fail(
        'PHP '
        . MINIMUM_PHP_VERSION
        . ' or later is required. Current: '
        . PHP_VERSION
    );
}

print_ok('PHP version: ' . PHP_VERSION);


print_check('PDO extension');

if (!extension_loaded('pdo')) {
    fail('PDO extension is not available.');
}

print_ok('PDO extension is available.');


print_check('PDO SQLite extension');

if (!extension_loaded('pdo_sqlite')) {
    fail('PDO SQLite extension is not available.');
}

print_ok('PDO SQLite extension is available.');


/*
 * config.php 読み込み
 */
print_check('Configuration file');

if (!is_file($configFile)) {
    fail(
        'Configuration file not found: '
        . $configFile
    );
}

$config = require $configFile;

if (!is_array($config)) {
    fail('config.php must return an array.');
}

print_ok('Configuration file loaded: ' . $configFile);


/*
 * 設定値の検証
 */
$systemName =
    require_non_empty_string(
        $config,
        'system_name'
    );

$systemShortName =
    require_non_empty_string(
        $config,
        'system_short_name'
    );

$timezone =
    require_non_empty_string(
        $config,
        'timezone'
    );

$databasePath =
    require_non_empty_string(
        $config,
        'database_path'
    );

$photoDirectory =
    require_non_empty_string(
        $config,
        'photo_directory'
    );

$manualDirectory =
    require_non_empty_string(
        $config,
        'manual_directory'
    );

$photoMaxSize =
    require_positive_integer(
        $config,
        'photo_max_size'
    );

$manualMaxSize =
    require_positive_integer(
        $config,
        'manual_max_size'
    );


if (!date_default_timezone_set($timezone)) {
    fail(
        'Invalid timezone: '
        . $timezone
    );
}

print_ok('System name: ' . $systemName);
print_ok('System short name: ' . $systemShortName);
print_ok('Timezone: ' . $timezone);
print_ok('Database path: ' . $databasePath);
print_ok('Photo directory: ' . $photoDirectory);
print_ok('Manual directory: ' . $manualDirectory);
print_ok('Photo maximum size: ' . $photoMaxSize . ' bytes');
print_ok('Manual maximum size: ' . $manualMaxSize . ' bytes');


$databaseDirectory =
    dirname($databasePath);


/*
 * --check
 *
 * ディレクトリやDBを新規作成しない。
 */
if ($mode === 'check') {
    echo PHP_EOL;
    echo 'Configuration and environment check' . PHP_EOL;
    echo '-----------------------------------' . PHP_EOL;

    check_directory_without_modifying(
        $databaseDirectory
    );

    check_directory_without_modifying(
        $photoDirectory
    );

    check_directory_without_modifying(
        $manualDirectory
    );

    if (is_file($databasePath)) {
        print_ok(
            'Database file exists: '
            . $databasePath
        );

        if (!is_readable($databasePath)) {
            fail(
                'Database is not readable: '
                . $databasePath
            );
        }

        if (!is_writable($databasePath)) {
            fail(
                'Database is not writable: '
                . $databasePath
            );
        }

        try {
            $pdo = new PDO(
                'sqlite:' . $databasePath,
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

            $pdo->exec('PRAGMA foreign_keys = ON');
            $pdo->exec('PRAGMA busy_timeout = 5000');

            print_ok('Database connection succeeded.');

            $tables = get_existing_tables($pdo);

            if (count($tables) === 0) {
                print_notice(
                    'Database contains no application tables.'
                );
            } else {
                print_ok(
                    'Database tables: '
                    . implode(', ', $tables)
                );
            }

        } catch (Throwable $e) {
            fail(
                'Database connection failed: '
                . $e->getMessage()
            );
        }

    } else {
        print_notice(
            'Database does not exist yet: '
            . $databasePath
        );
    }

    echo PHP_EOL;
    print_ok('Check completed. No files were modified.');

    exit(0);
}


/*
 * --backup
 */
if ($mode === 'backup') {
    echo PHP_EOL;
    echo 'Database backup' . PHP_EOL;
    echo '---------------' . PHP_EOL;

    create_database_backup($databasePath);

    echo PHP_EOL;
    print_ok('Backup command completed.');

    exit(0);
}


/*
 * 通常セットアップ
 */
echo PHP_EOL;
echo 'Setup' . PHP_EOL;
echo '-----' . PHP_EOL;


/*
 * 保存ディレクトリの準備
 */
prepare_directory($databaseDirectory);
prepare_directory($photoDirectory);
prepare_directory($manualDirectory);


/*
 * schema.sql 確認
 */
print_check('Schema file');

if (!is_file($schemaFile)) {
    fail(
        'Schema file not found: '
        . $schemaFile
    );
}

$schemaSql = file_get_contents($schemaFile);

if ($schemaSql === false) {
    fail(
        'Failed to read schema file: '
        . $schemaFile
    );
}

if (trim($schemaSql) === '') {
    fail('Schema file is empty.');
}

print_ok('Schema file loaded: ' . $schemaFile);


/*
 * 既存DBのバックアップ
 */
if (is_file($databasePath)) {
    create_database_backup($databasePath);
} else {
    print_notice(
        'A new database will be created.'
    );
}


/*
 * SQLite接続
 */
try {
    $pdo = new PDO(
        'sqlite:' . $databasePath,
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

    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    print_ok('Database connection succeeded.');

} catch (Throwable $e) {
    fail(
        'Database connection failed: '
        . $e->getMessage()
    );
}


/*
 * スキーマ適用
 *
 * schema.sql は CREATE ... IF NOT EXISTS のみを使用する。
 * トランザクション内で実行する。
 */
try {
    print_check('Applying database schema');

    $pdo->beginTransaction();

    $pdo->exec($schemaSql);

    $pdo->commit();

    print_ok('Database schema applied.');

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fail(
        'Failed to apply database schema: '
        . $e->getMessage()
    );
}


/*
 * 必須テーブル確認
 */
print_check('Required database tables');

verify_required_tables($pdo);


/*
 * DBファイルの権限調整
 */
if (is_file($databasePath)) {
    @chmod($databasePath, 0660);
}


/*
 * SQLite整合性チェック
 */
print_check('SQLite integrity check');

$integrityResult =
    $pdo->query('PRAGMA integrity_check')
        ->fetchColumn();

if ($integrityResult !== 'ok') {
    fail(
        'SQLite integrity check failed: '
        . (string)$integrityResult
    );
}

print_ok('SQLite integrity check: ok');


echo PHP_EOL;
echo 'Setup completed successfully.' . PHP_EOL;
echo PHP_EOL;
echo 'Database: ' . $databasePath . PHP_EOL;
echo 'Photos:   ' . $photoDirectory . PHP_EOL;
echo 'Manuals:  ' . $manualDirectory . PHP_EOL;
