<?php
/**
 * Inhalts-Shortcodes der bisherigen Seite.
 *
 * Die Seiteninhalte (Seiten, Beiträge) enthalten diese Shortcodes noch. Sie behalten ihre Namen, damit der Inhalt
 * unverändert importiert werden kann; die Ausgabe kommt aus dem neuen Theme (Template-Teile) bzw. aus diesem Plugin.
 *
 * Einfache Shortcodes stehen hier, die größeren in eigenen Dateien:
 *   inc/leasing.php            [shortcode_leasing_calculator]
 *   inc/applecare.php          [shortcode_applecare]
 *   inc/service-pricelist.php  [shortcode_service_pricelist], [shortcode_repair_instructions]
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

require_once MACOFFICE_PROJECT_PATH . 'inc/leasing.php';
require_once MACOFFICE_PROJECT_PATH . 'inc/applecare.php';
require_once MACOFFICE_PROJECT_PATH . 'inc/service-pricelist.php';

/**
 * Gibt den Inhalt eines Template-Teils (Theme) als String zurück.
 */
function macoffice_capture_template_part( string $slug, array $args = array() ): string {
    ob_start();
    get_template_part( $slug, null, $args );
    return (string) ob_get_clean();
}

/**
 * Gibt eine Plugin-Vorlage (templates/shortcodes/<name>.php) als String zurück.
 */
function macoffice_capture_plugin_template( string $name, array $vars = array() ): string {
    $file = MACOFFICE_PROJECT_PATH . 'templates/shortcodes/' . $name . '.php';
    if ( ! is_readable( $file ) ) {
        return '';
    }
    ob_start();
    ( static function () use ( $file, $vars ) {
        extract( $vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract
        include $file;
    } )();
    return (string) ob_get_clean();
}

// ── Produktfamilien, FAQ ─────────────────────────────────────────────────────────

add_shortcode( 'shortcode_product_categories', static function (): string {
    return macoffice_capture_template_part( 'template-parts/home/category-tiles' );
} );

add_shortcode( 'shortcode_faqs', static function (): string {
    return macoffice_capture_template_part( 'template-parts/home/faq-list' );
} );

// ── Beitragslisten (Karten) ─────────────────────────────────────────────────────
// Alter Shortcode => Kategorie-Slug

foreach ( array(
    'shortcode_news_posts'     => 'neuheiten',
    'shortcode_featured_posts' => 'lieblingsprodukte',
    'shortcode_business_posts' => 'business',
    'shortcode_private_posts'  => 'private',
    'shortcode_default_posts'  => 'allgemein',
) as $macoffice_tag => $macoffice_category ) {
    add_shortcode( $macoffice_tag, static function () use ( $macoffice_category ): string {
        $query = new WP_Query( array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'category_name'  => $macoffice_category,
            'posts_per_page' => -1,
            'no_found_rows'  => true,
        ) );

        if ( ! $query->have_posts() ) {
            return '';
        }

        $html = '<div class="home-grid home-grid--cards">';
        while ( $query->have_posts() ) {
            $query->the_post();
            $html .= macoffice_capture_template_part( 'template-parts/home/card' );
        }
        $html .= '</div>';
        wp_reset_postdata();

        return $html;
    } );
}

// ── Leistungen (Übersichtskarten mit Apple-Logos) ──────────────────────────────────

add_shortcode( 'shortcode_services_overview', static function (): string {
    return macoffice_capture_plugin_template( 'services-overview' );
} );

// ── Google Maps: Karte mit Einwilligung (Shortcode [google_map] des Agency Core) ──

add_shortcode( 'shortcode_google_maps', static function (): string {
    $embed = 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d19656.77575432525!2d16.209917483926358!3d47.810833882697544!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x476dcbe0da27d3e5%3A0x1c6428c578efbef6!2smac)office!5e0!3m2!1sde!2sat!4v1608589777546!5m2!1sde!2sat';
    return do_shortcode( '[google_map src="' . esc_url( $embed ) . '" title="mac)office, Wiener Neustadt" height="450"]' );
} );

// ── Fehler melden, Kontakt-Button ────────────────────────────────────────────────

add_shortcode( 'shortcode_error_found', static function (): string {
    return macoffice_capture_template_part( 'template-parts/home/error-report' );
} );

/**
 * Datenschutzerklärung: Kontaktdaten des Verantwortlichen (aus den Einstellungen im Agency Core) und E-Mail-Link.
 * Auf der bisherigen Seite war dieser Shortcode nicht registriert und erschien als Text.
 */
add_shortcode( 'shortcode_email_info_button', static function (): string {
    if ( ! function_exists( 'get_field' ) ) {
        return '';
    }
    $address = (array) get_field( 'top_header_address', 'option' );
    $email   = (array) get_field( 'top_header_email', 'option' );
    $phone   = (array) get_field( 'top_header_phone', 'option' );

    $lines = array();
    $lines[] = '<strong>' . esc_html( get_bloginfo( 'name' ) ) . '</strong>';
    if ( ! empty( $address['street'] ) ) {
        $lines[] = esc_html( $address['street'] );
    }
    if ( ! empty( $address['city'] ) ) {
        $lines[] = esc_html( $address['city'] );
    }
    if ( ! empty( $phone['number'] ) ) {
        $lines[] = 'Telefon: <a href="tel:' . esc_attr( preg_replace( '/[^+\d]/', '', $phone['number'] ) ) . '">' . esc_html( $phone['display'] ?? $phone['number'] ) . '</a>';
    }
    if ( ! empty( $email['address'] ) ) {
        $lines[] = 'E-Mail: <a href="mailto:' . esc_attr( antispambot( $email['address'] ) ) . '">' . esc_html( antispambot( $email['address'] ) ) . '</a>';
    }

    return '<p class="content-contact">' . implode( '<br>', $lines ) . '</p>';
} );

// ── Merkzettel: TI WooCommerce Wishlist => Wunschliste im Media-Lab-Plugin ───────────

add_shortcode( 'ti_wishlistsview', static function (): string {
    return shortcode_exists( 'mlw_wishlist_page' ) ? do_shortcode( '[mlw_wishlist_page]' ) : '';
} );

add_shortcode( 'shortcode_repair_instructions', static function (): string {
    return macoffice_capture_plugin_template( 'repair-instructions' );
} );
