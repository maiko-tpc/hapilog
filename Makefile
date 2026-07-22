# ============================================================
# 装置管理システム Makefile
#
# 主な使い方:
#
#   make
#   make all
#       環境確認とPHP構文チェックのみ。
#       データベースは変更しない。
#
#   make setup
#       保存ディレクトリとデータベース構造を準備する。
#       既存データは削除しない。
#
#   make backup
#       SQLiteデータベースのバックアップを作成する。
#
#   make package
#       公開・配布用アーカイブを作成する。
# ============================================================

PHP ?= php

APP_NAME ?= equip_log
DIST_DIR ?= dist
PACKAGE_NAME ?= $(APP_NAME).tar.gz

CONFIG_FILE := config.php
CONFIG_EXAMPLE := config.example.php
SETUP_SCRIPT := setup.php
SCHEMA_FILE := schema.sql


.PHONY: \
	all \
	help \
	check \
	syntax \
	config \
	setup \
	backup \
	package \
	clean


# ------------------------------------------------------------
# デフォルトターゲット
#
# make または make all では、
# 環境確認とPHP構文チェックだけを行う。
# DBやデータファイルは変更しない。
# ------------------------------------------------------------

all: check syntax
	@echo
	@echo "All checks completed successfully."
	@echo "The database was not modified."


# ------------------------------------------------------------
# ヘルプ
# ------------------------------------------------------------

help:
	@echo "Available commands:"
	@echo
	@echo "  make"
	@echo "  make all"
	@echo "      Check the environment and PHP syntax."
	@echo "      The database is not modified."
	@echo
	@echo "  make check"
	@echo "      Check PHP, PDO SQLite, configuration, and paths."
	@echo "      The database is not modified."
	@echo
	@echo "  make syntax"
	@echo "      Check the syntax of all PHP files."
	@echo
	@echo "  make config"
	@echo "      Create config.php from config.example.php."
	@echo "      Existing config.php is never overwritten."
	@echo
	@echo "  make setup"
	@echo "      Create data directories and initialize DB structure."
	@echo "      Existing records are not deleted."
	@echo "      An existing DB is backed up before setup."
	@echo
	@echo "  make backup"
	@echo "      Create a backup of the SQLite database."
	@echo
	@echo "  make package"
	@echo "      Create a distribution archive in $(DIST_DIR)/."
	@echo
	@echo "  make clean"
	@echo "      Remove temporary and generated distribution files."


# ------------------------------------------------------------
# 環境確認
#
# setup.php --check はファイルを変更しない。
# ------------------------------------------------------------

check:
	@echo "[CHECK] PHP command"
	@command -v "$(PHP)" >/dev/null 2>&1 || { \
		echo "[ERROR] PHP command not found: $(PHP)"; \
		exit 1; \
	}
	@echo "[OK] PHP command: $$(command -v "$(PHP)")"

	@echo "[CHECK] PHP version"
	@$(PHP) -r 'if (version_compare(PHP_VERSION, "7.4.0", "<")) { fwrite(STDERR, "[ERROR] PHP 7.4 or later is required. Current: " . PHP_VERSION . PHP_EOL); exit(1); } echo "[OK] PHP version: " . PHP_VERSION . PHP_EOL;'

	@echo "[CHECK] PDO extension"
	@$(PHP) -r 'if (!extension_loaded("pdo")) { fwrite(STDERR, "[ERROR] PDO extension is not available." . PHP_EOL); exit(1); } echo "[OK] PDO extension is available." . PHP_EOL;'

	@echo "[CHECK] PDO SQLite extension"
	@$(PHP) -r 'if (!extension_loaded("pdo_sqlite")) { fwrite(STDERR, "[ERROR] PDO SQLite extension is not available." . PHP_EOL); exit(1); } echo "[OK] PDO SQLite extension is available." . PHP_EOL;'

	@echo "[CHECK] Required application files"
	@test -f db.php || { \
		echo "[ERROR] db.php not found."; \
		exit 1; \
	}
	@test -f functions.php || { \
		echo "[ERROR] functions.php not found."; \
		exit 1; \
	}
	@test -f index.php || { \
		echo "[ERROR] index.php not found."; \
		exit 1; \
	}
	@test -f style.css || { \
		echo "[ERROR] style.css not found."; \
		exit 1; \
	}
	@test -f "$(SETUP_SCRIPT)" || { \
		echo "[ERROR] $(SETUP_SCRIPT) not found."; \
		exit 1; \
	}
	@test -f "$(SCHEMA_FILE)" || { \
		echo "[ERROR] $(SCHEMA_FILE) not found."; \
		exit 1; \
	}
	@echo "[OK] Required application files are present."

	@if [ -f "$(CONFIG_FILE)" ]; then \
		echo "[OK] $(CONFIG_FILE) is present."; \
	elif [ -f "$(CONFIG_EXAMPLE)" ]; then \
		echo "[NOTICE] $(CONFIG_FILE) is not present."; \
		echo "         Run 'make config' to create it."; \
		exit 1; \
	else \
		echo "[ERROR] Neither $(CONFIG_FILE) nor $(CONFIG_EXAMPLE) exists."; \
		exit 1; \
	fi

	@$(PHP) "$(SETUP_SCRIPT)" --check


# ------------------------------------------------------------
# PHP構文チェック
# ------------------------------------------------------------

syntax:
	@echo "[CHECK] PHP syntax"
	@set -e; \
	for file in $$(find . -maxdepth 1 -type f -name '*.php' | sort); do \
		$(PHP) -l "$$file"; \
	done
	@echo "[OK] PHP syntax check completed."


# ------------------------------------------------------------
# config.php の作成
#
# 既存の config.php は上書きしない。
# ------------------------------------------------------------

config:
	@if [ -f "$(CONFIG_FILE)" ]; then \
		echo "[NOTICE] $(CONFIG_FILE) already exists."; \
		echo "         It was not overwritten."; \
	elif [ ! -f "$(CONFIG_EXAMPLE)" ]; then \
		echo "[ERROR] $(CONFIG_EXAMPLE) not found."; \
		exit 1; \
	else \
		cp "$(CONFIG_EXAMPLE)" "$(CONFIG_FILE)"; \
		echo "[OK] Created $(CONFIG_FILE) from $(CONFIG_EXAMPLE)."; \
		echo "[NOTICE] Edit $(CONFIG_FILE) before running setup."; \
	fi


# ------------------------------------------------------------
# セットアップ
#
# 既存DBは削除しない。
# 既存DBがある場合、処理前にバックアップする。
# ------------------------------------------------------------

setup:
	@if [ ! -f "$(CONFIG_FILE)" ]; then \
		echo "[ERROR] $(CONFIG_FILE) not found."; \
		echo "        Run 'make config' and edit it first."; \
		exit 1; \
	fi

	@if [ ! -f "$(SETUP_SCRIPT)" ]; then \
		echo "[ERROR] $(SETUP_SCRIPT) not found."; \
		exit 1; \
	fi

	@if [ ! -f "$(SCHEMA_FILE)" ]; then \
		echo "[ERROR] $(SCHEMA_FILE) not found."; \
		exit 1; \
	fi

	@echo "[SETUP] Running $(SETUP_SCRIPT)"
	@$(PHP) "$(SETUP_SCRIPT)"


# ------------------------------------------------------------
# バックアップ
#
# データベースのみをバックアップする。
# 写真・PDFは含まない。
# ------------------------------------------------------------

backup:
	@if [ ! -f "$(CONFIG_FILE)" ]; then \
		echo "[ERROR] $(CONFIG_FILE) not found."; \
		exit 1; \
	fi

	@if [ ! -f "$(SETUP_SCRIPT)" ]; then \
		echo "[ERROR] $(SETUP_SCRIPT) not found."; \
		exit 1; \
	fi

	@$(PHP) "$(SETUP_SCRIPT)" --backup


# ------------------------------------------------------------
# 配布用アーカイブ作成
#
# 除外対象:
#   config.php
#   SQLite DB
#   写真・マニュアルなどの実データ
#   バックアップ
#   エディタ一時ファイル
#   Git管理情報
# ------------------------------------------------------------

package: all
	@echo "[PACKAGE] Creating distribution archive"

	@rm -rf "$(DIST_DIR)/$(APP_NAME)"
	@mkdir -p "$(DIST_DIR)/$(APP_NAME)"

	@find . \
		-maxdepth 1 \
		-type f \
		! -name "$(CONFIG_FILE)" \
		! -name '*.sqlite' \
		! -name '*.sqlite-shm' \
		! -name '*.sqlite-wal' \
		! -name '*.tar.gz' \
		! -name '*~' \
		! -name '#*#' \
		! -name '.#*' \
		! -name '.DS_Store' \
		-exec cp {} "$(DIST_DIR)/$(APP_NAME)/" \;

	@if [ -d assets ]; then \
		cp -a assets "$(DIST_DIR)/$(APP_NAME)/"; \
	fi

	@if [ -d docs ]; then \
		cp -a docs "$(DIST_DIR)/$(APP_NAME)/"; \
	fi

	@tar \
		-C "$(DIST_DIR)" \
		-czf "$(PACKAGE_NAME)" \
		"$(APP_NAME)"

	@echo "[OK] Created $(DIST_DIR)/$(PACKAGE_NAME)"


# ------------------------------------------------------------
# 一時ファイル削除
# ------------------------------------------------------------

clean:
	@echo "[CLEAN] Removing temporary files"

	@find . \
		-maxdepth 1 \
		-type f \
		\( \
			-name '*~' \
			-o -name '#*#' \
			-o -name '.#*' \
			-o -name '.DS_Store' \
		\) \
		-delete

	@rm -rf "$(DIST_DIR)"

	@echo "[OK] Cleanup completed."
