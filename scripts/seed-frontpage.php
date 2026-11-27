<?php
/**
 * macoffice.at – Startseite anlegen und befüllen (idempotent).
 *
 * Aufruf (aus dem Projekt-Root):
 *   wp --path=cms eval-file scripts/seed-frontpage.php
 *
 * Was passiert:
 *   - Seite „Startseite“ (Slug startseite) anlegen und als Startseite einstellen
 *   - Texte aus den alten „homepage-section“-Beiträgen des Live-Imports in die ACF-Felder
 *     der Startseite übernehmen (Leistungen-Einleitung, FAQ-Einleitung, Über-uns-Text)
 *   - Vier Leistungs-Karten mit Bildern aus dem Theme anlegen
 * Bereits befüllte Felder werden nicht überschrieben.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

// Mediathek-Uploads brauchen einen Administrator.
$admins = get_users( array( 'role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'fields' => 'ID' ) );
if ( $admins ) {
	wp_set_current_user( (int) $admins[0] );
}

if ( ! function_exists( 'update_field' ) || ! acf_get_field( 'field_fp_services' ) ) {
	WP_CLI::error( 'ACF-Feldgruppe „Startseite“ nicht gefunden. Sind ACF Pro und macoffice-project aktiv?' );
}

// ── 1. Seite ────────────────────────────────────────────────────────────────
$page = get_page_by_path( 'startseite' );
if ( $page ) {
	$page_id = (int) $page->ID;
	WP_CLI::log( "Seite „Startseite“ vorhanden (ID {$page_id})." );
} else {
	$page_id = wp_insert_post( array(
		'post_type'   => 'page',
		'post_status' => 'publish',
		'post_title'  => 'Startseite',
		'post_name'   => 'startseite',
	), true );
	if ( is_wp_error( $page_id ) ) {
		WP_CLI::error( $page_id->get_error_message() );
	}
	WP_CLI::log( "Seite „Startseite“ angelegt (ID {$page_id})." );
}
update_option( 'show_on_front', 'page' );
update_option( 'page_on_front', $page_id );

// ── 2. Texte aus den alten Abschnitten ──────────────────────────────────────
global $wpdb;

/** Inhalt eines alten Startseiten-Abschnitts holen (ohne Abstandshalter-Blöcke). */
$old_section = static function ( string $title_like ) use ( $wpdb ) {
	$content = $wpdb->get_var( $wpdb->prepare(
		"SELECT post_content FROM {$wpdb->posts}
		 WHERE post_type = 'homepage-section' AND post_status = 'publish' AND post_title LIKE %s
		 ORDER BY ID ASC LIMIT 1",
		$title_like
	) );
	if ( ! is_string( $content ) || '' === trim( $content ) ) {
		return '';
	}
	$content = preg_replace( '#<!-- wp:spacer.*?<!-- /wp:spacer -->\s*#s', '', $content );
	return trim( $content );
};

$texts = array(
	'field_fp_services_intro' => $old_section( 'Leistungen%' ),
	'field_fp_faq_intro'      => $old_section( 'Fragen%' ),
	'field_fp_about_text'     => $old_section( 'Über uns%' ),
);
foreach ( $texts as $key => $value ) {
	$field = acf_get_field( $key );
	if ( '' === $value ) {
		WP_CLI::log( "Kein alter Text für {$field['name']} gefunden (übersprungen)." );
		continue;
	}
	if ( '' !== (string) get_field( $key, $page_id, false ) ) {
		WP_CLI::log( "{$field['name']} ist bereits befüllt (nicht überschrieben)." );
		continue;
	}
	update_field( $key, $value, $page_id );
	WP_CLI::log( "{$field['name']} aus altem Abschnitt übernommen." );
}

// ── 3. Leistungs-Karten ─────────────────────────────────────────────────────
$existing_rows = get_field( 'field_fp_services', $page_id, false );
if ( ! empty( $existing_rows ) ) {
	WP_CLI::log( 'Leistungs-Karten sind bereits befüllt (nicht überschrieben).' );
} else {
	/** Theme-Bild einmalig in die Mediathek holen, danach wiederverwenden. */
	$import_image = static function ( string $relative, string $alt ) {
		$found = get_posts( array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_key'       => '_macoffice_seed_file',
			'meta_value'     => $relative,
			'posts_per_page' => 1,
			'fields'         => 'ids',
		) );
		if ( $found ) {
			return (int) $found[0];
		}
		$source = get_theme_file_path( 'assets/images/' . $relative );
		if ( ! is_readable( $source ) ) {
			WP_CLI::warning( "Bild fehlt im Theme: {$relative}" );
			return 0;
		}
		$tmp = wp_tempnam( basename( $source ) );
		copy( $source, $tmp );
		$id = media_handle_sideload( array( 'name' => basename( $source ), 'tmp_name' => $tmp ), 0 );
		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			WP_CLI::warning( "Bild {$relative}: " . $id->get_error_message() );
			return 0;
		}
		update_post_meta( $id, '_macoffice_seed_file', $relative );
		update_post_meta( $id, '_wp_attachment_image_alt', $alt );
		return (int) $id;
	};

	$cards = array(
		array( 'Kompetenz trifft Liebe zum Produkt', 'Verkauf',          '/leistungen/#verkauf',          'services/verkauf.webp',          'Bereich Verkauf' ),
		array( 'Reparaturen vom Profi',              'Service Hardware', '/leistungen/#service-hardware', 'services/service-hardware.webp', 'Bereich Service Hardware' ),
		// Anker wie live (mit Tippfehler „serivice“), damit der Link auf der Leistungsseite trifft.
		array( 'Erfahrung nutzbar gemacht',          'Software Service', '/leistungen/#serivice-software', 'services/service-software.webp', 'Bereich Software Service' ),
		array( 'Fachwissen auf Abruf',               'Consulting',       '/leistungen/#consulting',       'services/consulting.webp',       'Bereich Consulting' ),
	);

	$rows = array();
	foreach ( $cards as $card ) {
		$rows[] = array(
			'pretitle' => $card[0],
			'title'    => $card[1],
			'url'      => $card[2],
			'image'    => $import_image( $card[3], $card[4] ),
		);
	}
	update_field( 'field_fp_services', $rows, $page_id );
	WP_CLI::log( count( $rows ) . ' Leistungs-Karten angelegt.' );
}

WP_CLI::success( 'Startseite fertig: ' . get_permalink( $page_id ) );
