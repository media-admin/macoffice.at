<?php
/**
 * macoffice.at – Shop im Katalog-Modus (Anfrage über den Merkzettel statt Kauf), idempotent.
 *
 * Aufruf (aus dem Projekt-Root):
 *   wp --path=cms eval-file scripts/seed-shop.php
 *
 * Entspricht den Einstellungen von YITH Catalog Mode auf der bisherigen Seite (Warenkorb-Buttons überall ausgeblendet,
 * Shop deaktiviert, Preise sichtbar) und benennt die Wunschliste des Media-Lab-Plugins in "Merkzettel" um.
 */

if ( ! defined( 'WP_CLI' ) || ! function_exists( 'update_field' ) ) {
    fwrite( STDERR, "Bitte per WP-CLI ausführen und ACF Pro aktivieren.\n" );
    exit( 1 );
}

$set = static function ( string $name, $value ): void {
    update_field( $name, $value, 'option' );
    WP_CLI::log( sprintf( '  %s = %s', $name, is_bool( $value ) ? ( $value ? '1' : '0' ) : ( is_int( $value ) ? $value : "„{$value}“" ) ) );
};

WP_CLI::log( 'Catalog Mode (media-lab-woocommerce):' );
$set( 'wc_catalog_mode_enabled', true );
$set( 'wc_catalog_mode_hide_prices', false );   // Preise bleiben sichtbar (inkl. MwSt.)
$set( 'wc_catalog_mode_hide_buttons', true );   // kein "Kaufen"/"In den Warenkorb"
$set( 'wc_catalog_mode_hide_cart', true );      // Warenkorb und Kasse sind aus (wie "Shop deaktivieren" bei YITH)

WP_CLI::log( 'Merkzettel (Wunschliste):' );
$set( 'mlw_wishlist_label', 'Merkzettel' );
$set( 'mlw_add_button_label', 'Auf den Merkzettel' );
$set( 'mlw_remove_button_label', 'Vom Merkzettel entfernen' );

WP_CLI::success( 'Shop-Einstellungen gesetzt.' );
