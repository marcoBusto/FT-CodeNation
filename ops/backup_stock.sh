#!/bin/bash
# Backup diario de la base de producción: dump comprimido + retención de 14 días.
set -euo pipefail

DESTINO=/home/bitnami/backups/db
CONFIG=/home/bitnami/.stock_backup.cnf
RETENCION_DIAS=14

mkdir -p "$DESTINO"
ARCHIVO="$DESTINO/stock_agricola_$(date +%Y%m%d_%H%M).sql.gz"
trap 'rm -f "$ARCHIVO"' ERR

/opt/bitnami/mariadb/bin/mariadb-dump --defaults-extra-file="$CONFIG" \
  --single-transaction stock_agricola | gzip > "$ARCHIVO"

# Un dump vacío o truncado no sirve: falla en voz alta antes de rotar backups buenos.
gunzip -t "$ARCHIVO"
[ "$(gunzip -c "$ARCHIVO" | grep -c 'CREATE TABLE')" -ge 1 ]

find "$DESTINO" -name 'stock_agricola_*.sql.gz' -mtime +"$RETENCION_DIAS" -delete
echo "$(date '+%F %T') OK $(basename "$ARCHIVO") $(du -h "$ARCHIVO" | cut -f1)"
