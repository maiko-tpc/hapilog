<?php

/*
 * HTMLエスケープ
 */
function h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/*
 * 設定値の取得
 */
function config_value($key, $default = null) {
    global $config;

    if (!is_array($config)) {
        return $default;
    }

    if (!array_key_exists($key, $config)) {
        return $default;
    }

    return $config[$key];
}

/*
 * システムの正式名称
 */
function system_name() {
    return (string)config_value(
        'system_name',
        '装置管理システム'
    );
}

/*
 * システムの短い名称
 */
function system_short_name() {
    return (string)config_value(
        'system_short_name',
        '装置管理'
    );
}


/*
 * ファイルサイズ上限を読みやすく表示する。
 */
function format_file_size_limit($bytes) {
    $bytes = (int)$bytes;

    if ($bytes >= 1024 * 1024 && $bytes % (1024 * 1024) === 0) {
        return (string)($bytes / (1024 * 1024)) . 'MB';
    }

    if ($bytes >= 1024 && $bytes % 1024 === 0) {
        return (string)($bytes / 1024) . 'KB';
    }

    return (string)$bytes . 'B';
}
