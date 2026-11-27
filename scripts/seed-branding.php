<?php
/**
 * macoffice: Logos und Kontaktangaben in die Agency-Core-Einstellungen eintragen.
 *
 * Läuft per WP-CLI (idempotent, mehrfaches Ausführen ist unkritisch):
 *   wp --path=cms eval-file scripts/seed-branding.php
 *
 * Importiert die Logos aus dem Theme (assets/images/branding) in die Mediathek und setzt die
 * ACF-Optionen (Logo, Top Header). Header und Footer lesen diese Werte.
 */

if ( ! defined( 'WP_CLI' ) || ! function_exists( 'update_field' ) ) {
    fwrite( STDERR, "Bitte per WP-CLI ausführen und ACF Pro aktivieren.\n" );
    exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

$dir = get_theme_root() . '/macoffice/assets/images/branding/';

/** Bild importieren (oder vorhandenes wiederverwenden) und Attachment-ID liefern. */
$import = static function ( string $file, string $alt ) use ( $dir ): int {
    $existing = get_posts( array(
        'post_type' => 'attachment', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids',
        'meta_key'  => '_macoffice_seed', 'meta_value' => $file,
    ) );
    if ( $existing ) {
        return (int) $existing[0];
    }
    $src = $dir . $file;
    if ( ! file_exists( $src ) ) {
        WP_CLI::error( "Datei fehlt: $src" );
    }
    $tmp = wp_tempnam( $file );
    copy( $src, $tmp );
    $id = media_handle_sideload( array( 'name' => $file, 'tmp_name' => $tmp ), 0 );
    if ( is_wp_error( $id ) ) {
        WP_CLI::error( $id->get_error_message() );
    }
    update_post_meta( $id, '_macoffice_seed', $file );
    update_post_meta( $id, '_wp_attachment_image_alt', $alt );
    return (int) $id;
};

$alt = 'Logo mac)office – Ihr autorisierter Apple-Händler in Wiener Neustadt';

update_field( 'logo_desktop',        $import( 'logo-header-light.svg',        $alt ), 'option' );
update_field( 'logo_desktop_dark',   $import( 'logo-header-dark.svg',         $alt ), 'option' );
update_field( 'logo_mobile',         $import( 'logo-header-light-mobile.svg', $alt ), 'option' );
update_field( 'logo_mobile_dark',    $import( 'logo-header-dark-mobile.svg',  $alt ), 'option' );
update_field( 'logo_desktop_width',  200, 'option' );
update_field( 'logo_mobile_width',   140, 'option' );
update_field( 'dark_mode_enabled',   1,   'option' );
update_field( 'btt_enabled',         1,   'option' );

update_field( 'top_header_enable', 1, 'option' );
update_field( 'top_header_address', array(
    'enable' => 1, 'street' => 'Fischauer Gasse 150', 'city' => '2700 Wiener Neustadt', 'country' => 'Österreich', 'maps_link' => '',
), 'option' );
update_field( 'top_header_hours', array( 'enable' => 1, 'text' => 'Mo - Fr 10 - 18 Uhr | Sa 10 - 13 Uhr' ), 'option' );
update_field( 'top_header_phone', array( 'enable' => 1, 'number' => '+43 2622 85270', 'display' => '+43 2622 85270' ), 'option' );
update_field( 'top_header_email', array( 'enable' => 1, 'address' => 'info@macoffice.at' ), 'option' );
update_field( 'top_header_social', array(
    'enable' => 1, 'facebook' => 'https://www.facebook.com/macsworkeasier', 'instagram' => 'https://www.instagram.com/_macoffice/',
), 'option' );
update_field( 'top_header_style', array( 'background' => 'dark', 'mobile' => 'hide' ), 'option' );

WP_CLI::success( 'Branding und Kontaktangaben eingetragen.' );
