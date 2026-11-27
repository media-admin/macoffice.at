#!/usr/bin/env bash
# Richtet den automatischen Artikel-Import als Cronjob ein (Server mit SSH/WP-CLI).
#
#   scripts/setup-product-import-cron.sh /pfad/zur/installation/cms            # Vorschau
#   scripts/setup-product-import-cron.sh /pfad/zur/installation/cms --install  # in die crontab des Benutzers schreiben
#   Weitere Optionen: --fetch (zusätzlich nachts die Feed-URL abrufen), --wp=/usr/local/bin/wp
#
# Läuft alle 10 Minuten: prüft, ob sich Internetartikel.xlsx geändert hat, und startet nur dann den Import
# (siehe docs/16_PRODUCT-IMPORT.md). Mehrfaches Ausführen ersetzt den bestehenden Eintrag, es entstehen keine Duplikate.
set -euo pipefail

WP_PATH="${1:-}"
[[ -n "$WP_PATH" && -d "$WP_PATH" ]] || { echo "Aufruf: $0 <WordPress-Ordner (cms)> [--install] [--fetch] [--wp=<Pfad>]" >&2; exit 1; }
shift
INSTALL=0; FETCH=0; WP_BIN="$(command -v wp || true)"
for a in "$@"; do
  case "$a" in
    --install) INSTALL=1 ;;
    --fetch)   FETCH=1 ;;
    --wp=*)    WP_BIN="${a#--wp=}" ;;
    *) echo "Unbekannte Option: $a" >&2; exit 1 ;;
  esac
done
[[ -x "$WP_BIN" ]] || { echo "WP-CLI (wp) nicht gefunden. Mit --wp=/Pfad/zu/wp angeben." >&2; exit 1; }
WP_PATH="$(cd "$WP_PATH" && pwd)"
LOG="$WP_PATH/../product-import.log"
MARK="# macoffice-product-import"

CMD="$WP_BIN --path=$WP_PATH macoffice product-feed run"
LINES="*/10 * * * * $CMD >> $LOG 2>&1 $MARK"
[[ $FETCH -eq 1 ]] && LINES+=$'\n'"5 3 * * * $CMD --fetch >> $LOG 2>&1 $MARK"

echo "Cron-Einträge:"; echo "$LINES"; echo
"$WP_BIN" --path="$WP_PATH" macoffice product-feed status || {
  echo "Hinweis: Befehl 'macoffice product-feed' nicht verfügbar (Plugin macoffice-project aktiv? WooCommerce aktiv?)." >&2; exit 1; }

if [[ $INSTALL -eq 1 ]]; then
  { crontab -l 2>/dev/null | grep -vF "$MARK" || true; echo "$LINES"; } | crontab -
  echo "Installiert. Log: $LOG"
else
  echo "Vorschau – mit --install eintragen."
fi
