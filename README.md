# Equipment Log

PHPとSQLiteで動作する、研究室・大学・研究施設向けの軽量な装置管理システムです。

装置の基本情報、現在地、使用者、状態、移動履歴、写真、PDFマニュアルをブラウザ上で一元管理できます。簡易掲示板を利用して、一時的な物品移動や返却状況も記録できます。

## 主な機能

* 装置情報の登録・編集・検索
* 管理番号、カテゴリ、状態、現在地による絞り込み
* 管理番号順・更新日時順での並べ替え
* デフォルト置き場と現在地の管理
* 使用者・状態・移動履歴の記録
* 装置写真の登録
* 同型装置間での写真の共有
* PDFマニュアルの登録
* 同型装置間でのマニュアルの共有
* CSV形式での装置一覧出力
* 掲示板への投稿・編集
* 掲示板投稿の返却完了管理
* 装置数・状態別件数のサマリー表示
* 掲示板の投稿数・返却状況のサマリー表示
* SQLiteデータベースのバックアップ
* `Makefile` による環境確認、セットアップ、配布物作成

## 動作環境

以下の環境を想定しています。

* PHP 7.4以降
* PDO
* PDO SQLite
* SQLite 3
* Apache HTTP ServerなどのPHP対応Webサーバー
* GNU Make
* Unix系OS

  * Linux
  * macOSなど

JavaScriptフレームワークや外部データベースサーバーは不要です。

## ディレクトリ構成

代表的な構成は次のとおりです。

```text
equip_log/
├── Makefile
├── README.md
├── config.example.php
├── config.php
├── setup.php
├── schema.sql
├── db.php
├── functions.php
├── index.php
├── item.php
├── new_item.php
├── edit_item.php
├── move.php
├── board.php
├── style.css
└── その他のPHPファイル
```

装置データは、Web公開ディレクトリとは別の場所に保存することを推奨します。

```text
equip_log_data/
├── equipment.sqlite
├── photos/
├── manuals/
└── backups/
```

## インストール

### 1. ソースコードを配置する

配布アーカイブを展開し、Webサーバーから参照できる場所へ配置します。

```bash
tar xzf equip_log.tar.gz
cd equip_log
```

### 2. 設定ファイルを作成する

設定例をコピーします。

```bash
make config
```

または、直接コピーしても構いません。

```bash
cp config.example.php config.php
```

作成された `config.php` を、導入先の環境に合わせて編集します。

```php
<?php

return [
    'system_name' => '○○大学 装置管理システム',
    'system_short_name' => '装置管理',
    'timezone' => 'Asia/Tokyo',

    'database_path' =>
        '/path/to/equip_log_data/equipment.sqlite',

    'photo_directory' =>
        '/path/to/equip_log_data/photos',

    'manual_directory' =>
        '/path/to/equip_log_data/manuals',

    'photo_max_size' => 10 * 1024 * 1024,
    'manual_max_size' => 10 * 1024 * 1024,
];
```

各保存先には、Webサーバーの実行ユーザーが読み書きできる必要があります。

### 3. 環境を確認する

```bash
make all
```

`make` だけでも同じ処理を実行します。

```bash
make
```

この処理では以下を確認します。

* PHPコマンド
* PHPバージョン
* PDO拡張
* PDO SQLite拡張
* 必須ファイル
* `config.php` の設定
* PHPファイルの構文

`make` および `make all` は、データベースを変更しません。

### 4. データベースをセットアップする

```bash
make setup
```

この処理では以下を行います。

* データ保存ディレクトリの作成
* 写真保存ディレクトリの作成
* マニュアル保存ディレクトリの作成
* SQLiteデータベースの作成
* 必要なテーブルの作成
* 必要なインデックスの作成
* SQLite整合性チェック

既存のデータベースがある場合は、セットアップ前に自動的にバックアップを作成します。

既存テーブルや既存レコードは削除しません。

## Apache Basic認証

Basic認証を利用する場合は、導入先ごとに `.htaccess` を設定してください。

例：

```apache
AuthType Basic
AuthName "Equipment Log"
AuthUserFile /absolute/path/to/.equip_log_htpasswd
Require valid-user
```

パスワードファイルは、例えば次のように作成します。

```bash
htpasswd -c /absolute/path/to/.equip_log_htpasswd admin
```

`.htaccess` の `AuthUserFile` は、PHPの `config.php` からは参照できません。各施設のApache環境に合わせて直接設定してください。

## Makefileの使い方

### 環境確認と構文チェック

```bash
make
```

または、

```bash
make all
```

データベースは変更されません。

### ヘルプ表示

```bash
make help
```

### 環境確認のみ

```bash
make check
```

### PHP構文チェックのみ

```bash
make syntax
```

### 設定ファイルの作成

```bash
make config
```

既存の `config.php` は上書きしません。

### 初期セットアップ

```bash
make setup
```

既存DBがある場合は、先にバックアップを作成してからスキーマを確認します。

### データベースのバックアップ

```bash
make backup
```

バックアップは、データベースと同じ親ディレクトリにある `backups` ディレクトリへ保存されます。

例：

```text
equip_log_data/backups/
└── equipment.sqlite.20260614_123456.bak
```

このバックアップにはSQLiteデータベースのみが含まれます。写真とPDFマニュアルは含まれません。

### 配布用アーカイブの作成

```bash
make package
```

次のような配布用アーカイブが作成されます。

```text
dist/equip_log.tar.gz
```

配布物からは原則として以下が除外されます。

* `config.php`
* SQLiteデータベース
* SQLiteの一時ファイル
* 写真
* PDFマニュアル
* バックアップ
* エディタの一時ファイル
* 既存の配布アーカイブ

### 一時ファイルの削除

```bash
make clean
```

## データベースの安全性

`make setup` で使用する `schema.sql` は、再実行可能な構成になっています。

主に以下を使用します。

```sql
CREATE TABLE IF NOT EXISTS
CREATE INDEX IF NOT EXISTS
```

以下の処理は行いません。

```sql
DROP TABLE
DELETE
```

そのため、通常は `make setup` を複数回実行しても、既存の装置情報や履歴は保持されます。

ただし、重要な環境で更新作業を行う場合は、事前にバックアップを確認してください。

```bash
make backup
```

## 保存データ

### SQLiteデータベース

以下の情報を保存します。

* 装置基本情報
* 現在地
* 使用者
* 状態
* 移動履歴
* 写真の登録情報
* PDFマニュアルの登録情報
* 掲示板投稿
* 掲示板の編集日時
* 掲示板の返却完了日時

### 写真

写真ファイルの実体は、`config.php` の `photo_directory` に保存されます。

同じ写真を複数装置から参照できます。別の装置からも参照されている写真は、一方の登録を削除しても実ファイルを削除しない設計です。

### PDFマニュアル

PDFファイルの実体は、`config.php` の `manual_directory` に保存されます。

同じPDFを複数装置から参照できます。別の装置からも参照されているPDFは、一方の登録を削除しても実ファイルを削除しない設計です。

## バックアップ

SQLiteデータベースのバックアップは次のコマンドで作成できます。

```bash
make backup
```

写真とPDFマニュアルも含めて完全なバックアップを作成する場合は、データ保存ディレクトリ全体を別途バックアップしてください。

例：

```bash
tar czf equip_log_data_backup.tar.gz /path/to/equip_log_data
```

大容量の写真やPDFを扱う場合は、施設のバックアップポリシーに合わせて運用してください。

## アップロードサイズ

アプリケーション上の上限は `config.php` で設定します。

```php
'photo_max_size' => 10 * 1024 * 1024,
'manual_max_size' => 10 * 1024 * 1024,
```

PHP側の設定値も、これ以上である必要があります。

代表的な設定項目：

```ini
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
```

実際に利用される `php.ini` は、CLI版PHPとWebサーバー版PHPで異なる場合があります。

## 権限設定

データ保存ディレクトリには、Webサーバーの実行ユーザーが読み書きできる必要があります。

例：

```bash
chown -R www-data:www-data /path/to/equip_log_data
chmod -R 770 /path/to/equip_log_data
```

Webサーバーのユーザー名は環境によって異なります。

代表例：

* `www-data`
* `apache`
* `httpd`
* `www`

必要以上に広い権限を与えないでください。

## セキュリティ上の注意

* `equipment.sqlite` はWeb公開ディレクトリの外に置くことを推奨します。
* 写真とPDFマニュアルも、可能であればWeb公開ディレクトリの外に置いてください。
* Basic認証などでアクセス制限してください。
* `config.php` を公開リポジトリへ登録しないでください。
* 定期的にバックアップしてください。
* HTTPS環境での利用を推奨します。
* `.htaccess` が有効かどうかはApache設定に依存します。
* 本番環境ではPHPのエラー詳細を画面に表示しないことを推奨します。

## Gitで管理する場合

`.gitignore` には、少なくとも次を追加することを推奨します。

```gitignore
config.php

*.sqlite
*.sqlite-shm
*.sqlite-wal

dist/

*~
#*#
.#*
.DS_Store
```

データディレクトリがリポジトリ内にある場合は、写真、PDF、バックアップも除外してください。

```gitignore
photos/
manuals/
backups/
```

## トラブルシューティング

### PDO SQLiteが利用できない

次のようなエラーが表示される場合：

```text
PDO SQLite extension is not available.
```

PHPのSQLite拡張をインストールしてください。

Debian・Ubuntu系の例：

```bash
sudo apt install php-sqlite3
```

Red Hat・Rocky Linux・AlmaLinux系では、利用しているPHPパッケージ構成を確認してください。

### データディレクトリへ書き込めない

次のようなエラーが表示される場合：

```text
Directory is not writable
```

ディレクトリの所有者と権限を確認してください。

```bash
ls -ld /path/to/equip_log_data
```

Webサーバーの実行ユーザーから書き込める必要があります。

### SQLiteのロックエラー

SQLiteは同時に多数の書き込みを行う用途には向きません。

本システムではロック待ち時間を設定していますが、同時更新が多い環境では、

```text
database is locked
```

が発生する可能性があります。

数人から数十人程度が、主に閲覧し、時々登録・更新する運用を想定しています。

### CSSの変更が反映されない

ブラウザのキャッシュを削除するか、強制再読み込みしてください。

一般的な操作：

* Windows・Linux：`Ctrl + F5`
* macOS：`Command + Shift + R`

### Makefileで `missing separator` が出る

Makefileのコマンド行は、先頭をスペースではなくタブ文字にする必要があります。

```text
Makefile: missing separator
```

と表示された場合は、コマンド行のインデントを確認してください。

## 想定する利用規模

本システムは、小規模から中規模の研究室・施設内での利用を想定しています。

例：

* 装置数：数百から数千件程度
* 利用者：数人から数十人程度
* 更新頻度：閲覧中心で、時々登録・移動・編集
* 写真・PDF：装置詳細画面から個別に表示

装置数が200件程度であれば、SQLiteで十分軽量に動作します。

## ライセンス

公開時には、利用するライセンスをこの節へ記載してください。

例：

```text
This software is released under the MIT License.
```

MIT Licenseを採用する場合は、別途 `LICENSE` ファイルを追加してください。

## 開発方針

このシステムは、以下を重視して開発しています。

* 特別なデータベースサーバーを必要としない
* 古めのPHP環境でも動作する
* 小規模施設で保守しやすい
* 装置の所在と履歴が分かりやすい
* 写真やマニュアルを簡単に参照できる
* 過剰に複雑なUIや依存関係を導入しない
* 他施設へ移植しやすい
* 既存データを安全に保持する

## 今後の候補

今後追加を検討できる機能として、以下があります。

* CSVインポート
* 操作ログ
* 装置の無効化・復元
* ユーザー権限管理
* 貸出期限と期限超過表示
* QRコード付きラベル印刷
* データベースマイグレーション
* Web画面からのバックアップ管理
* 多言語対応
