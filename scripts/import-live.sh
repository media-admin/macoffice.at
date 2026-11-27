#!/usr/bin/env bash
# =============================================================================
# macoffice.at – Live-Datenbank in LocalDev importieren
#
# Aufruf (aus dem Projekt-Root, nach scripts/local-setup.sh):
#   bash scripts/import-live.sh                      # neuester Dump aus _import/
#   bash scripts/import-live.sh pfad/zum/dump.sql.gz # bestimmter Dump (.sql, .sql.gz, .zip)
#   bash scripts/import-live.sh dump.sql uploads/    # zusätzlich Uploads (Ordner oder .zip)
#
# Optional per Umgebungsvariable:
#   OLD_URL=https://macoffice.at   (sonst aus der Option 'home' des Dumps gelesen)
#   DUMP_PREFIX=wp_                (sonst aus dem Dump erkannt)
#   ASSUME_YES=1                   (keine Rückfrage vor dem Überschreiben der lokalen DB)
#
# Was passiert:
#   1. Lokale DB wird gesichert (_import/lokal-vor-import-*.sql) und geleert
#   2. Dump wird eingespielt, Tabellenpräfix in wp-config.php wird angepasst
#   3. URLs werden auf https://at.macoffice-2026.localdev umgeschrieben
#   4. Aktive Plugins werden auf den neuen Satz zurückgesetzt (kein Yoast etc.)
#   5. Mailversand wird lokal blockiert (mu-plugin zz-local-no-mail.php)
#   6. Lokaler Admin wird angelegt/zurückgesetzt, Branding-Seed läuft erneut
#
# Dumps enthalten Kunden- und Bestelldaten. Nie committen: _import/ und *.sql*
# stehen in der .gitignore (das Repo ist öffentlich).
# =============================================================================
set -euo pipefail

SLUG="at.macoffice-2026"
URL="https://${SLUG}.localdev"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CMS="${PROJECT_ROOT}/cms"
IMPORT_DIR="${PROJECT_ROOT}/_import"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-markus.tritremmel@media-lab.at}"
ADMIN_PASS="${ADMIN_PASS:-$(php -r 'echo bin2hex(random_bytes(12));')}"
# Plugins, die im neuen Aufbau aktiv sein sollen (nur installierte werden aktiviert)
PLUGINS="advanced-custom-fields-pro media-lab-agency-core woocommerce contact-form-7 media-lab-seo media-lab-woocommerce media-lab-bookings media-lab-backup macoffice-project"

step() { printf '\n\033[1;36m━━ %s\033[0m\n' "$*"; }
ok()   { printf '\033[0;32m✓  %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠  %s\033[0m\n' "$*"; }
die()  { printf '\033[0;31m✗  %s\033[0m\n' "$*" >&2; exit 1; }

DUMP="${1:-}"
UPLOADS_SRC="${2:-}"

step "Voraussetzungen"
for bin in php mysql wp; do command -v "$bin" >/dev/null 2>&1 || die "'$bin' nicht gefunden"; done
[ -f "$CMS/wp-config.php" ] || die "cms/wp-config.php fehlt – zuerst 'bash scripts/local-setup.sh' ausführen"
mkdir -p "$IMPORT_DIR"
if [ -z "$DUMP" ]; then
  DUMP="$(ls -t "$IMPORT_DIR"/*.sql "$IMPORT_DIR"/*.sql.gz "$IMPORT_DIR"/*.zip 2>/dev/null | grep -v 'lokal-vor-import' | sed -n 1p || true)"
fi
[ -n "$DUMP" ] && [ -f "$DUMP" ] || die "Kein Dump gefunden. Datei nach _import/ legen oder Pfad als Argument angeben."
ok "Dump: $DUMP ($(du -h "$DUMP" | cut -f1))"

read_dump() {
  case "$DUMP" in
    *.gz)  gunzip -c "$DUMP" ;;
    *.zip) unzip -p "$DUMP" '*.sql' -x '__MACOSX/*' ;;
    *)     cat "$DUMP" ;;
  esac
}

step "Tabellenpräfix erkennen"
if [ -z "${DUMP_PREFIX:-}" ]; then
  FOUND="$(read_dump | grep -m1 -oE 'CREATE TABLE (IF NOT EXISTS )?`[A-Za-z0-9_]*options`' || true)"
  [ -n "$FOUND" ] || die "Keine *options-Tabelle im Dump gefunden. DUMP_PREFIX=... setzen?"
  DUMP_PREFIX="$(printf '%s' "$FOUND" | sed 's/.*`\([A-Za-z0-9_]*\)options`.*/\1/')"
fi
ok "Präfix des Dumps: ${DUMP_PREFIX}"

cd "$CMS"
LOCAL_PREFIX="$(wp config get table_prefix --skip-plugins --skip-themes)"
if [ "${ASSUME_YES:-0}" != 1 ]; then
  warn "Die lokale Datenbank wird komplett überschrieben (vorher wird sie gesichert)."
  read -r -p "Fortfahren? [j/N] " ANSWER
  case "$ANSWER" in j|J|y|Y) ;; *) die "Abgebrochen." ;; esac
fi

step "Lokale DB sichern und leeren"
BACKUP="$IMPORT_DIR/lokal-vor-import-$(date +%Y%m%d-%H%M%S).sql"
wp db export "$BACKUP" --skip-plugins --skip-themes >/dev/null 2>&1 && ok "Gesichert: $BACKUP" || warn "Sicherung nicht möglich (DB evtl. leer)"
wp db reset --yes --skip-plugins --skip-themes >/dev/null
if [ "$LOCAL_PREFIX" != "$DUMP_PREFIX" ]; then
  wp config set table_prefix "$DUMP_PREFIX" --type=variable --skip-plugins --skip-themes >/dev/null
  ok "wp-config.php: table_prefix ${LOCAL_PREFIX} → ${DUMP_PREFIX}"
fi

step "Dump einspielen"
# USE/CREATE DATABASE/DEFINER entfernen, damit nur in die lokale DB geschrieben wird
read_dump \
  | LC_ALL=C sed -E '/^(USE |CREATE DATABASE )/d; s/DEFINER=`[^`]*`@`[^`]*`//g' \
  | wp db import - --skip-plugins --skip-themes
CHECK="$(wp db query "SHOW TABLES LIKE '${DUMP_PREFIX}options'" --skip-plugins --skip-themes --skip-column-names)"
[ -n "$CHECK" ] || die "Import fehlgeschlagen: ${DUMP_PREFIX}options fehlt"
ok "Dump importiert"

step "URLs umschreiben"
if [ -z "${OLD_URL:-}" ]; then
  OLD_URL="$(wp db query "SELECT option_value FROM \`${DUMP_PREFIX}options\` WHERE option_name='home'" --skip-plugins --skip-themes --skip-column-names | tr -d '\r\n')"
fi
OLD_URL="${OLD_URL%/}"
case "$OLD_URL" in http://*|https://*) ;; *) die "OLD_URL '$OLD_URL' ist keine gültige URL" ;; esac
OLD_HOST="${OLD_URL#*://}"; OLD_HOST="${OLD_HOST#www.}"
ok "Live-URL: ${OLD_URL} (Host ${OLD_HOST})"
SR=(wp search-replace --all-tables-with-prefix --skip-columns=guid --skip-plugins --skip-themes --report-changed-only)
for from in "https://www.${OLD_HOST}" "http://www.${OLD_HOST}" "https://${OLD_HOST}" "http://${OLD_HOST}"; do
  "${SR[@]}" "$from" "$URL" | tail -1
  # JSON-escaped (Blockeditor-Attribute, Page-Builder-Daten)
  "${SR[@]}" "$(printf '%s' "$from" | sed 's#/#\\/#g')" "$(printf '%s' "$URL" | sed 's#/#\\/#g')" >/dev/null || true
done
wp option update home "$URL" --skip-plugins --skip-themes >/dev/null
wp option update siteurl "${URL}/cms" --skip-plugins --skip-themes >/dev/null
wp option update blog_public 0 --skip-plugins --skip-themes >/dev/null
ok "home=${URL}, siteurl=${URL}/cms, Suchmaschinen gesperrt"

step "Plugins und Theme zurücksetzen"
wp option update active_plugins '[]' --format=json --skip-plugins --skip-themes >/dev/null
wp option delete active_sitewide_plugins --skip-plugins --skip-themes >/dev/null 2>&1 || true
for p in $PLUGINS; do
  if wp plugin is-installed "$p" --skip-plugins --skip-themes 2>/dev/null; then
    wp plugin activate "$p" --skip-themes >/dev/null 2>&1 && ok "aktiviert: $p" || warn "'$p' ließ sich nicht aktivieren"
  else
    warn "nicht installiert: $p"
  fi
done
wp theme activate macoffice --skip-plugins >/dev/null && ok "Theme macoffice aktiv"

step "Mailversand lokal sperren"
cat > "$CMS/wp-content/mu-plugins/zz-local-no-mail.php" <<'PHP'
<?php
/**
 * Nur LocalDev (steht in .gitignore): blockiert jeden Mailversand, damit mit
 * importierten Live-Daten (Kunden, Bestellungen, Newsletter) nichts rausgeht.
 */
if ( function_exists( 'wp_get_environment_type' ) && 'local' === wp_get_environment_type() ) {
	add_filter( 'pre_wp_mail', function ( $return, $atts ) {
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[local-no-mail] blockiert: ' . ( is_array( $atts['to'] ) ? implode( ',', $atts['to'] ) : $atts['to'] ) . ' – ' . $atts['subject'] );
		}
		return false;
	}, 10, 2 );
}
PHP
ok "mu-plugin zz-local-no-mail.php geschrieben"

step "Aufräumen"
wp transient delete --all >/dev/null 2>&1 || true
wp db query "TRUNCATE TABLE \`${DUMP_PREFIX}woocommerce_sessions\`" >/dev/null 2>&1 || true
wp cache flush >/dev/null 2>&1 || true
wp rewrite flush --hard >/dev/null 2>&1 || true
ok "Transients, Sessions, Cache, Permalinks"

if [ -n "$UPLOADS_SRC" ]; then
  step "Uploads"
  DEST="$CMS/wp-content/uploads"; mkdir -p "$DEST"
  SRC="$UPLOADS_SRC"
  if [ -f "$UPLOADS_SRC" ]; then
    TMP="$(mktemp -d)"; unzip -q "$UPLOADS_SRC" -d "$TMP"
    SRC="$TMP"
    [ -d "$TMP/wp-content/uploads" ] && SRC="$TMP/wp-content/uploads"
    [ -d "$TMP/uploads" ] && SRC="$TMP/uploads"
  fi
  [ -d "$SRC" ] || die "Uploads-Quelle '$UPLOADS_SRC' nicht gefunden"
  rsync -a "${SRC%/}/" "$DEST/"
  ok "Uploads kopiert nach cms/wp-content/uploads"
else
  warn "Keine Uploads angegeben – Bilder fehlen lokal (später: bash scripts/import-live.sh <dump> <uploads>, oder nur die Uploads per rsync nachliefern)."
fi

step "Lokaler Admin und Branding"
if wp user get "$ADMIN_USER" --skip-plugins --skip-themes >/dev/null 2>&1; then
  wp user update "$ADMIN_USER" --user_pass="$ADMIN_PASS" --role=administrator --skip-plugins --skip-themes >/dev/null
else
  wp user create "$ADMIN_USER" "$ADMIN_EMAIL" --role=administrator --user_pass="$ADMIN_PASS" --skip-plugins --skip-themes >/dev/null
fi
wp eval-file "$PROJECT_ROOT/scripts/seed-branding.php" || warn "seed-branding.php fehlgeschlagen (später: wp --path=cms eval-file scripts/seed-branding.php)"

step "Fertig"
echo "Frontend:  ${URL}/"
echo "Admin:     ${URL}/cms/wp-admin/"
echo "Benutzer:  ${ADMIN_USER}"
echo "Passwort:  ${ADMIN_PASS}   (jetzt notieren, wird nicht gespeichert)"
echo "Hinweis:   Alle Live-Benutzer sind mit importiert; lokal anmelden mit dem Admin oben."
