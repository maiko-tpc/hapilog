#!/bin/sh

SCRIPT_DIR=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
CONFIG_FILE="$SCRIPT_DIR/config.php"

DB=$(php -r '$config = require $argv[1]; echo $config["database_path"];' "$CONFIG_FILE")
BACKUP_DIR=$(php -r '$config = require $argv[1]; echo $config["backup_directory"];' "$CONFIG_FILE")
KEEP=$(php -r '$config = require $argv[1]; echo (int)$config["backup_keep"];' "$CONFIG_FILE")

if [ -z "$DB" ] || [ -z "$BACKUP_DIR" ] || [ -z "$KEEP" ]; then
    echo "Failed to read backup settings from config.php" >&2
    exit 1
fi

if [ ! -f "$DB" ]; then
    echo "Database file not found: $DB" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR" || exit 1

STAMP=$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="$BACKUP_DIR/equipment_${STAMP}.sqlite"

cp "$DB" "$BACKUP_FILE" || exit 1

 echo "Backup created:"
 echo "$BACKUP_FILE"

# Keep only the newest $KEEP backup files.
# Older files are deleted.
COUNT=$(find "$BACKUP_DIR" -maxdepth 1 -type f -name 'equipment_*.sqlite' | wc -l)

if [ "$COUNT" -gt "$KEEP" ]; then
    ls -1t "$BACKUP_DIR"/equipment_*.sqlite 2>/dev/null \
        | tail -n "+$(expr "$KEEP" + 1)" \
        | while IFS= read -r file
    do
        rm -f "$file"
        echo "Removed old backup: $file"
    done
fi

echo "Backup cleanup completed. Keeping newest $KEEP files."
