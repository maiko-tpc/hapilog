-- ============================================================
-- 装置管理システム データベーススキーマ
--
-- このSQLは再実行可能である。
-- 既存テーブルや既存データを削除しない。
-- ============================================================

PRAGMA foreign_keys = ON;


-- ------------------------------------------------------------
-- 装置基本情報
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS items (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    asset_tag TEXT NOT NULL UNIQUE,
    model TEXT NOT NULL DEFAULT '',
    name TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT '',
    manufacturer TEXT NOT NULL DEFAULT '',
    property_number TEXT NOT NULL DEFAULT '',

    default_location TEXT NOT NULL DEFAULT '',
    purchase_date TEXT NOT NULL DEFAULT '',

    manual_url TEXT NOT NULL DEFAULT '',
    serial_number TEXT NOT NULL DEFAULT '',
    note TEXT NOT NULL DEFAULT '',

    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);


-- ------------------------------------------------------------
-- 装置の移動・状態履歴
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS movements (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    item_id INTEGER NOT NULL,

    location TEXT NOT NULL DEFAULT '',
    user_name TEXT NOT NULL DEFAULT '',
    status TEXT NOT NULL DEFAULT '',
    memo TEXT NOT NULL DEFAULT '',

    moved_at TEXT NOT NULL,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- 装置写真
--
-- stored_name が同じレコードを複数装置から参照できる。
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS item_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    item_id INTEGER NOT NULL,

    original_name TEXT NOT NULL DEFAULT '',
    stored_name TEXT NOT NULL,
    mime_type TEXT NOT NULL DEFAULT '',
    file_size INTEGER NOT NULL DEFAULT 0,
    caption TEXT NOT NULL DEFAULT '',

    uploaded_at TEXT NOT NULL,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- PDFマニュアル
--
-- stored_name が同じレコードを複数装置から参照できる。
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS item_manuals (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    item_id INTEGER NOT NULL,

    original_name TEXT NOT NULL DEFAULT '',
    stored_name TEXT NOT NULL,
    mime_type TEXT NOT NULL DEFAULT 'application/pdf',
    file_size INTEGER NOT NULL DEFAULT 0,
    title TEXT NOT NULL DEFAULT '',

    uploaded_at TEXT NOT NULL,

    FOREIGN KEY (item_id)
        REFERENCES items(id)
        ON DELETE CASCADE
);


-- ------------------------------------------------------------
-- 掲示板
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS board_posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,

    author TEXT NOT NULL DEFAULT '',
    title TEXT NOT NULL DEFAULT '',
    body TEXT NOT NULL,
    location TEXT NOT NULL DEFAULT '',

    created_at TEXT NOT NULL,
    edited_at TEXT,
    returned_at TEXT
);


-- ------------------------------------------------------------
-- インデックス
-- ------------------------------------------------------------

CREATE INDEX IF NOT EXISTS idx_items_asset_tag
    ON items(asset_tag);

CREATE INDEX IF NOT EXISTS idx_items_category
    ON items(category);

CREATE INDEX IF NOT EXISTS idx_items_default_location
    ON items(default_location);

CREATE INDEX IF NOT EXISTS idx_movements_item_time
    ON movements(item_id, moved_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_movements_status
    ON movements(status);

CREATE INDEX IF NOT EXISTS idx_movements_location
    ON movements(location);

CREATE INDEX IF NOT EXISTS idx_item_photos_item
    ON item_photos(item_id);

CREATE INDEX IF NOT EXISTS idx_item_photos_stored_name
    ON item_photos(stored_name);

CREATE INDEX IF NOT EXISTS idx_item_manuals_item
    ON item_manuals(item_id);

CREATE INDEX IF NOT EXISTS idx_item_manuals_stored_name
    ON item_manuals(stored_name);

CREATE INDEX IF NOT EXISTS idx_board_posts_created
    ON board_posts(created_at DESC, id DESC);

CREATE INDEX IF NOT EXISTS idx_board_posts_returned
    ON board_posts(returned_at);
