# HapiLog (Hardware and Apparatus Property Inventory Log)

**HapiLog** は、研究室・大学・研究施設向けの軽量な装置管理システムです。

PHP と SQLite で動作し、専用のデータベースサーバーを必要としません。
装置情報、所在、使用者、状態、移動履歴、写真、PDFマニュアル、簡易掲示板をブラウザ上で管理できます。

## 主な機能

* 装置情報の登録・編集
* キーワード検索
* カテゴリ・状態・現在地による絞り込み
* 管理番号順・更新日時順の並べ替え
* 現在地・使用者・状態の管理
* 移動履歴の記録
* 装置写真の登録
* 同型装置間での写真の共有
* PDFマニュアルの登録
* 同型装置間でのPDFマニュアルの共有
* 装置一覧のCSV出力
* 一時的な移動・貸出・返却確認用の掲示板
* 掲示板投稿の返却完了管理
* トップページでの装置数サマリー表示
* 掲示板での投稿数・返却状況・最終更新日時表示
* SQLiteデータベースのバックアップ
* `make setup` による初期セットアップ
* `make package` による配布用アーカイブ作成

## スクリーンショット

公開時には、ここにスクリーンショットを追加してください。

例：

```text
docs/
├── screenshot_list.png
├── screenshot_item.png
└── screenshot_board.png
```

Markdownで表示する例：

```markdown
![装置一覧](docs/screenshot_list.png)
```

## 動作環境

以下の環境を想定しています。

* PHP 7.4以降
* PDO
* PDO SQLite
* SQLite 3
* Apache HTTP Server などのPHP対応Webサーバー
* GNU Make
* Unix系OS

  * Linux
  * macOS
  * BSD系OSなど

JavaScriptフレームワークや外部データベースサーバーは不要です。

## 推奨ディレクトリ構成

アプリ本体は、Webサーバーから参照できる場所に配置します。

```text
hapilog/
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

実データは、可能であればWeb公開ディレクトリの外に置くことを推奨します。

```text
hapilog_data/
├── hapilog.sqlite
├── photos/
├── manuals/
└── backups/
```

このように、アプリ本体と実データを分けておくと、セキュリティと移植性の面で扱いやすくなります。

## インストール

### 1. HapiLogを配置する

Gitを使う場合：

```bash
git clone https://github.com/YOUR_NAME/hapilog.git
cd hapilog
```

配布アーカイブを使う場合：

```bash
tar xzf hapilog.tar.gz
cd hapilog
```

### 2. `config.php` を作成する

設定例をコピーします。

```bash
make config
```

または手動でコピーします。

```bash
cp config.example.php config.php
```

作成された `config.php` を、導入先の環境に合わせて編集します。

例：

```php
<?php

return [
    'system_name' => 'HapiLog',
    'system_short_name' => 'HapiLog',
    'timezone' => 'Asia/Tokyo',

    'database_path' =>
        '/path/to/hapilog_data/hapilog.sqlite',

    'photo_directory' =>
        '/path/to/hapilog_data/photos',

    'manual_directory' =>
        '/path/to/hapilog_data/manuals',

    'photo_max_size' => 10 * 1024 * 1024,
    'manual_max_size' => 10 * 1024 * 1024,
];
```

データ保存先のディレクトリは、Webサーバーの実行ユーザーから読み書きできる必要があります。

### 3. 環境を確認する

```bash
make
```

または、

```bash
make all
```

このコマンドでは、以下を確認します。

* PHPコマンド
* PHPバージョン
* PDO拡張
* PDO SQLite拡張
* 必須ファイル
* 設定ファイル
* PHPファイルの構文

`make` および `make all` は、データベースを変更しません。

### 4. セットアップを実行する

```bash
make setup
```

この処理では、保存ディレクトリの作成とSQLiteデータベース構造の初期化を行います。

既存のデータベースがある場合は、スキーマ適用前に自動でバックアップを作成します。

既存レコードは削除しません。

## 基本的な使い方

ブラウザで HapiLog のURLを開きます。

典型的な流れは次のとおりです。

1. **新規登録** から装置を登録する
2. 管理番号、名称、型番、カテゴリ、メーカー、置き場などを入力する
3. 必要に応じて写真やPDFマニュアルを登録する
4. **移動登録** で現在地、使用者、状態を更新する
5. 一時的な貸出や返却確認には掲示板を使う
6. 必要に応じてCSVで装置一覧を出力する

## 設定ファイル

施設ごとの設定は `config.php` に書きます。

`config.php` は公開リポジトリに含めないでください。
代わりに `config.example.php` をテンプレートとして管理します。

主な設定項目：

```php
'system_name' => 'HapiLog',
'system_short_name' => 'HapiLog',
'timezone' => 'Asia/Tokyo',
'database_path' => '/path/to/hapilog_data/hapilog.sqlite',
'photo_directory' => '/path/to/hapilog_data/photos',
'manual_directory' => '/path/to/hapilog_data/manuals',
'photo_max_size' => 10 * 1024 * 1024,
'manual_max_size' => 10 * 1024 * 1024,
```

## Apache Basic認証

研究室内で利用する場合は、Basic認証やIP制限などでアクセス制限することを推奨します。

`.htaccess` の例：

```apache
AuthType Basic
AuthName "HapiLog"
AuthUserFile /absolute/path/to/.hapilog_htpasswd
Require valid-user

<FilesMatch "^(README\.md|Makefile|schema\.sql|config\.example\.php|setup\.php)$">
    Require all denied
</FilesMatch>
```

パスワードファイルは、例えば次のように作成します。

```bash
htpasswd -c /absolute/path/to/.hapilog_htpasswd admin
```

実際の `.htaccess` にはサーバー固有のパスが含まれるため、公開リポジトリには含めないことを推奨します。

必要であれば `.htaccess.example` を用意してください。

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

既存の `config.php` は上書きされません。

### 初期セットアップ

```bash
make setup
```

既存データは削除されません。

### SQLiteデータベースのバックアップ

```bash
make backup
```

バックアップは、SQLiteデータベースと同じ親ディレクトリ内の `backups` に保存されます。

例：

```text
hapilog_data/backups/
└── hapilog.sqlite.20260614_123456.bak
```

このバックアップには写真やPDFマニュアルの実ファイルは含まれません。

### 配布用アーカイブの作成

```bash
make package
```

`dist/` 以下に配布用アーカイブが作成されます。

例：

```text
dist/hapilog.tar.gz
```

配布アーカイブには、原則として以下を含めません。

* `config.php`
* `.htaccess`
* SQLiteデータベース
* 写真ファイル
* PDFマニュアル
* バックアップファイル
* エディタの一時ファイル

### 一時ファイルの削除

```bash
make clean
```

## データベースの安全性

HapiLog の `schema.sql` は、再実行しても既存データを削除しない構成を想定しています。

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

ただし、本番環境を更新する場合は、事前にバックアップを確認してください。

```bash
make backup
```

## 保存されるデータ

### SQLiteデータベース

SQLiteデータベースには、以下の情報が保存されます。

* 装置情報
* 管理番号
* カテゴリ
* メーカー
* 型番
* シリアル番号
* 現在地
* 使用者
* 状態
* 備考
* 移動履歴
* 写真の登録情報
* PDFマニュアルの登録情報
* 掲示板投稿
* 返却状態
* 編集日時

### 写真

写真ファイルの実体は、`photo_directory` で指定したディレクトリに保存されます。

同じ写真ファイルを複数の装置から参照できます。

### PDFマニュアル

PDFマニュアルの実体は、`manual_directory` で指定したディレクトリに保存されます。

同じPDFファイルを複数の装置から参照できます。

## バックアップ

SQLiteデータベースのみをバックアップする場合：

```bash
make backup
```

写真やPDFマニュアルも含めて完全にバックアップする場合は、データ保存ディレクトリ全体をバックアップしてください。

例：

```bash
tar czf hapilog_data_backup.tar.gz /path/to/hapilog_data
```

実際の運用では、各施設のバックアップ方針に合わせてください。

## アップロードサイズ

アプリケーション側のアップロード上限は `config.php` で設定します。

```php
'photo_max_size' => 10 * 1024 * 1024,
'manual_max_size' => 10 * 1024 * 1024,
```

PHP側にもアップロード制限があります。

`php.ini` の設定例：

```ini
upload_max_filesize = 128M
post_max_size = 128M
memory_limit = 256M
file_uploads = On
max_file_uploads = 20
```

CLI版PHPとWebサーバー版PHPでは、参照される `php.ini` が異なる場合があります。

## パーミッション

データ保存ディレクトリは、Webサーバーの実行ユーザーから読み書きできる必要があります。

例：

```bash
chown -R www-data:www-data /path/to/hapilog_data
chmod -R 770 /path/to/hapilog_data
```

Webサーバーの実行ユーザー名は環境によって異なります。

代表例：

* `www-data`
* `apache`
* `httpd`
* `www`

必要以上に広い権限を与えないようにしてください。

## セキュリティ上の注意

* `config.php` を公開リポジトリに含めないでください。
* サーバー固有の `.htaccess` を公開リポジトリに含めないでください。
* SQLiteデータベースはWeb公開ディレクトリの外に置くことを推奨します。
* 写真やPDFマニュアルも、可能であればWeb公開ディレクトリの外に置いてください。
* Basic認証、IP制限、VPNなどでアクセス制限してください。
* 本番環境ではHTTPSを利用してください。
* データベースとアップロードファイルを定期的にバックアップしてください。
* 本番環境ではPHPの詳細なエラーを画面に表示しないことを推奨します。
* 多人数で利用する場合は、アップロードされるファイルの扱いに注意してください。

## `.gitignore`

典型的な `.gitignore` は次のようになります。

```gitignore
# Local configuration
config.php
.htaccess

# SQLite database files
*.sqlite
*.sqlite3
*.db
*.db3
*.sqlite-shm
*.sqlite-wal
*.sqlite-journal

# Data directories
hapilog_data/
equip_log_data/
log_data/
data/
photos/
manuals/
backup/
backups/
uploads/

# Distribution files
dist/
*.tar.gz
*.tgz
*.zip

# Editor and backup files
*~
#*#
.#*
*.bak
*.backup
*.backup2
*.orig
*.swp
*.swo

# OS files
.DS_Store
Thumbs.db
```

## 想定する利用規模

HapiLog は、小規模から中規模の研究室・研究施設での利用を想定しています。

例：

* 装置数：数百〜数千件程度
* 利用者：数人〜数十人程度
* 利用形態：閲覧中心
* 更新頻度：時々登録・移動・編集
* 写真やPDFマニュアルを装置ごとに添付

この程度の規模であれば、SQLiteで十分軽量に動作します。

## 開発方針

HapiLog は、以下を重視して開発しています。

* シンプルであること
* 軽量であること
* 導入しやすいこと
* 別サーバーへ移植しやすいこと
* 古めのPHP環境でも動作すること
* 外部データベースサーバーを必要としないこと
* 研究室の装置管理に実用的であること
* 既存データを安全に保持できること
* 専門外の管理者にも理解しやすいこと
* 不要な依存関係や複雑なフレームワークを避けること


## 名前について
研究室の装置管理を、少しでも楽に、気持ちよく行えるようにするためのシステムです。
