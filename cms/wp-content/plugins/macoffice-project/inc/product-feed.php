<?php
/**
 * Produktimport: Feed-Datei, Backend-Upload und automatischer Lauf (WP All Import bleibt die Import-Engine).
 *
 * Bisher: Excel-Datei im Importfenster von WP All Import hochladen, 1 Artikel pro AJAX-Anfrage, Fenster offen halten.
 * Jetzt: Es gibt EINE feste Ablage-Datei (wp-content/uploads/wpallimport/files/Internetartikel.xlsx). Sie kommt
 *   1. per SFTP/Skript des Kunden oder von einer URL (Cron: `wp macoffice product-feed run --fetch`), oder
 *   2. per Upload im Backend (Produkte > Artikel-Import) als Rückfallebene.
 * Der Lauf selbst startet ohne Browser über WP-CLI/Cron (`wp macoffice product-feed run`): nur wenn sich die Datei
 * geändert hat, nie doppelt (Sperre), mit Schutz vor abgeschnittenen Dateien (der Import entfernt fehlende Artikel!)
 * und mit Aufräumen hängengebliebener WP-All-Import-Läufe.
 *
 * Einmalige Umstellung des bestehenden Imports: `wp macoffice product-feed setup --apply`
 * (Quelle = Datei im Ablageordner, selektives Hashing = unveränderte Artikel werden übersprungen).
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Grundlagen ───────────────────────────────────────────────────────────────

/** Dateiname der Ablage-Datei. Muss zur Quelle des WP-All-Import-Imports passen (siehe `setup`). */
const MACOFFICE_PF_FILENAME = 'Internetartikel.xlsx';
/** Ab diesem Anteil der zuletzt akzeptierten Zeilenzahl gilt eine Datei als abgeschnitten/fehlerhaft. */
const MACOFFICE_PF_MIN_RATIO = 0.6;
/** Erst ab dieser Zeilenzahl greift der Schutz (kleine Test-Dateien sollen immer möglich sein). */
const MACOFFICE_PF_GUARD_FROM = 200;

function macoffice_pf_capability(): string {
    return (string) apply_filters( 'macoffice_pf_capability', class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options' );
}

function macoffice_pf_dir(): string {
    $uploads = wp_upload_dir( null, false );
    return rtrim( (string) apply_filters( 'macoffice_product_feed_dir', $uploads['basedir'] . '/wpallimport/files' ), '/' );
}

function macoffice_pf_file(): string {
    return (string) apply_filters( 'macoffice_product_feed_file', macoffice_pf_dir() . '/' . MACOFFICE_PF_FILENAME );
}

function macoffice_pf_state(): array {
    $s = get_option( 'macoffice_pf_state' );
    return is_array( $s ) ? $s : array();
}

/** Ergebnis des letzten erfolgreichen Imports (Datei-Hash, Zeilenzahl, Zeit). */
function macoffice_pf_last_run(): array {
    $s = get_option( 'macoffice_pf_last_run' );
    return is_array( $s ) ? $s : array();
}

// ── Prüfen ───────────────────────────────────────────────────────────────────

/**
 * Datenzeilen einer Feed-Datei zählen (ohne Kopfzeile), ohne die Datei komplett in den Speicher zu laden.
 * xlsx: <row>-Elemente im ersten Tabellenblatt (leere, nur formatierte Zeilen zählen mit, die Zahl ist also eine
 * obere Näherung – für den Abschneide-Schutz reicht das). csv: Zeilen.
 *
 * @return int|WP_Error
 */
function macoffice_pf_count_rows( string $path ) {
    if ( ! is_readable( $path ) ) {
        return new WP_Error( 'unreadable', 'Datei nicht lesbar.' );
    }
    $ext = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

    if ( 'csv' === $ext ) {
        $n = 0;
        $fh = fopen( $path, 'rb' );
        if ( ! $fh ) {
            return new WP_Error( 'unreadable', 'Datei nicht lesbar.' );
        }
        while ( false !== ( $line = fgets( $fh ) ) ) {
            if ( '' !== trim( $line ) ) {
                $n++;
            }
        }
        fclose( $fh );
        return max( 0, $n - 1 );
    }

    if ( 'xlsx' !== $ext ) {
        return new WP_Error( 'type', 'Nur .xlsx oder .csv erlaubt.' );
    }
    if ( ! class_exists( 'ZipArchive' ) ) {
        return new WP_Error( 'zip', 'PHP-Erweiterung zip fehlt.' );
    }
    $zip = new ZipArchive();
    if ( true !== $zip->open( $path ) ) {
        return new WP_Error( 'zip', 'Die Datei ist keine gültige Excel-Datei (xlsx) oder unvollständig.' );
    }
    $sheets = array();
    for ( $i = 0; $i < $zip->numFiles; $i++ ) {
        $name = (string) $zip->getNameIndex( $i );
        if ( preg_match( '#^xl/worksheets/sheet\d+\.xml$#', $name ) ) {
            $sheets[] = $name;
        }
    }
    if ( ! $sheets ) {
        $zip->close();
        return new WP_Error( 'zip', 'Die Excel-Datei enthält kein Tabellenblatt.' );
    }
    natsort( $sheets );
    $stream = $zip->getStream( reset( $sheets ) );
    if ( ! $stream ) {
        $zip->close();
        return new WP_Error( 'zip', 'Tabellenblatt nicht lesbar.' );
    }
    $rows = 0;
    $tail = '';
    while ( ! feof( $stream ) ) {
        $chunk = $tail . (string) fread( $stream, 262144 );
        $rows += preg_match_all( '/<row[\s>]/', $chunk );
        $tail  = substr( $chunk, -5 ); // "<row " kann an der Blockgrenze liegen; 5 Zeichen sind kürzer als der Treffer
        // Treffer im Rest nicht doppelt zählen: der Rest enthält höchstens ein unvollständiges "<row".
        if ( preg_match( '/<row[\s>]/', $tail ) ) {
            $tail = '';
        }
    }
    fclose( $stream );
    $zip->close();

    return max( 0, $rows - 1 );
}

/**
 * Schutz vor abgeschnittenen Dateien. Der Import entfernt Artikel, die in der Datei fehlen – eine halbe Datei würde
 * also die Hälfte des Shops löschen.
 *
 * @return true|WP_Error
 */
function macoffice_pf_check_rows( int $rows, bool $force = false ) {
    if ( $rows < 1 ) {
        return new WP_Error( 'empty', 'Die Datei enthält keine Artikelzeilen.' );
    }
    if ( $force ) {
        return true;
    }
    $prev = (int) ( macoffice_pf_last_run()['rows'] ?? 0 );
    $min  = (float) apply_filters( 'macoffice_pf_min_ratio', MACOFFICE_PF_MIN_RATIO );
    if ( $prev >= MACOFFICE_PF_GUARD_FROM && $rows < (int) floor( $prev * $min ) ) {
        return new WP_Error(
            'shrink',
            sprintf(
                'Die Datei enthält nur %d Artikelzeilen, der letzte Import hatte %d. Das sieht nach einer unvollständigen Datei aus (fehlende Artikel würden gelöscht). Mit "Trotzdem übernehmen" bzw. --force bestätigen, falls das stimmt.',
                $rows,
                $prev
            )
        );
    }
    return true;
}

// ── Datei übernehmen (Backend-Upload und URL-Abruf) ──────────────────────────

/**
 * Geprüfte Datei atomar als Ablage-Datei speichern.
 *
 * @param string $src    Quelldatei (Upload-Temp oder Download)
 * @param string $name   Ursprünglicher Dateiname (für die Endung)
 * @param string $source 'upload' | 'url'
 * @return array|WP_Error Zustand (rows, sha, ...)
 */
function macoffice_pf_accept_file( string $src, string $name, string $source, bool $force = false, string $user = '' ) {
    $target = macoffice_pf_file();
    $want   = strtolower( pathinfo( $target, PATHINFO_EXTENSION ) );
    $got    = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    if ( $got !== $want ) {
        return new WP_Error( 'type', sprintf( 'Bitte eine .%s-Datei verwenden (der Import ist auf diesen Dateityp eingerichtet).', $want ) );
    }

    // Prüfen auf einer Kopie mit richtiger Endung (Upload-Temp-Dateien haben keine).
    $dir = dirname( $target );
    if ( ! wp_mkdir_p( $dir ) ) {
        return new WP_Error( 'dir', 'Ablageordner kann nicht angelegt werden.' );
    }
    $tmp = $dir . '/.incoming-' . wp_generate_password( 8, false ) . '.' . $want;
    if ( ! @copy( $src, $tmp ) ) {
        return new WP_Error( 'copy', 'Datei konnte nicht gespeichert werden (Schreibrechte im Ordner wpallimport/files prüfen).' );
    }

    $rows = macoffice_pf_count_rows( $tmp );
    if ( is_wp_error( $rows ) ) {
        @unlink( $tmp );
        return $rows;
    }
    $ok = macoffice_pf_check_rows( $rows, $force );
    if ( is_wp_error( $ok ) ) {
        @unlink( $tmp );
        return $ok;
    }

    $sha = hash_file( 'sha256', $tmp );
    if ( ! @rename( $tmp, $target ) ) { // gleicher Ordner => atomar; ein laufender Import sieht nie eine halbe Datei
        @unlink( $tmp );
        return new WP_Error( 'rename', 'Datei konnte nicht an die Ablage-Stelle verschoben werden.' );
    }
    clearstatcache( true, $target );

    $state = array(
        'time'   => time(),
        'rows'   => $rows,
        'sha'    => $sha,
        'source' => $source,
        'user'   => $user,
        'name'   => $name,
    );
    update_option( 'macoffice_pf_state', $state, false );
    return $state;
}

/**
 * Feed von einer URL holen (Option macoffice_pf_url, Filter macoffice_product_feed_url oder --url).
 * Zugangsdaten optional per Konstanten in der wp-config.php: MACOFFICE_FEED_USER / MACOFFICE_FEED_PASS.
 *
 * @return array|WP_Error|null null = keine URL eingerichtet
 */
function macoffice_pf_fetch( string $url = '', bool $force = false ) {
    $url = $url ?: (string) apply_filters( 'macoffice_product_feed_url', (string) get_option( 'macoffice_pf_url', '' ) );
    if ( '' === $url ) {
        return null;
    }
    if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) && ! apply_filters( 'macoffice_pf_allow_insecure_url', false ) ) {
        return new WP_Error( 'url', 'Feed-URL muss https:// verwenden.' );
    }
    $args = array( 'timeout' => 300, 'stream' => true, 'filename' => wp_tempnam( 'macoffice-feed' ), 'redirection' => 3 );
    if ( defined( 'MACOFFICE_FEED_USER' ) && defined( 'MACOFFICE_FEED_PASS' ) ) {
        $args['headers'] = array( 'Authorization' => 'Basic ' . base64_encode( MACOFFICE_FEED_USER . ':' . MACOFFICE_FEED_PASS ) );
    }
    $res = wp_remote_get( $url, $args );
    if ( is_wp_error( $res ) ) {
        @unlink( $args['filename'] );
        return $res;
    }
    $code = (int) wp_remote_retrieve_response_code( $res );
    if ( 200 !== $code ) {
        @unlink( $args['filename'] );
        return new WP_Error( 'http', sprintf( 'Abruf fehlgeschlagen (HTTP %d).', $code ) );
    }
    // Name der Ablage-Datei verwenden: der Name in der URL ist oft ein Skript (feed.php?...).
    $result = macoffice_pf_accept_file( $args['filename'], basename( macoffice_pf_file() ), 'url', $force, 'cron' );
    @unlink( $args['filename'] );
    return $result;
}

// ── Lauf (Cron / WP-CLI) ─────────────────────────────────────────────────────

/** ID des WP-All-Import-Imports: explizit, per Option oder der neueste Import mit dem Dateinamen der Ablage-Datei. */
function macoffice_pf_import_id( int $explicit = 0 ): int {
    global $wpdb;
    if ( $explicit > 0 ) {
        return $explicit;
    }
    $opt = (int) get_option( 'macoffice_pf_import_id', 0 );
    if ( $opt > 0 ) {
        return $opt;
    }
    $name = basename( macoffice_pf_file() );
    return (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$wpdb->prefix}pmxi_imports WHERE name = %s OR path LIKE %s ORDER BY id DESC LIMIT 1", $name, '%' . $wpdb->esc_like( $name ) ) );
}

/**
 * Hängengebliebene Merker eines früheren Laufs zurücksetzen, sonst verweigert WP All Import jeden weiteren Lauf
 * ("Import already running"). Die eigenen Läufe sind durch die Sperre ausgeschlossen: sind die Merker trotzdem gesetzt,
 * ist entweder ein früherer Lauf abgestürzt oder der Import läuft gerade im Browser. WP All Import schreibt keinen
 * verlässlichen "Lebenszeichen"-Zeitstempel, daher wird der Fortschrittszähler (imported) zweimal im Abstand gelesen:
 * bewegt er sich, läuft der Import wirklich.
 *
 * @return string 'free' | 'running' | 'reset'
 */
function macoffice_pf_release_stale( int $import_id ): string {
    global $wpdb;
    $table = $wpdb->prefix . 'pmxi_imports';
    $read  = static function () use ( $wpdb, $table, $import_id ) {
        return $wpdb->get_row( $wpdb->prepare( "SELECT triggered, executing, processing, imported, iteration FROM {$table} WHERE id = %d", $import_id ), ARRAY_A );
    };
    $a = $read();
    if ( ! $a || ( ! (int) $a['triggered'] && ! (int) $a['executing'] ) ) {
        return 'free';
    }
    $wait = (int) apply_filters( 'macoffice_pf_activity_wait', 60 );
    if ( $wait > 0 ) {
        sleep( $wait );
    }
    $b = $read();
    if ( ! $b || ( ! (int) $b['triggered'] && ! (int) $b['executing'] ) ) {
        return 'free'; // zwischenzeitlich regulär beendet
    }
    if ( $a['imported'] !== $b['imported'] || $a['iteration'] !== $b['iteration'] ) {
        return 'running';
    }
    $wpdb->update( $table, array( 'triggered' => 0, 'executing' => 0, 'processing' => 0 ), array( 'id' => $import_id ) );
    return 'reset';
}

/**
 * Einen Durchlauf ausführen. Gibt ein Array mit 'status' zurück:
 *  skipped-unchanged | skipped-running | skipped-nofile | ok | error (+ 'message').
 */
function macoffice_pf_run( array $opts = array() ): array {
    $opts = $opts + array( 'import' => 0, 'fetch' => false, 'url' => '', 'force' => false, 'dry' => false, 'runner' => null );

    // Sperre: nie zwei Läufe gleichzeitig (Cron-Überschneidung, manueller Start).
    $dir = macoffice_pf_dir();
    wp_mkdir_p( $dir );
    $lock = @fopen( $dir . '/.macoffice-feed.lock', 'c' );
    if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) {
        return array( 'status' => 'skipped-running', 'message' => 'Ein anderer Lauf ist noch aktiv.' );
    }

    try {
        if ( $opts['fetch'] ) {
            $fetched = macoffice_pf_fetch( (string) $opts['url'], (bool) $opts['force'] );
            if ( is_wp_error( $fetched ) ) {
                return array( 'status' => 'error', 'message' => 'Abruf: ' . $fetched->get_error_message() );
            }
        }

        $file = macoffice_pf_file();
        if ( ! is_file( $file ) ) {
            return array( 'status' => 'skipped-nofile', 'message' => 'Keine Feed-Datei vorhanden: ' . $file );
        }

        $sha  = hash_file( 'sha256', $file );
        $last = macoffice_pf_last_run();
        if ( ! $opts['force'] && ( $last['sha'] ?? '' ) === $sha ) {
            return array( 'status' => 'skipped-unchanged', 'message' => 'Datei unverändert seit dem letzten Import.' );
        }

        // Datei prüfen (auch SFTP-Ablagen kommen hier vorbei) – schützt vor halben Dateien.
        $rows = macoffice_pf_count_rows( $file );
        if ( is_wp_error( $rows ) ) {
            return array( 'status' => 'error', 'message' => $rows->get_error_message() );
        }
        $ok = macoffice_pf_check_rows( $rows, (bool) $opts['force'] );
        if ( is_wp_error( $ok ) ) {
            return array( 'status' => 'error', 'message' => $ok->get_error_message() );
        }
        // Noch im Upload? Eine Datei, die sich in den letzten Sekunden geändert hat, beim nächsten Durchlauf nehmen.
        clearstatcache( true, $file );
        if ( ! $opts['force'] && ( time() - (int) filemtime( $file ) ) < (int) apply_filters( 'macoffice_pf_settle_seconds', 60 ) ) {
            return array( 'status' => 'skipped-running', 'message' => 'Datei wurde gerade erst geschrieben, wird beim nächsten Durchlauf verarbeitet.' );
        }

        $import_id = macoffice_pf_import_id( (int) $opts['import'] );
        if ( $import_id < 1 ) {
            return array( 'status' => 'error', 'message' => 'Kein WP-All-Import-Import gefunden. Mit --import=<ID> angeben oder zuerst `wp macoffice product-feed setup` ausführen.' );
        }

        $flag = macoffice_pf_release_stale( $import_id );
        if ( 'running' === $flag ) {
            return array( 'status' => 'skipped-running', 'message' => 'Der Import läuft gerade (z. B. im Browser gestartet).', 'import' => $import_id );
        }

        if ( $opts['dry'] ) {
            return array( 'status' => 'ok', 'message' => sprintf( 'Trockenlauf: würde Import %d mit %d Zeilen starten.', $import_id, $rows ), 'import' => $import_id, 'rows' => $rows, 'reset' => 'reset' === $flag );
        }

        $started = time();
        $res     = is_callable( $opts['runner'] ) ? call_user_func( $opts['runner'], $import_id ) : macoffice_pf_wpcli_run( $import_id );
        if ( 0 !== (int) ( $res['code'] ?? 1 ) ) {
            return array( 'status' => 'error', 'message' => 'WP All Import meldet einen Fehler: ' . trim( (string) ( $res['stderr'] ?? '' ) . ' ' . (string) ( $res['stdout'] ?? '' ) ), 'import' => $import_id );
        }

        update_option(
            'macoffice_pf_last_run',
            array(
                'time'     => time(),
                'duration' => time() - $started,
                'sha'      => $sha,
                'rows'     => $rows,
                'import'   => $import_id,
            ),
            false
        );
        return array( 'status' => 'ok', 'message' => sprintf( 'Import %d fertig (%d Zeilen, %d s).', $import_id, $rows, time() - $started ), 'import' => $import_id, 'rows' => $rows, 'reset' => 'reset' === $flag );
    } finally {
        flock( $lock, LOCK_UN );
        fclose( $lock );
    }
}

/** WP All Import per WP-CLI starten (eigener Prozess, kein Zeitlimit, kein Browser). */
function macoffice_pf_wpcli_run( int $import_id ): array {
    if ( ! class_exists( 'WP_CLI' ) ) {
        return array( 'code' => 1, 'stderr' => 'Nur per WP-CLI möglich.' );
    }
    $r = WP_CLI::runcommand( 'all-import run ' . $import_id, array( 'return' => 'all', 'exit_error' => false ) );
    return array( 'code' => (int) $r->return_code, 'stdout' => (string) $r->stdout, 'stderr' => (string) $r->stderr );
}

// ── WP-CLI ───────────────────────────────────────────────────────────────────

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    class Macoffice_Product_Feed_CLI {

        /**
         * Feed prüfen und WP All Import starten, wenn sich die Datei geändert hat.
         *
         * ## OPTIONS
         *
         * [--import=<id>]
         * : ID des WP-All-Import-Imports (Standard: automatisch anhand des Dateinamens).
         *
         * [--fetch]
         * : Vorher die Datei von der eingerichteten URL holen (Option macoffice_pf_url).
         *
         * [--url=<url>]
         * : Feed-URL für diesen Lauf (https).
         *
         * [--force]
         * : Auch bei unveränderter Datei importieren und den Schutz vor kleinen Dateien übergehen.
         *
         * [--dry-run]
         * : Nur prüfen, nichts starten.
         *
         * ## EXAMPLES
         *
         *     wp macoffice product-feed run
         *     wp macoffice product-feed run --fetch
         *
         * @when after_wp_load
         */
        public function run( $args, $assoc ) {
            $r = macoffice_pf_run(
                array(
                    'import' => (int) ( $assoc['import'] ?? 0 ),
                    'fetch'  => isset( $assoc['fetch'] ),
                    'url'    => (string) ( $assoc['url'] ?? '' ),
                    'force'  => isset( $assoc['force'] ),
                    'dry'    => isset( $assoc['dry-run'] ),
                )
            );
            if ( ! empty( $r['reset'] ) ) {
                WP_CLI::warning( 'Hängender früherer Lauf wurde zurückgesetzt.' );
            }
            if ( 'error' === $r['status'] ) {
                WP_CLI::error( $r['message'] );
            }
            WP_CLI::log( '[' . $r['status'] . '] ' . $r['message'] );
        }

        /**
         * Bestehenden Import auf die Ablage-Datei umstellen (Standard: nur anzeigen).
         *
         * ## OPTIONS
         *
         * [--import=<id>]
         * : ID des Imports (Standard: neuester Import mit passendem Dateinamen).
         *
         * [--apply]
         * : Änderungen speichern. Die bisherigen Einstellungen werden vorher in der Option macoffice_pf_options_backup_<id> gesichert.
         *
         * @when after_wp_load
         */
        public function setup( $args, $assoc ) {
            global $wpdb;
            $id = macoffice_pf_import_id( (int) ( $assoc['import'] ?? 0 ) );
            if ( $id < 1 ) {
                WP_CLI::error( 'Kein Import gefunden. Mit --import=<ID> angeben.' );
            }
            $table = $wpdb->prefix . 'pmxi_imports';
            $row   = $wpdb->get_row( $wpdb->prepare( "SELECT id, type, path, options FROM {$table} WHERE id = %d", $id ), ARRAY_A );
            if ( ! $row ) {
                WP_CLI::error( "Import $id existiert nicht." );
            }
            $options = maybe_unserialize( $row['options'] );
            if ( ! is_array( $options ) ) {
                WP_CLI::error( 'Import-Einstellungen nicht lesbar.' );
            }

            $want_path = basename( macoffice_pf_file() );
            $changes   = array(
                'Quelle (type)'               => array( $row['type'], 'file' ),
                'Dateipfad (path)'            => array( $row['path'], $want_path ),
                'Selektives Hashing'          => array( (string) ( $options['is_selective_hashing'] ?? '' ), '1' ),
                'Datensätze pro Anfrage'      => array( (string) ( $options['records_per_request'] ?? '' ), '20' ),
            );
            foreach ( $changes as $label => $c ) {
                WP_CLI::log( sprintf( '%-26s %s  ->  %s%s', $label, $c[0] ?: '(leer)', $c[1], (string) $c[0] === (string) $c[1] ? '  (schon so)' : '' ) );
            }
            if ( ! isset( $assoc['apply'] ) ) {
                WP_CLI::log( 'Nichts geändert (Vorschau). Mit --apply speichern.' );
                return;
            }

            update_option( 'macoffice_pf_options_backup_' . $id, array( 'type' => $row['type'], 'path' => $row['path'], 'options' => $row['options'] ), false );
            $options['is_selective_hashing'] = '1';
            $options['records_per_request']  = '20';
            $wpdb->update( $table, array( 'type' => 'file', 'path' => $want_path, 'options' => maybe_serialize( $options ) ), array( 'id' => $id ) );
            update_option( 'macoffice_pf_import_id', $id, false );
            delete_option( 'macoffice_pf_last_run' ); // Hashes ändern sich: der erste Lauf danach verarbeitet alle Artikel einmal

            WP_CLI::success( "Import $id umgestellt. Der erste Lauf danach verarbeitet alle Artikel noch einmal, ab dann nur Änderungen." );
        }

        /**
         * Zustand anzeigen.
         *
         * @when after_wp_load
         */
        public function status( $args, $assoc ) {
            $f = macoffice_pf_file();
            WP_CLI::log( 'Datei:        ' . $f . ( is_file( $f ) ? ' (' . size_format( filesize( $f ) ) . ', ' . wp_date( 'd.m.Y H:i', filemtime( $f ) ) . ')' : ' (fehlt)' ) );
            $last = macoffice_pf_last_run();
            WP_CLI::log( 'Letzter Lauf: ' . ( $last ? wp_date( 'd.m.Y H:i', (int) $last['time'] ) . ', ' . $last['rows'] . ' Zeilen, ' . $last['duration'] . ' s' : '–' ) );
            WP_CLI::log( 'Import-ID:    ' . macoffice_pf_import_id() );
        }
    }
    WP_CLI::add_command( 'macoffice product-feed', 'Macoffice_Product_Feed_CLI' );
}

// ── Backend: Produkte > Artikel-Import ───────────────────────────────────────

if ( is_admin() ) {
    add_action( 'admin_menu', static function (): void {
        add_submenu_page(
            'edit.php?post_type=product',
            __( 'Artikel-Import', 'macoffice' ),
            __( 'Artikel-Import', 'macoffice' ),
            macoffice_pf_capability(),
            'macoffice-product-feed',
            'macoffice_pf_render_admin_page'
        );
    } );

    add_action( 'admin_post_macoffice_pf_upload', static function (): void {
        if ( ! current_user_can( macoffice_pf_capability() ) ) {
            wp_die( esc_html__( 'Keine Berechtigung.', 'macoffice' ), '', array( 'response' => 403 ) );
        }
        check_admin_referer( 'macoffice_pf_upload' );

        $notice = array( 'type' => 'error', 'message' => 'Keine Datei empfangen.' );
        $f      = $_FILES['feed_file'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification
        if ( is_array( $f ) && UPLOAD_ERR_OK === (int) $f['error'] && is_uploaded_file( $f['tmp_name'] ) ) {
            $res = macoffice_pf_accept_file(
                $f['tmp_name'],
                sanitize_file_name( (string) $f['name'] ),
                'upload',
                ! empty( $_POST['confirm_shrink'] ),
                wp_get_current_user()->display_name
            );
            $notice = is_wp_error( $res )
                ? array( 'type' => 'error', 'message' => $res->get_error_message() )
                : array( 'type' => 'success', 'message' => sprintf( 'Datei gespeichert (%d Artikelzeilen). Der Import startet automatisch beim nächsten Durchlauf (in der Regel innerhalb von 15 Minuten).', $res['rows'] ) );
        } elseif ( is_array( $f ) && UPLOAD_ERR_NO_FILE !== (int) $f['error'] ) {
            $notice['message'] = in_array( (int) $f['error'], array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true )
                ? 'Datei ist größer als vom Server erlaubt (' . size_format( wp_max_upload_size() ) . ').'
                : 'Upload fehlgeschlagen (Fehlercode ' . (int) $f['error'] . ').';
        }
        set_transient( 'macoffice_pf_notice_' . get_current_user_id(), $notice, 60 );
        wp_safe_redirect( admin_url( 'edit.php?post_type=product&page=macoffice-product-feed' ) );
        exit;
    } );

    function macoffice_pf_render_admin_page(): void {
        global $wpdb;
        if ( ! current_user_can( macoffice_pf_capability() ) ) {
            return;
        }
        $notice = get_transient( 'macoffice_pf_notice_' . get_current_user_id() );
        if ( $notice ) {
            delete_transient( 'macoffice_pf_notice_' . get_current_user_id() );
        }
        $file  = macoffice_pf_file();
        $state = macoffice_pf_state();
        $last  = macoffice_pf_last_run();
        $id    = macoffice_pf_import_id();
        $imp   = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT triggered, executing, last_activity, `count`, created, updated, skipped, deleted FROM {$wpdb->prefix}pmxi_imports WHERE id = %d", $id ), ARRAY_A ) : null;
        $hist  = $id ? $wpdb->get_row( $wpdb->prepare( "SELECT date, time_run, summary FROM {$wpdb->prefix}pmxi_history WHERE import_id = %d ORDER BY id DESC LIMIT 1", $id ), ARRAY_A ) : null;
        $waiting = is_file( $file ) && ( ! $last || ( $last['sha'] ?? '' ) !== hash_file( 'sha256', $file ) );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Artikel-Import', 'macoffice' ); ?></h1>

            <?php if ( is_array( $notice ) ) : ?>
                <div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
            <?php endif; ?>

            <h2><?php esc_html_e( 'Stand', 'macoffice' ); ?></h2>
            <table class="widefat striped" style="max-width:760px">
                <tbody>
                <tr><th><?php esc_html_e( 'Artikeldatei', 'macoffice' ); ?></th><td>
                    <?php
                    if ( is_file( $file ) ) {
                        echo esc_html( sprintf( '%s, %s, %s', basename( $file ), size_format( filesize( $file ) ), wp_date( 'd.m.Y, H:i', (int) filemtime( $file ) ) ) );
                        if ( ! empty( $state['source'] ) ) {
                            echo esc_html( ' – ' . ( 'url' === $state['source'] ? 'automatisch abgeholt' : 'hochgeladen von ' . $state['user'] ) );
                        }
                    } else {
                        esc_html_e( 'Noch keine Datei vorhanden.', 'macoffice' );
                    }
                    ?>
                </td></tr>
                <tr><th><?php esc_html_e( 'Letzter Import', 'macoffice' ); ?></th><td>
                    <?php
                    if ( $last ) {
                        echo esc_html( sprintf( '%s – %d Artikelzeilen, Dauer %d Min.', wp_date( 'd.m.Y, H:i', (int) $last['time'] ), (int) $last['rows'], max( 1, (int) round( (int) $last['duration'] / 60 ) ) ) );
                        if ( $hist && ! empty( $hist['summary'] ) ) {
                            echo '<br>' . esc_html( $hist['summary'] );
                        }
                    } else {
                        esc_html_e( 'Noch kein automatischer Import.', 'macoffice' );
                    }
                    ?>
                </td></tr>
                <tr><th><?php esc_html_e( 'Status', 'macoffice' ); ?></th><td>
                    <?php
                    if ( $imp && ( (int) $imp['triggered'] || (int) $imp['executing'] ) ) {
                        esc_html_e( 'Import läuft gerade.', 'macoffice' );
                    } elseif ( $waiting ) {
                        esc_html_e( 'Neue Datei wartet auf den nächsten automatischen Durchlauf.', 'macoffice' );
                    } else {
                        esc_html_e( 'Alles aktuell.', 'macoffice' );
                    }
                    ?>
                </td></tr>
                </tbody>
            </table>

            <h2 style="margin-top:2em"><?php esc_html_e( 'Neue Artikeldatei hochladen', 'macoffice' ); ?></h2>
            <p><?php esc_html_e( 'Der Import läuft danach von selbst im Hintergrund – dieses Fenster muss nicht offen bleiben. Normalerweise wird die Datei automatisch bereitgestellt; der Upload ist nur der Ersatzweg.', 'macoffice' ); ?></p>
            <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="macoffice_pf_upload">
                <?php wp_nonce_field( 'macoffice_pf_upload' ); ?>
                <p><input type="file" name="feed_file" accept=".<?php echo esc_attr( strtolower( pathinfo( $file, PATHINFO_EXTENSION ) ) ); ?>" required></p>
                <p><label><input type="checkbox" name="confirm_shrink" value="1"> <?php esc_html_e( 'Trotzdem übernehmen, auch wenn die Datei deutlich weniger Artikel enthält als beim letzten Import (fehlende Artikel werden entfernt!)', 'macoffice' ); ?></label></p>
                <?php submit_button( __( 'Hochladen', 'macoffice' ) ); ?>
            </form>
            <p class="description"><?php echo esc_html( sprintf( 'Maximale Dateigröße laut Server: %s.', size_format( wp_max_upload_size() ) ) ); ?></p>
        </div>
        <?php
    }
}
