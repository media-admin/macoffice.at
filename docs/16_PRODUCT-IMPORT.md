# 16 – Produktimport (Artikel, Preise, Lagerstatus)

Die Artikel von macoffice.at kommen aus der Warenwirtschaft des Kunden als Excel-Datei (`Internetartikel.xlsx`, ca. 2.000 Zeilen) und werden mit **WP All Import Pro** in WooCommerce eingespielt (neue Artikel, Änderungen an Preis/Status/Bild, nicht mehr gelistete Artikel werden entfernt).

## Problem der bisherigen Lösung

- Die Datei wurde im Importfenster von WP All Import hochgeladen, der Import lief per AJAX **im Browser**: Fenster schließen = Abbruch.
- 1 Artikel pro Anfrage, bei jedem Lauf alle ~2.000 Artikel komplett neu geschrieben (inkl. Bildabgleich) – laut Verlauf 45–90 Minuten.
- Abgebrochene Läufe hinterlassen Merker ("Import läuft"), danach geht gar nichts mehr.

## Neuer Ablauf

```
Warenwirtschaft ──(SFTP/Skript oder URL)──┐
                                          ├─► uploads/wpallimport/files/Internetartikel.xlsx
Backend-Upload (Ersatzweg) ───────────────┘            │
                                                       ▼  alle 10 Min. (Cron)
                          wp macoffice product-feed run  → Datei geändert? → WP All Import (WP-CLI, ohne Browser)
```

- **Eine feste Ablage-Datei.** Wer sie liefert (SFTP, Abruf von URL, Backend-Upload), ist egal.
- **Läuft im Hintergrund** per WP-CLI/Cron, kein offenes Fenster nötig.
- **Nur bei Änderung:** Datei-Hash wird verglichen, bei unveränderter Datei passiert nichts.
- **Selektives Hashing** im Import: unveränderte Artikel werden übersprungen, nur geänderte Zeilen werden geschrieben (typisch: Minuten statt über eine Stunde).
- **Schutz vor halben Dateien:** Hat die Datei weniger als 60 % der Zeilen des letzten Imports (ab 200 Zeilen), wird sie *nicht* importiert – der Import würde sonst alle fehlenden Artikel löschen. Bewusst übergehen: Häkchen im Backend bzw. `--force`.
- **Keine Doppelläufe** (Dateisperre) und **Aufräumen hängengebliebener Läufe** (Merker werden nur zurückgesetzt, wenn der Fortschritt 60 Sekunden lang stillsteht – ein im Browser laufender Import wird nicht gestört).
- Dateien, die gerade erst (< 60 s) geschrieben wurden, werden beim nächsten Durchlauf verarbeitet (SFTP-Upload noch nicht fertig).

Code: `cms/wp-content/plugins/macoffice-project/inc/product-feed.php`

## Einrichtung (einmalig pro Umgebung)

1. Plugin `macoffice-project` aktiv, WooCommerce und WP All Import Pro aktiv, Import in WP All Import vorhanden (Name `Internetartikel.xlsx`).
2. **Import umstellen** (Vorschau, dann speichern):
   ```bash
   wp --path=cms macoffice product-feed setup            # zeigt nur an
   wp --path=cms macoffice product-feed setup --apply    # sichert die alten Einstellungen und stellt um
   ```
   Setzt Quelle = Datei im Ablageordner, selektives Hashing = an, 20 Datensätze pro Anfrage (nur für manuelle Läufe im Browser relevant). Die alten Einstellungen liegen danach in der Option `macoffice_pf_options_backup_<ID>`.
   **Der erste Lauf danach verarbeitet alle Artikel noch einmal** (neue Hashes), ab dem zweiten Lauf nur noch Änderungen.
3. **Cron einrichten** (Server mit SSH und WP-CLI):
   ```bash
   scripts/setup-product-import-cron.sh /pfad/zu/cms --fetch          # Vorschau
   scripts/setup-product-import-cron.sh /pfad/zu/cms --fetch --install
   ```
   `--fetch` nur, wenn der Feed von einer URL geholt werden soll (dann zusätzlich Option `macoffice_pf_url` setzen, https; Zugangsdaten optional als Konstanten `MACOFFICE_FEED_USER` / `MACOFFICE_FEED_PASS` in der `wp-config.php`).
4. Test: Datei ablegen oder hochladen, dann `wp --path=cms macoffice product-feed run --dry-run`, danach ohne `--dry-run`.

### Hosting ohne SSH/WP-CLI (z. B. Shared Hosting)

WP All Import bringt eigene Cron-URLs mit (Import > Verwalten > Scheduling). Beim Hoster zwei Cronjobs per URL anlegen:
- `…/wp-load.php?import_key=<Schlüssel>&import_id=<ID>&action=trigger` (z. B. 1× nachts)
- `…/wp-load.php?import_key=<Schlüssel>&import_id=<ID>&action=processing` (alle 2 Minuten)

Dann fehlen allerdings Änderungserkennung und Schutz vor kleinen Dateien aus dem CLI-Lauf; Upload im Backend und Hashing funktionieren trotzdem.

## Alltag

| Was | Wie |
|---|---|
| Datei bereitstellen | SFTP/URL automatisch, sonst Backend: **Produkte > Artikel-Import** |
| Stand ansehen | gleiche Seite, oder `wp macoffice product-feed status` |
| Sofort starten | `wp macoffice product-feed run --force` (Cron-Durchlauf abwarten reicht normalerweise) |
| Log | `product-import.log` neben dem Ordner `cms`; Details je Lauf unter WP All Import > Verwalten |

## Fehlerbilder

- *"Datei enthält nur … Zeilen"* → Export beim Kunden unvollständig; neu liefern lassen. Nur bei echter Verkleinerung `--force`.
- *"Import already running"* → wird beim nächsten Durchlauf automatisch aufgeräumt, wenn wirklich nichts mehr läuft.
- Bilder fehlen/laden lange → liegt am Bildabgleich der ersten Läufe; danach greift das Hashing.

## Offene Punkte

- Wo liegt der Feed bzw. wie liefert der Kunde ihn (SFTP/URL)? Davon hängt `--fetch` ab.
- Beispieldatei `Internetartikel.xlsx` (Kopfzeile + einige Zeilen) zur Kontrolle der Bildspalten (`nochfeld1–5`).
- Hosting klären: Hetzner (SSH, Cron) vs. Shared Hosting (Web-Cron).
