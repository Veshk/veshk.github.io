#!/bin/sh
set -eu
umask 077

portal_dir=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
portal_env=${PORTAL_ENV_FILE:-/opt/vesh-portal/runtime.env}
backup_dir=${PORTAL_BACKUP_DIR:-/opt/vesh-portal/backups}
mkdir -p "$backup_dir"
chmod 700 "$backup_dir"
backup_file="$backup_dir/database-$(date -u +%Y%m%dT%H%M%SZ).dump"
trap 'rm -f "$backup_file.tmp"' EXIT HUP INT TERM

docker compose --project-directory "$portal_dir" --env-file "$portal_env" exec -T db \
    sh -c 'exec pg_dump --username "$DB_USERNAME" --dbname "$DB_DATABASE" --format=custom --no-owner' \
    > "$backup_file.tmp"
test -s "$backup_file.tmp"
mv "$backup_file.tmp" "$backup_file"
printf 'Database backup saved: %s\n' "$backup_file"
