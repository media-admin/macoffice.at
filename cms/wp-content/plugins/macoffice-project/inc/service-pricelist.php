<?php
/**
 * [shortcode_service_pricelist] – Reparaturpreise nach Gerät.
 *
 * Auswahl über GET-Parameter (verlinkbar, funktioniert ohne JavaScript):
 *   ?cat=<Geräte-Art>&cat2=<Kategorie>&cat3=<Unterkategorie>&device=<Modell>&repair=<Thema>
 *
 * Daten: drei eigene Tabellen (categories, devices, repairs), befüllt aus dem SQL-Export der bisherigen Service-Datenbank:
 *   - im Backend unter Werkzeuge > Servicepreise (Upload der .sql-Datei, siehe service-pricelist-admin.php) oder
 *   - per WP-CLI:  wp macoffice service-prices import <datei.sql>
 *
 * Reparaturbonus (bisherige Regel): bei "repbon" = 1 und Preis > 400 € → 200 €, sonst die Hälfte des Preises (abgerundet).
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const MACOFFICE_SP_DB_VERSION = '1';

function macoffice_sp_table( string $name ): string {
    global $wpdb;
    return $wpdb->prefix . 'macoffice_sp_' . $name;
}

function macoffice_sp_install(): void {
    if ( get_option( 'macoffice_sp_db_version' ) === MACOFFICE_SP_DB_VERSION ) {
        return;
    }

    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();

    dbDelta( "CREATE TABLE " . macoffice_sp_table( 'categories' ) . " (
        id bigint(20) unsigned NOT NULL,
        name varchar(255) NOT NULL DEFAULT '',
        typ tinyint(3) unsigned NOT NULL DEFAULT 1,
        parent bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY typ_parent (typ,parent)
    ) $charset;" );

    dbDelta( "CREATE TABLE " . macoffice_sp_table( 'devices' ) . " (
        id bigint(20) unsigned NOT NULL,
        kat_id bigint(20) unsigned NOT NULL DEFAULT 0,
        name varchar(255) NOT NULL DEFAULT '',
        PRIMARY KEY  (id),
        KEY kat_id (kat_id)
    ) $charset;" );

    dbDelta( "CREATE TABLE " . macoffice_sp_table( 'repairs' ) . " (
        id bigint(20) unsigned NOT NULL,
        device_id bigint(20) unsigned NOT NULL DEFAULT 0,
        name varchar(255) NOT NULL DEFAULT '',
        preis decimal(10,2) NOT NULL DEFAULT 0,
        repbon tinyint(1) NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        KEY device_id (device_id)
    ) $charset;" );

    update_option( 'macoffice_sp_db_version', MACOFFICE_SP_DB_VERSION, false );
}
add_action( 'init', 'macoffice_sp_install', 5 );

// ── Import (WP-CLI) ──────────────────────────────────────────────────────────────

/**
 * Zerlegt eine Zeichenfolge "(a,'b',NULL),(…)" in Zeilen aus Werten.
 *
 * @return array<int,array<int,string|null>>
 */
function macoffice_sp_parse_tuples( string $sql ): array {
    $rows = array();
    $row  = null;
    $len  = strlen( $sql );
    $i    = 0;

    while ( $i < $len ) {
        $c = $sql[ $i ];

        if ( null === $row ) {
            if ( '(' === $c ) {
                $row = array();
                $i++;
                continue;
            }
            if ( ';' === $c ) {
                break;
            }
            $i++;
            continue;
        }

        if ( ' ' === $c || "\n" === $c || "\r" === $c || "\t" === $c || ',' === $c ) {
            $i++;
            continue;
        }
        if ( ')' === $c ) {
            $rows[] = $row;
            $row    = null;
            $i++;
            continue;
        }
        if ( "'" === $c ) {
            $i++;
            $value = '';
            while ( $i < $len ) {
                $ch = $sql[ $i ];
                if ( '\\' === $ch && $i + 1 < $len ) {
                    $n     = $sql[ $i + 1 ];
                    $map   = array( 'n' => "\n", 'r' => "\r", 't' => "\t", '0' => "\0", 'Z' => "\x1a" );
                    $value .= $map[ $n ] ?? $n;
                    $i    += 2;
                    continue;
                }
                if ( "'" === $ch ) {
                    if ( $i + 1 < $len && "'" === $sql[ $i + 1 ] ) {
                        $value .= "'";
                        $i     += 2;
                        continue;
                    }
                    $i++;
                    break;
                }
                $value .= $ch;
                $i++;
            }
            $row[] = $value;
            continue;
        }

        // Zahl, NULL oder anderes Literal bis zum nächsten Trenner
        $start = $i;
        while ( $i < $len && ',' !== $sql[ $i ] && ')' !== $sql[ $i ] ) {
            $i++;
        }
        $literal = trim( substr( $sql, $start, $i - $start ) );
        $row[]   = 'NULL' === strtoupper( $literal ) ? null : $literal;
    }

    return $rows;
}

/**
 * Liest INSERTs der Tabellen categories, devices, repairs aus einem SQL-Export.
 *
 * @return array<string,array<int,array<string,string|null>>>
 */
function macoffice_sp_parse_sql( string $sql ): array {
    $wanted  = array( 'categories', 'devices', 'repairs' );
    $columns = array();
    $result  = array_fill_keys( $wanted, array() );

    // Spaltenreihenfolge aus CREATE TABLE (falls INSERT ohne Spaltenliste)
    if ( preg_match_all( '/CREATE TABLE\s+(?:IF NOT EXISTS\s+)?`?(\w+)`?\s*\((.*?)\)\s*(?:ENGINE|DEFAULT|;)/is', $sql, $creates, PREG_SET_ORDER ) ) {
        foreach ( $creates as $create ) {
            $table = strtolower( $create[1] );
            if ( ! in_array( $table, $wanted, true ) ) {
                continue;
            }
            foreach ( preg_split( '/\R/', $create[2] ) as $line ) {
                if ( preg_match( '/^\s*`(\w+)`\s+/', $line, $m ) ) {
                    $columns[ $table ][] = $m[1];
                }
            }
        }
    }

    if ( ! preg_match_all( '/INSERT\s+INTO\s+`?(\w+)`?\s*(\(([^)]*)\))?\s*VALUES\s*(.*?);\s*(?=\R|$)/is', $sql, $inserts, PREG_SET_ORDER ) ) {
        return $result;
    }

    foreach ( $inserts as $insert ) {
        $table = strtolower( $insert[1] );
        if ( ! in_array( $table, $wanted, true ) ) {
            continue;
        }
        $cols = array();
        if ( ! empty( $insert[3] ) ) {
            $cols = array_map( static fn( $c ) => trim( $c, " `\t" ), explode( ',', $insert[3] ) );
        } elseif ( ! empty( $columns[ $table ] ) ) {
            $cols = $columns[ $table ];
        }
        if ( ! $cols ) {
            continue;
        }
        foreach ( macoffice_sp_parse_tuples( $insert[4] . ';' ) as $values ) {
            if ( count( $values ) !== count( $cols ) ) {
                continue;
            }
            $result[ $table ][] = array_combine( $cols, $values );
        }
    }

    return $result;
}

/**
 * Ersetzt den Datenbestand durch den Inhalt der Datei. Gibt die Anzahl je Tabelle zurück.
 *
 * @return array<string,int>|WP_Error
 */
function macoffice_sp_import_file( string $file ) {
    if ( ! is_readable( $file ) ) {
        return new WP_Error( 'unreadable', 'Datei nicht lesbar: ' . $file );
    }

    $data = macoffice_sp_parse_sql( (string) file_get_contents( $file ) );
    if ( ! $data['categories'] && ! $data['devices'] && ! $data['repairs'] ) {
        return new WP_Error( 'empty', 'Keine INSERT-Daten für categories, devices oder repairs gefunden.' );
    }

    macoffice_sp_install();
    global $wpdb;

    $counts = array();
    $wpdb->query( 'START TRANSACTION' );

    foreach ( array( 'categories', 'devices', 'repairs' ) as $table ) {
        $wpdb->query( 'DELETE FROM ' . macoffice_sp_table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL
        $counts[ $table ] = 0;

        foreach ( $data[ $table ] as $row ) {
            switch ( $table ) {
                case 'categories':
                    $record = array(
                        'id'     => (int) $row['id'],
                        'name'   => (string) ( $row['name'] ?? '' ),
                        'typ'    => (int) ( $row['typ'] ?? 1 ),
                        'parent' => (int) ( $row['parent'] ?? 0 ),
                    );
                    break;
                case 'devices':
                    $record = array(
                        'id'     => (int) $row['id'],
                        'kat_id' => (int) ( $row['kat_id'] ?? 0 ),
                        'name'   => (string) ( $row['name'] ?? '' ),
                    );
                    break;
                default:
                    $record = array(
                        'id'        => (int) $row['id'],
                        'device_id' => (int) ( $row['device_id'] ?? 0 ),
                        'name'      => (string) ( $row['name'] ?? '' ),
                        'preis'     => (float) str_replace( ',', '.', (string) ( $row['preis'] ?? 0 ) ),
                        'repbon'    => (int) ( $row['repbon'] ?? 0 ),
                    );
            }
            if ( false === $wpdb->insert( macoffice_sp_table( $table ), $record ) ) {
                // Eine fehlerhafte Zeile bricht den ganzen Import ab, die bisherigen Daten bleiben erhalten
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'insert_failed', sprintf( 'Import abgebrochen (Tabelle %s, ID %d): %s', $table, (int) $row['id'], $wpdb->last_error ) );
            }
            $counts[ $table ]++;
        }
    }

    $wpdb->query( 'COMMIT' );

    return $counts;
}

/** Aktuelle Anzahl der Datensätze je Tabelle. */
function macoffice_sp_counts(): array {
    global $wpdb;
    macoffice_sp_install();
    $counts = array();
    foreach ( array( 'categories', 'devices', 'repairs' ) as $table ) {
        $counts[ $table ] = (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . macoffice_sp_table( $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    }
    return $counts;
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    /**
     * Servicepreise (Preisliste auf der Seite "Leistungen").
     */
    class Macoffice_Service_Prices_CLI {
        /**
         * Importiert categories, devices und repairs aus einem SQL-Export (ersetzt vorhandene Daten).
         *
         * ## EXAMPLES
         *
         *     wp macoffice service-prices import ~/Downloads/macoffice-service-db.sql
         *
         * @param array $args Positionsargumente.
         */
        public function import( $args ) {
            $result = macoffice_sp_import_file( (string) ( $args[0] ?? '' ) );
            if ( is_wp_error( $result ) ) {
                WP_CLI::error( $result->get_error_message() );
            }
            WP_CLI::success( sprintf( 'Importiert: %d Kategorien, %d Geräte, %d Reparaturen.', $result['categories'], $result['devices'], $result['repairs'] ) );
        }
    }
    WP_CLI::add_command( 'macoffice service-prices', 'Macoffice_Service_Prices_CLI' );
}

if ( is_admin() ) {
    require_once __DIR__ . '/service-pricelist-admin.php';
}

// ── Ausgabe ──────────────────────────────────────────────────────────────────────

/**
 * Reparaturbonus in Euro (bisherige Regel).
 */
function macoffice_sp_repair_bonus( float $price ): int {
    return $price > 400 ? 200 : (int) floor( $price / 2 );
}

/**
 * @return array<int,object>
 */
function macoffice_sp_categories( int $type, int $parent = 0 ): array {
    global $wpdb;
    $table = macoffice_sp_table( 'categories' );

    if ( 1 === $type ) {
        return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM $table WHERE typ = %d ORDER BY id ASC", $type ) ); // phpcs:ignore WordPress.DB.PreparedSQL
    }
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM $table WHERE typ = %d AND parent = %d ORDER BY id DESC", $type, $parent ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * @return array<int,object>
 */
function macoffice_sp_devices( int $category ): array {
    global $wpdb;
    $table = macoffice_sp_table( 'devices' );
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name FROM $table WHERE kat_id = %d ORDER BY id DESC", $category ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * @return array<int,object>
 */
function macoffice_sp_repairs( int $device ): array {
    global $wpdb;
    $table = macoffice_sp_table( 'repairs' );
    return (array) $wpdb->get_results( $wpdb->prepare( "SELECT id, name, preis, repbon FROM $table WHERE device_id = %d ORDER BY id ASC", $device ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

function macoffice_sp_repair( int $id ): ?object {
    global $wpdb;
    $table = macoffice_sp_table( 'repairs' );
    return $wpdb->get_row( $wpdb->prepare( "SELECT id, name, preis, repbon FROM $table WHERE id = %d", $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL
}

/**
 * Auswahl aus den Abfrageparametern (cat, cat2, cat3, device, repair).
 *
 * @param array<string,mixed> $source z. B. $_GET
 * @return array{cat:int,cat2:int,cat3:int,device:int,repair:int}
 */
function macoffice_sp_selection( array $source ): array {
    $sel = array();
    foreach ( array( 'cat', 'cat2', 'cat3', 'device', 'repair' ) as $key ) {
        $sel[ $key ] = isset( $source[ $key ] ) ? absint( wp_unslash( $source[ $key ] ) ) : 0;
    }
    return $sel;
}

/**
 * Inhalt des Rechners für eine Auswahl. Die Links funktionieren ohne JavaScript (Seite lädt neu, springt zum Abschnitt);
 * mit JavaScript lädt assets/src/js/components/service-prices.js denselben Inhalt per AJAX nach.
 *
 * @param array{cat:int,cat2:int,cat3:int,device:int,repair:int} $sel
 */
function macoffice_sp_render( array $sel, string $base ): string {
    $cat    = $sel['cat'];
    $cat2   = $sel['cat2'];
    $cat3   = $sel['cat3'];
    $device = $sel['device'];
    $repair = $sel['repair'];

    $url = static function ( array $args, string $anchor ) use ( $base ): string {
        return esc_url( add_query_arg( array_filter( $args ), $base ) . '#' . $anchor );
    };

    $level = static function ( string $title, string $class, array $items, int $selected, callable $href ): string {
        if ( ! $items ) {
            return '';
        }
        $out = '<div class="service-prices__section service-prices__section--' . esc_attr( $class ) . '"><h3 class="service-prices__heading">' . esc_html( $title ) . '</h3><ul class="service-prices__list">';
        foreach ( $items as $item ) {
            $current = (int) $item->id === $selected;
            $out    .= sprintf(
                '<li><a class="btn btn--outline service-prices__option%s" href="%s"%s>%s</a></li>',
                $current ? ' is-selected' : '',
                $href( (int) $item->id ),
                $current ? ' aria-current="true"' : '',
                esc_html( $item->name )
            );
        }
        return $out . '</ul></div>';
    };

    ob_start();
    // Kopfzeile mit "Zurücksetzen" (immer vorhanden, ohne Auswahl nur unsichtbar: kein Springen des Layouts)
    printf(
        '<div class="service-prices__head"><h2 class="wp-block-heading">Wählen Sie zur Anzeige der Reparaturkosten Ihr Gerät aus:</h2><a class="service-prices__reset%s" href="%s"%s><span aria-hidden="true">↺</span> Zurücksetzen</a></div>',
        $cat ? '' : ' is-hidden',
        esc_url( $base . '#service-prices' ),
        $cat ? '' : ' aria-hidden="true" tabindex="-1"'
    );

    // 1. Geräte-Art
    echo $level( 'Geräte-Art', 'type', macoffice_sp_categories( 1 ), $cat, static fn( $id ) => $url( array( 'cat' => $id ), 'sp-2' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

    echo '<div id="sp-2"></div>';

    // 2. Geräte-Kategorie
    $categories2 = $cat ? macoffice_sp_categories( 2, $cat ) : array();
    echo $level( 'Geräte-Kategorie', 'category', $categories2, $cat2, static fn( $id ) => $url( array( 'cat' => $cat, 'cat2' => $id ), 'sp-3' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

    echo '<div id="sp-3"></div>';

    // 3. Unterkategorie (nur, wenn die Kategorie selbst keine Modelle hat)
    $devices_cat2 = $cat2 ? macoffice_sp_devices( $cat2 ) : array();
    $categories3  = ( $cat2 && ! $devices_cat2 ) ? macoffice_sp_categories( 3, $cat2 ) : array();
    echo $level( 'Geräte-Unterkategorie', 'subcategory', $categories3, $cat3, static fn( $id ) => $url( array( 'cat' => $cat, 'cat2' => $cat2, 'cat3' => $id ), 'sp-4' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

    echo '<div id="sp-4"></div>';

    // 4. Modelle: tiefste gewählte Ebene
    if ( $cat3 ) {
        $models = macoffice_sp_devices( $cat3 );
    } elseif ( $cat2 ) {
        $models = $devices_cat2;
    } elseif ( $cat ) {
        $models = macoffice_sp_devices( $cat );
    } else {
        $models = array();
    }
    echo $level( 'Geräte-Modell', 'model', $models, $device, static fn( $id ) => $url( array( 'cat' => $cat, 'cat2' => $cat2, 'cat3' => $cat3, 'device' => $id ), 'sp-5' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

    echo '<div id="sp-5"></div>';

    // 5. Thema
    $topics = $device ? macoffice_sp_repairs( $device ) : array();
    echo $level( 'Thema', 'topic', $topics, $repair, static fn( $id ) => $url( array( 'cat' => $cat, 'cat2' => $cat2, 'cat3' => $cat3, 'device' => $device, 'repair' => $id ), 'sp-6' ) ); // phpcs:ignore WordPress.Security.EscapeOutput

    echo '<div id="sp-6"></div>';

    // 6. Preis
    $selected = $repair ? macoffice_sp_repair( $repair ) : null;

    // Für das Anfrageformular darunter (JavaScript trägt Gerät und Thema samt Preis ein)
    $device_name = '';
    foreach ( $models as $model ) {
        if ( (int) $model->id === $device ) {
            $device_name = (string) $model->name;
        }
    }
    $repair_label = '';
    if ( $selected ) {
        $selected_price = (float) $selected->preis;
        $repair_label   = sprintf( '%s – %s inkl. USt.', $selected->name, fmod( $selected_price, 1 ) ? '€ ' . number_format_i18n( $selected_price, 2 ) : '€ ' . number_format_i18n( $selected_price, 0 ) . ',-' );
    }
    printf(
        '<div class="service-prices__summary" hidden data-device="%s" data-repair="%s"></div>',
        esc_attr( $device_name ),
        esc_attr( $repair_label )
    );

    if ( $selected ) {
        $price = (float) $selected->preis;
        echo '<div class="service-prices__result">';
        echo '<p class="service-prices__result-label">Preis inkl. USt.:</p>';
        $price_label = fmod( $price, 1 ) ? '€ ' . number_format_i18n( $price, 2 ) : '€ ' . number_format_i18n( $price, 0 ) . ',-';
        echo '<p class="service-prices__result-price">' . esc_html( $price_label ) . '</p>';
        if ( (int) $selected->repbon === 1 ) {
            echo '<p class="service-prices__result-label">Mögliche Reparaturbonus Förderung:</p>';
            echo '<p class="service-prices__result-bonus">' . esc_html( '€ ' . macoffice_sp_repair_bonus( $price ) . ',-' ) . '</p>';
        }
        echo '</div>';
        echo do_shortcode( '[shortcode_repair_instructions]' );
    }

    return (string) ob_get_clean();
}

add_shortcode( 'shortcode_service_pricelist', static function (): string {
    macoffice_sp_install();

    $base = get_permalink() ?: home_url( '/' );
    return sprintf(
        '<div class="service-prices" id="service-prices" data-endpoint="%s" data-base="%s"><div class="service-prices__body" aria-live="polite">%s</div></div>',
        esc_url( admin_url( 'admin-ajax.php' ) ),
        esc_url( $base ),
        macoffice_sp_render( macoffice_sp_selection( $_GET ), $base ) // phpcs:ignore WordPress.Security.NonceVerification, WordPress.Security.EscapeOutput
    );
} );

/**
 * AJAX: nur der Inhalt des Rechners für die gewählte Auswahl (öffentlich, nur lesend).
 */
function macoffice_sp_ajax(): void {
    macoffice_sp_install();

    // Basis-Adresse der Seite: nur eigene Adressen zulassen (Links im Ergebnis zeigen darauf).
    $base = isset( $_GET['base'] ) ? esc_url_raw( wp_unslash( $_GET['base'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    $base = $base ? wp_validate_redirect( $base, home_url( '/' ) ) : home_url( '/' );
    $base = remove_query_arg( array( 'cat', 'cat2', 'cat3', 'device', 'repair' ), $base );

    nocache_headers();
    wp_send_json_success( array( 'html' => macoffice_sp_render( macoffice_sp_selection( $_GET ), $base ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
}
add_action( 'wp_ajax_macoffice_sp', 'macoffice_sp_ajax' );
add_action( 'wp_ajax_nopriv_macoffice_sp', 'macoffice_sp_ajax' );
