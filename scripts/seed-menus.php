<?php
/**
 * macoffice.at – Menüs anlegen und den Theme-Positionen zuweisen (idempotent).
 *
 * Aufruf (aus dem Projekt-Root):
 *   wp --path=cms eval-file scripts/seed-menus.php
 *
 * Struktur wie auf der Live-Seite (Stand 11/2026):
 *   Hauptmenü (primary):           Business, Leistungen, Produkte, FAQs, Über uns, Merkzettel
 *   Footer Rechtliches (footer-legal): AGBs, Impressum, Datenschutz, Fernwartung
 * Ein bereits befülltes Menü wird nicht verändert, nur der Position zugewiesen.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

/**
 * Menü mit Punkten anlegen. Punkt = [ Beschriftung, Seitenpfad ].
 * Existiert die Seite, wird sie verknüpft, sonst entsteht ein Link auf den Pfad.
 */
$build_menu = static function ( string $name, array $items ): int {
	$menu = wp_get_nav_menu_object( $name );
	if ( $menu ) {
		$menu_id = (int) $menu->term_id;
		if ( wp_get_nav_menu_items( $menu_id ) ) {
			WP_CLI::log( "Menü „{$name}“ ist bereits befüllt (nicht verändert)." );
			return $menu_id;
		}
	} else {
		$menu_id = wp_create_nav_menu( $name );
		if ( is_wp_error( $menu_id ) ) {
			WP_CLI::error( $menu_id->get_error_message() );
		}
	}

	foreach ( $items as $position => $item ) {
		list( $label, $path ) = $item;
		$page = get_page_by_path( trim( $path, '/' ) );
		$args = array(
			'menu-item-title'    => $label,
			'menu-item-position' => $position + 1,
			'menu-item-status'   => 'publish',
		);
		if ( $page ) {
			$args += array(
				'menu-item-type'      => 'post_type',
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page->ID,
			);
		} else {
			WP_CLI::warning( "Seite „{$path}“ nicht gefunden, „{$label}“ wird ein normaler Link." );
			$args += array(
				'menu-item-type' => 'custom',
				'menu-item-url'  => home_url( '/' . trim( $path, '/' ) . '/' ),
			);
		}
		wp_update_nav_menu_item( $menu_id, 0, $args );
	}
	WP_CLI::log( "Menü „{$name}“ angelegt (" . count( $items ) . ' Punkte).' );
	return (int) $menu_id;
};

$primary = $build_menu( 'Hauptmenü', array(
	array( 'Business',   'business' ),
	array( 'Leistungen', 'leistungen' ),
	array( 'Produkte',   'produkte' ),
	array( 'FAQs',       'fragen-und-antworten' ),
	array( 'Über uns',   'ueber-uns' ),
	array( 'Merkzettel', 'merkzettel' ),
) );

$legal = $build_menu( 'Footer Rechtliches', array(
	array( 'AGBs',        'agbs' ),
	array( 'Impressum',   'impressum' ),
	array( 'Datenschutz', 'datenschutz' ),
	array( 'Fernwartung', 'fernwartung' ),
) );

$locations                 = get_theme_mod( 'nav_menu_locations', array() );
$locations                 = is_array( $locations ) ? $locations : array();
$locations['primary']      = $primary;
$locations['footer-legal'] = $legal;
set_theme_mod( 'nav_menu_locations', $locations );

WP_CLI::success( 'Menüs zugewiesen: primary → Hauptmenü, footer-legal → Footer Rechtliches.' );
