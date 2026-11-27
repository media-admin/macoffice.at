#!/usr/bin/env bash
# Lokale Entwicklung: fehlende Mediathek-Dateien direkt von der Live-Seite laden (Hotlink),
# statt mehrere GB Uploads herunterzuladen.
#
#   bash scripts/uploads-from-live.sh on    # Fallback aktivieren (Standard)
#   bash scripts/uploads-from-live.sh off   # entfernen
#
# Nur LocalDev: Das mu-plugin ist per .gitignore ausgeschlossen und wird nie deployed.
# Dateien, die lokal existieren (z. B. eigene Uploads, Seed-Bilder), haben Vorrang.
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
MU="$ROOT/cms/wp-content/mu-plugins/zz-local-uploads-from-live.php"
LIVE_URL="${LIVE_UPLOADS_URL:-https://www.macoffice.at/wp-content/uploads}"
MODE="${1:-on}"

case "$MODE" in
  off)
    rm -f "$MU"
    echo "Uploads-Fallback entfernt."
    ;;
  on)
    mkdir -p "$(dirname "$MU")"
    cat > "$MU" <<PHP
<?php
/**
 * LOCAL ONLY – von scripts/uploads-from-live.sh erzeugt, nicht committen/deployen.
 * Fehlt eine Mediathek-Datei lokal, wird die URL auf die Live-Seite umgeschrieben.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

const MACOFFICE_LIVE_UPLOADS_URL = '${LIVE_URL}';

/** Liefert die Live-URL, wenn die lokale Datei zur URL fehlt; sonst die Original-URL. */
function macoffice_local_or_live_url( string \$url ): string {
    \$up = wp_get_upload_dir();
    if ( strpos( \$url, \$up['baseurl'] ) !== 0 ) {
        return \$url;
    }
    \$rel = ltrim( substr( \$url, strlen( \$up['baseurl'] ) ), '/' );
    if ( file_exists( \$up['basedir'] . '/' . \$rel ) ) {
        return \$url;
    }
    return MACOFFICE_LIVE_UPLOADS_URL . '/' . \$rel;
}

add_filter( 'wp_get_attachment_url', 'macoffice_local_or_live_url', 20 );

add_filter( 'wp_calculate_image_srcset', function ( \$sources ) {
    if ( ! is_array( \$sources ) ) {
        return \$sources;
    }
    foreach ( \$sources as \$w => \$s ) {
        \$sources[ \$w ]['url'] = macoffice_local_or_live_url( \$s['url'] );
    }
    return \$sources;
}, 20 );

// Inhalte mit fest eingetragenen Upload-URLs (Beitragstext): Host wurde beim Import auf LocalDev umgeschrieben.
add_filter( 'the_content', function ( \$html ) {
    \$up = wp_get_upload_dir();
    return preg_replace_callback(
        '#' . preg_quote( \$up['baseurl'], '#' ) . '/[^"\'\s)]+#',
        static fn( \$m ) => macoffice_local_or_live_url( \$m[0] ),
        \$html
    );
}, 20 );
PHP
    php -l "$MU" >/dev/null
    echo "Uploads-Fallback aktiv: fehlende Dateien kommen von $LIVE_URL"
    ;;
  *)
    echo "Aufruf: bash scripts/uploads-from-live.sh [on|off]" >&2; exit 1 ;;
esac
