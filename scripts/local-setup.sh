#!/usr/bin/env bash
# =============================================================================
# macoffice.at – LocalDev-Setup (Laravel Valet)
#
# Richtet aus dem Repo eine lokale WordPress-Installation ein:
#   Ordner:  ~/Valet-Umgebung/at.macoffice-2026
#   Domain:  https://at.macoffice-2026.localdev   (WordPress unter /cms)
#
# Aufruf (aus dem Projekt-Root):
#   bash scripts/local-setup.sh
#
# Optional per Umgebungsvariable:
#   DB_USER=media-admin DB_PASS=... DB_NAME=at_macoffice_2026 DB_PREFIX=mo_
#   DB_PASS nicht gesetzt = das Script fragt verdeckt danach. DB_PASS= (leer) = ohne Passwort.
#   ADMIN_USER=admin ADMIN_EMAIL=... ADMIN_PASS=...   (leer = zufälliges Passwort)
#   ACF_PRO_SRC=/pfad/zu/advanced-custom-fields-pro    (ACF Pro ist lizenzpflichtig)
#
# Das Script ist wiederholbar: Bestehendes (DB, WP-Core, wp-config) bleibt
# unverändert, es wird nur Fehlendes ergänzt.
# =============================================================================
set -euo pipefail

SLUG="at.macoffice-2026"
DOMAIN="${SLUG}.localdev"
URL="https://${DOMAIN}"
PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
CMS="${PROJECT_ROOT}/cms"

DB_USER="${DB_USER-media-admin}"
DB_NAME="${DB_NAME:-at_macoffice_2026}"
DB_PREFIX="${DB_PREFIX:-mo_}"
ADMIN_USER="${ADMIN_USER:-admin}"
ADMIN_EMAIL="${ADMIN_EMAIL:-markus.tritremmel@media-lab.at}"
# Zufallspasswort (php ist Voraussetzung). Keine Pipe mit head: unter pipefail
# beendet SIGPIPE das Script sonst lautlos.
ADMIN_PASS="${ADMIN_PASS:-$(php -r 'echo bin2hex(random_bytes(12));')}"
ACF_PRO_SRC="${ACF_PRO_SRC:-$HOME/Valet-Umgebung/media-lab-starter-kit/cms/wp-content/plugins/advanced-custom-fields-pro}"

step() { printf '\n\033[1;36m━━ %s\033[0m\n' "$*"; }
ok()   { printf '\033[0;32m✓  %s\033[0m\n' "$*"; }
warn() { printf '\033[1;33m⚠  %s\033[0m\n' "$*"; }
die()  { printf '\033[0;31m✗  %s\033[0m\n' "$*" >&2; exit 1; }

step "Voraussetzungen prüfen"
for bin in php mysql node npm composer wp valet; do
  command -v "$bin" >/dev/null 2>&1 || die "'$bin' nicht gefunden"
done
[ -d "$CMS" ] || die "Ordner cms/ fehlt – Script im Projekt-Root des Repos ausführen"
if [ "$(basename "$PROJECT_ROOT")" != "$SLUG" ]; then
  warn "Ordnername ist '$(basename "$PROJECT_ROOT")', Valet-Domain wird daher nicht ${DOMAIN}. Ordner in '${SLUG}' umbenennen."
fi
ok "Tools vorhanden"

step "Datenbank"
if [ -z "${DB_PASS+x}" ]; then
  read -r -s -p "MySQL-Passwort für '${DB_USER}': " DB_PASS
  echo
fi
MYSQL=(mysql -u"$DB_USER")
[ -n "$DB_PASS" ] && MYSQL+=(-p"$DB_PASS")
"${MYSQL[@]}" -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
ok "Datenbank ${DB_NAME}"

step "WordPress-Core"
cd "$CMS"
if [ ! -f wp-load.php ]; then
  wp core download --locale=de_AT --skip-content
  ok "Core heruntergeladen"
else
  ok "Core bereits vorhanden"
fi

step "wp-config.php"
if [ ! -f wp-config.php ]; then
  wp config create --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" \
    --dbprefix="$DB_PREFIX" --locale=de_AT --skip-check --extra-php <<'PHP'
/* Lokale Entwicklung */
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );

/* HTTPS hinter Valet */
$_SERVER['HTTPS'] = 'on';
define( 'FORCE_SSL_ADMIN', true );
PHP
  ok "wp-config.php erstellt"
else
  ok "wp-config.php bereits vorhanden"
fi

step "Installation"
if ! wp core is-installed 2>/dev/null; then
  wp core install --url="${URL}/cms" --title="macoffice (Neubau 2026)" \
    --admin_user="$ADMIN_USER" --admin_password="$ADMIN_PASS" \
    --admin_email="$ADMIN_EMAIL" --skip-email
  wp option update home "$URL"
  wp rewrite structure '/%postname%/' --hard
  wp option update blog_public 0
  INSTALLED_NOW=1
  ok "WordPress installiert (siteurl mit /cms, home ohne)"
else
  INSTALLED_NOW=0
  ok "WordPress bereits installiert"
fi

step "Plugins"
PLUG="$CMS/wp-content/plugins"
if [ ! -d "$PLUG/advanced-custom-fields-pro" ]; then
  if [ -d "$ACF_PRO_SRC" ]; then
    cp -R "$ACF_PRO_SRC" "$PLUG/advanced-custom-fields-pro"
    ok "ACF Pro kopiert aus $ACF_PRO_SRC"
  else
    warn "ACF Pro nicht gefunden ($ACF_PRO_SRC). Manuell nach cms/wp-content/plugins/ legen."
  fi
fi
# Plugins aus dem WordPress.org-Verzeichnis (Drittanbieter sind nicht im Repo; SEO übernimmt media-lab-seo, kein Yoast)
for p in woocommerce contact-form-7; do
  wp plugin is-installed "$p" 2>/dev/null || wp plugin install "$p"
done
for p in advanced-custom-fields-pro media-lab-agency-core woocommerce contact-form-7 media-lab-seo media-lab-woocommerce media-lab-bookings media-lab-backup macoffice-project; do
  if wp plugin is-installed "$p" 2>/dev/null; then wp plugin activate "$p" || warn "'$p' ließ sich nicht aktivieren"; fi
done
wp theme activate macoffice
ok "Plugins und Theme aktiviert"
step "Logos und Kontaktangaben (Agency Core)"
wp eval-file "$PROJECT_ROOT/scripts/seed-branding.php" || warn "seed-branding.php fehlgeschlagen (später manuell: wp --path=cms eval-file scripts/seed-branding.php)"
wp eval-file "$PROJECT_ROOT/scripts/seed-frontpage.php" || warn "seed-frontpage.php fehlgeschlagen (später manuell: wp --path=cms eval-file scripts/seed-frontpage.php)"
wp eval-file "$PROJECT_ROOT/scripts/seed-menus.php" || warn "seed-menus.php fehlgeschlagen (später manuell: wp --path=cms eval-file scripts/seed-menus.php)"
warn "Noch NICHT installiert (kommen mit dem Live-Import bzw. Phase 0/2): Germanized, Product Table Lite, Fluent Forms, FluentCRM, Flip Book, WP All Import Pro."

step "Valet"
cd "$PROJECT_ROOT"
[ -e wp-content ] || ln -s cms/wp-content wp-content
valet link "$SLUG" >/dev/null
valet secure "$SLUG" >/dev/null 2>&1 || warn "valet secure fehlgeschlagen (ggf. mit sudo wiederholen)"
ok "Valet: ${URL}"

step "Dependencies und Build"
[ -d vendor ] || composer install --no-interaction
npm ci
npm run build
ok "Build abgeschlossen"

step "Fertig"
echo "Frontend:  ${URL}/"
echo "Admin:     ${URL}/cms/wp-admin/"
if [ "${INSTALLED_NOW:-0}" = 1 ]; then
  echo "Benutzer:  ${ADMIN_USER}"
  echo "Passwort:  ${ADMIN_PASS}   (jetzt notieren, wird nicht gespeichert)"
fi
echo "Dev-Server: npm run dev   (aus dem Projekt-Root)"
