<?php

/*
 * 装置管理システム 設定例
 *
 * 導入時は、このファイルを config.php にコピーして、
 * 各施設の環境に合わせて編集してください。
 *
 *   cp config.example.php config.php
 */

return [

    /*
     * 画面上部やブラウザタイトルに表示する正式名称
     */
    'system_name' => '○○大学 装置管理システム',

    /*
     * メニューや短い表示に使う名称
     */
    'system_short_name' => '装置管理',

    /*
     * PHPで使用するタイムゾーン
     *
     * 日本国内では通常 Asia/Tokyo のままでよい。
     */
    'timezone' => 'Asia/Tokyo',

    /*
     * SQLiteデータベースファイルの絶対パス
     *
     * Web公開ディレクトリの外に置くことを推奨する。
     */
    'database_path' =>
        '/path/to/equip_log_data/equipment.sqlite',

    /*
     * 写真ファイルの保存ディレクトリ
     *
     * Webサーバーの実行ユーザーが読み書きできる必要がある。
     */
    'photo_directory' =>
        '/path/to/equip_log_data/photos',

    /*
     * PDFマニュアルの保存ディレクトリ
     *
     * Webサーバーの実行ユーザーが読み書きできる必要がある。
     */
    'manual_directory' =>
        '/path/to/equip_log_data/manuals',

    /*
     * 写真1ファイルあたりの最大サイズ
     *
     * 単位はバイト。
     * 以下は10 MB。
     */
    'photo_max_size' => 10 * 1024 * 1024,

    /*
     * PDFマニュアル1ファイルあたりの最大サイズ
     *
     * 単位はバイト。
     * 以下は10 MB。
     */
    'manual_max_size' => 10 * 1024 * 1024,

];
