<?php
/**
 * Servicepreise: Upload-Seite im Backend (Werkzeuge > Servicepreise).
 *
 * Ersetzt das frühere, ungeschützte PHP-Skript der alten Seite (import.php im Theme, ohne Anmeldung). Die Preise werden wie
 * bisher als SQL-Export der Service-Datenbank (Tabellen categories, devices, repairs) geliefert. Der Import ersetzt alle
 * Daten in einem Schritt (Transaktion): bei einem Fehler bleibt die bisherige Preisliste unverändert.
 *
 * Berechtigung: Filter macoffice_sp_capability (Standard: manage_woocommerce, sonst manage_options).
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const MACOFFICE_SP_PAGE = 'macoffice-service-prices';

function macoffice_sp_capability(): string {
    $default = class_exists( 'WooCommerce' ) ? 'manage_woocommerce' : 'manage_options';
    return (string) apply_filters( 'macoffice_sp_capability', $default );
}

add_action( 'admin_menu', static function (): void {
    add_management_page(
        __( 'Servicepreise', 'macoffice' ),
        __( 'Servicepreise', 'macoffice' ),
        macoffice_sp_capability(),
        MACOFFICE_SP_PAGE,
        'macoffice_sp_render_admin_page'
    );
} );

/**
 * Upload verarbeiten (admin-post). Ergebnis geht über ein kurzlebiges Transient an die Seite zurück.
 */
add_action( 'admin_post_macoffice_sp_import', static function (): void {
    if ( ! current_user_can( macoffice_sp_capability() ) ) {
        wp_die( esc_html__( 'Keine Berechtigung.', 'macoffice' ), '', array( 'response' => 403 ) );
    }
    check_admin_referer( 'macoffice_sp_import' );

    $notice = macoffice_sp_handle_upload();
    set_transient( 'macoffice_sp_notice_' . get_current_user_id(), $notice, 5 * MINUTE_IN_SECONDS );

    wp_safe_redirect( add_query_arg( array( 'page' => MACOFFICE_SP_PAGE ), admin_url( 'tools.php' ) ) );
    exit;
} );

/**
 * @return array{type:string,message:string}
 */
function macoffice_sp_handle_upload(): array {
    $file = $_FILES['sql_file'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification -- Nonce in admin_post geprüft
    if ( ! is_array( $file ) || empty( $file['tmp_name'] ) ) {
        return array( 'type' => 'error', 'message' => 'Es wurde keine Datei hochgeladen.' );
    }
    if ( UPLOAD_ERR_OK !== (int) $file['error'] ) {
        $messages = array(
            UPLOAD_ERR_INI_SIZE  => 'Die Datei ist größer als das Upload-Limit des Servers (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'Die Datei ist zu groß.',
            UPLOAD_ERR_PARTIAL   => 'Die Datei wurde nur teilweise übertragen. Bitte erneut versuchen.',
        );
        return array( 'type' => 'error', 'message' => $messages[ (int) $file['error'] ] ?? 'Upload fehlgeschlagen (Fehlercode ' . (int) $file['error'] . ').' );
    }
    if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
        return array( 'type' => 'error', 'message' => 'Ungültiger Upload.' );
    }

    $name = sanitize_file_name( (string) $file['name'] );
    $ext  = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
    if ( 'sql' !== $ext ) {
        return array( 'type' => 'error', 'message' => 'Nur .sql-Dateien sind erlaubt.' );
    }

    // Sicherheitsnetz: Bricht die neue Liste gegenüber der bisherigen drastisch ein, nur mit ausdrücklicher Bestätigung übernehmen
    $before = macoffice_sp_counts();
    $data   = macoffice_sp_parse_sql( (string) file_get_contents( $file['tmp_name'] ) );
    $new    = array_map( 'count', $data );
    if ( $before['repairs'] >= 20 && $new['repairs'] < $before['repairs'] * 0.5 && empty( $_POST['confirm_shrink'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
        return array(
            'type'    => 'error',
            'message' => sprintf(
                'Die Datei enthält nur %d Reparaturpreise, die aktuelle Liste hat %d. Es wurde nichts geändert. Wenn das richtig ist, die Bestätigung unten ankreuzen und erneut hochladen.',
                $new['repairs'],
                $before['repairs']
            ),
        );
    }

    $result = macoffice_sp_import_file( $file['tmp_name'] );
    if ( is_wp_error( $result ) ) {
        return array( 'type' => 'error', 'message' => $result->get_error_message() . ' Die bisherige Preisliste ist unverändert.' );
    }

    update_option(
        'macoffice_sp_last_import',
        array(
            'time'   => time(),
            'user'   => wp_get_current_user()->display_name,
            'file'   => $name,
            'counts' => $result,
        ),
        false
    );

    return array(
        'type'    => 'success',
        'message' => sprintf( 'Import erfolgreich: %d Kategorien, %d Geräte, %d Reparaturpreise.', $result['categories'], $result['devices'], $result['repairs'] ),
    );
}

function macoffice_sp_render_admin_page(): void {
    if ( ! current_user_can( macoffice_sp_capability() ) ) {
        return;
    }

    $notice = get_transient( 'macoffice_sp_notice_' . get_current_user_id() );
    if ( $notice ) {
        delete_transient( 'macoffice_sp_notice_' . get_current_user_id() );
    }
    $counts = macoffice_sp_counts();
    $last   = get_option( 'macoffice_sp_last_import' );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Servicepreise', 'macoffice' ); ?></h1>

        <?php if ( is_array( $notice ) ) : ?>
            <div class="notice notice-<?php echo 'success' === $notice['type'] ? 'success' : 'error'; ?>"><p><?php echo esc_html( $notice['message'] ); ?></p></div>
        <?php endif; ?>

        <p>
            <?php
            printf(
                /* translators: 1: categories, 2: devices, 3: repairs */
                esc_html__( 'Aktuell gespeichert: %1$d Kategorien, %2$d Geräte, %3$d Reparaturpreise.', 'macoffice' ),
                (int) $counts['categories'],
                (int) $counts['devices'],
                (int) $counts['repairs']
            );
            if ( is_array( $last ) ) {
                echo ' ';
                printf(
                    /* translators: 1: date, 2: user, 3: file name */
                    esc_html__( 'Letzter Import: %1$s von %2$s (%3$s).', 'macoffice' ),
                    esc_html( wp_date( 'd.m.Y, H:i', (int) $last['time'] ) ),
                    esc_html( (string) $last['user'] ),
                    esc_html( (string) $last['file'] )
                );
            }
            ?>
        </p>

        <h2><?php esc_html_e( 'Neue Preisliste importieren', 'macoffice' ); ?></h2>
        <p><?php esc_html_e( 'SQL-Export der Service-Datenbank auswählen (Tabellen categories, devices und repairs). Der Import ersetzt die gesamte Preisliste. Bei einem Fehler bleibt die bisherige Liste unverändert.', 'macoffice' ); ?></p>

        <form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <input type="hidden" name="action" value="macoffice_sp_import">
            <?php wp_nonce_field( 'macoffice_sp_import' ); ?>
            <p><input type="file" name="sql_file" accept=".sql" required></p>
            <p>
                <label>
                    <input type="checkbox" name="confirm_shrink" value="1">
                    <?php esc_html_e( 'Auch übernehmen, wenn die neue Liste deutlich weniger Reparaturpreise enthält als die bisherige', 'macoffice' ); ?>
                </label>
            </p>
            <?php submit_button( __( 'Importieren', 'macoffice' ) ); ?>
        </form>

        <p class="description">
            <?php
            printf(
                /* translators: %s: maximum upload size */
                esc_html__( 'Maximale Dateigröße laut Server: %s.', 'macoffice' ),
                esc_html( size_format( wp_max_upload_size() ) )
            );
            ?>
        </p>
    </div>
    <?php
}
