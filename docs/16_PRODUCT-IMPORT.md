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

## Zwei Betriebsarten

| | **Web-Modus** (Standard, z. B. IONOS Shared Hosting) | **CLI-Modus** (Server mit SSH/WP-CLI, z. B. Hetzner) |
|---|---|---|
| Auslöser | WP-Cron-Ereignis alle 5 Min. (läuft bei Seitenaufrufen; täglicher IONOS-Cronjob auf `wp-cron.php` als Sicherheitsnetz) | Server-Cron alle 10 Min.: `wp macoffice product-feed run` |
| Import | WP All Import über seine eigenen Cron-Aufrufe (`trigger`, dann `processing` in Etappen, jede Etappe mit eigenem Zeitlimit) | `wp all-import run <ID>` in einem Prozess |
| Einrichtung | Button im Backend | WP-CLI + Skript |
| Umschalten | Option `macoffice_pf_mode` (`web`/`cli`) | |

Beide Arten haben denselben Schutz (Änderungserkennung, Größenschutz, Sperre, Hänger-Erkennung). Im Web-Modus gibt der Import nach 3 erfolglosen Versuchen für dieselbe Datei auf und zeigt den Fehler im Backend.

## Einrichtung (einmalig pro Umgebung)

### Web-Modus (IONOS)
1. Plugin `macoffice-project` und WP All Import Pro aktiv; in WP All Import Pro unter *Einstellungen* einen **Cron-Schlüssel** speichern (muss gesetzt sein).
2. **Produkte > Artikel-Import** öffnen, Hinweis „Einrichtung nötig“ → **Import jetzt umstellen**. Das setzt Quelle = Datei im Ablageordner, selektives Hashing an, 20 Datensätze pro Anfrage; die alten Einstellungen werden gesichert (Option `macoffice_pf_options_backup_<ID>`). Der erste Lauf danach verarbeitet alle Artikel noch einmal.
3. Artikeldatei hochladen (oder per SFTP in `wp-content/uploads/wpallimport/files/Internetartikel.xlsx` legen). Nach spätestens ca. 5 Minuten startet der Import im Hintergrund; Stand und Fehler stehen auf derselben Seite.
4. Empfohlen: im IONOS-Konto einen Cronjob einrichten, der täglich `https://<domain>/cms/wp-cron.php` aufruft (Sicherheitsnetz für ruhige Zeiten, falls WP-Cron mangels Besuchern nicht läuft). `DISABLE_WP_CRON` darf nicht auf `true` stehen.

### CLI-Modus
```bash
wp --path=cms option update macoffice_pf_mode cli
wp --path=cms macoffice product-feed setup            # Vorschau
wp --path=cms macoffice product-feed setup --apply
scripts/setup-product-import-cron.sh /pfad/zu/cms --fetch --install   # --fetch nur bei Feed-URL
wp --path=cms macoffice product-feed run --dry-run
```

### Feed von einer URL holen (optional)
Option `macoffice_pf_url` (https) setzen; Zugangsdaten optional als Konstanten `MACOFFICE_FEED_USER` / `MACOFFICE_FEED_PASS` in der `wp-config.php`. Abgeholt wird im CLI-Modus mit `run --fetch` (Skript `--fetch`). Im Web-Modus gibt es dafür noch keinen Abruf; dort kommt die Datei per Upload oder SFTP.

## Alltag

| Was | Wie |
|---|---|
| Datei bereitstellen | SFTP/URL automatisch, sonst Backend: **Produkte > Artikel-Import** |
| Stand ansehen | gleiche Seite, oder `wp macoffice product-feed status` |
| Sofort starten | Backend-Button „Jetzt importieren“ (Web-Modus) bzw. `wp macoffice product-feed run --force` |
| Log | `product-import.log` neben dem Ordner `cms`; Details je Lauf unter WP All Import > Verwalten |

## Fehlerbilder

- *"Datei enthält nur … Zeilen"* → Export beim Kunden unvollständig; neu liefern lassen. Nur bei echter Verkleinerung `--force`.
- *"Import already running"* → wird beim nächsten Durchlauf automatisch aufgeräumt, wenn wirklich nichts mehr läuft.
- Bilder fehlen/laden lange → liegt am Bildabgleich der ersten Läufe; danach greift das Hashing.

## Offene Punkte

- Wo liegt der Feed bzw. wie liefert der Kunde ihn (SFTP/URL)? Davon hängt `--fetch` ab.
- Beispieldatei `Internetartikel.xlsx` (Kopfzeile + einige Zeilen) zur Kontrolle der Bildspalten (`nochfeld1–5`).
- Live-Hosting ist IONOS Shared Hosting: Web-Modus. Ob das Paket SSH/WP-CLI hat, ist nur für den CLI-Modus relevant.
- Der Web-Modus ist nur mit Stubs getestet; erster echter Lauf auf Staging beobachten (Loopback-Aufruf `wp-load.php?import_key=…` muss auf dem Hosting erreichbar sein).
