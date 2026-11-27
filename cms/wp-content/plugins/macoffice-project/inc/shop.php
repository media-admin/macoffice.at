<?php
/**
 * Shop-Besonderheiten von macoffice.at (aus dem bisherigen Theme übernommen).
 *
 *  - Lieferstatus: Die Lagermenge ist ein Code, keine Stückzahl (0 lagernd, 1 kurzfristig verfügbar,
 *    2 bald/demnächst verfügbar, alles andere auf Anfrage). Der Code kommt aus dem Produktimport.
 *  - Sortierung: eigene Auswahl (Standard nach ID, Verfügbarkeit, Preis, Name).
 *  - "Stand vom" unter der Produktliste.
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// ── Lieferstatus ─────────────────────────────────────────────────────────────────

/**
 * Status eines Produkts: lagernd | kurzfristig | bald | anfrage.
 */
function macoffice_availability_code( $product ): string {
    $stock = $product instanceof WC_Product ? $product->get_stock_quantity() : null;

    if ( null === $stock ) {
        return 'anfrage';
    }

    switch ( (int) $stock ) {
        case 0:
            return 'lagernd';
        case 1:
            return 'kurzfristig';
        case 2:
            return 'bald';
        default:
            return 'anfrage';
    }
}

/**
 * @return array<string,string>
 */
function macoffice_availability_labels(): array {
    return (array) apply_filters( 'macoffice_availability_labels', array(
        'lagernd'     => 'lagernd',
        'kurzfristig' => 'kurzfristig verfügbar',
        'bald'        => 'bald/demnächst verfügbar',
        'anfrage'     => 'auf Anfrage',
    ) );
}

// Text (Produktseite, Produktkarte, Wunschliste)
add_filter( 'woocommerce_get_availability_text', static function ( $text, $product ) {
    $labels = macoffice_availability_labels();
    return $labels[ macoffice_availability_code( $product ) ];
}, 99, 2 );

// CSS-Klasse der Produktseite: kein "Nicht vorrätig"-Rot für den Code 0 ("lagernd")
add_filter( 'woocommerce_get_availability_class', static function ( $class, $product ) {
    $code = macoffice_availability_code( $product );
    return 'lagernd' === $code ? 'in-stock' : 'availability-' . $code;
}, 99, 2 );

// Punkt-Farbe auf der Produktkarte (media-lab-woocommerce, Filter mlw_loop_availability)
add_filter( 'mlw_loop_availability', static function ( $data, $product ) {
    $labels = macoffice_availability_labels();
    $code   = macoffice_availability_code( $product );
    $status = array(
        'lagernd'     => 'in_stock',
        'kurzfristig' => 'in_house',
        'bald'        => 'soon',
        'anfrage'     => 'request',
    );

    return array(
        'label'  => $labels[ $code ],
        'status' => $status[ $code ],
    );
}, 10, 2 );

// ── Sortierung ───────────────────────────────────────────────────────────────────

function macoffice_catalog_orderby_options(): array {
    return array(
        'default_sorting' => 'Standardsortierung',
        'stock_list_asc'  => 'Nach Verfügbarkeit (aufsteigend)',
        'stock_list_desc' => 'Nach Verfügbarkeit (absteigend)',
        'price_list_asc'  => 'Nach Preis (aufsteigend)',
        'price_list_desc' => 'Nach Preis (absteigend)',
        'name_list_asc'   => 'Nach Name (aufsteigend)',
        'name_list_desc'  => 'Nach Name (absteigend)',
    );
}

add_filter( 'woocommerce_catalog_orderby', 'macoffice_catalog_orderby_options', 20 );
add_filter( 'woocommerce_default_catalog_orderby_options', 'macoffice_catalog_orderby_options', 20 );
add_filter( 'woocommerce_default_catalog_orderby', static fn() => 'default_sorting' );

add_filter( 'woocommerce_get_catalog_ordering_args', static function ( $args ) {
    $requested = isset( $_GET['orderby'] ) ? wc_clean( wp_unslash( $_GET['orderby'] ) ) : 'default_sorting'; // phpcs:ignore WordPress.Security.NonceVerification

    switch ( $requested ) {
        case 'default_sorting':
            $args['orderby'] = array( 'ID' => 'ASC' );
            $args['order']   = '';
            unset( $args['meta_key'] );
            break;

        // Verfügbarkeitscode: 0 lagernd … 3 auf Anfrage
        case 'stock_list_asc':
        case 'stock_list_desc':
            $args['orderby']  = array(
                'meta_value_num' => 'stock_list_asc' === $requested ? 'ASC' : 'DESC',
                'title'          => 'ASC',
            );
            $args['order']    = '';
            $args['meta_key'] = '_stock';
            break;

        case 'price_list_asc':
        case 'price_list_desc':
            $args['orderby']  = array(
                'meta_value_num' => 'price_list_asc' === $requested ? 'ASC' : 'DESC',
                'title'          => 'ASC',
            );
            $args['order']    = '';
            $args['meta_key'] = '_price';
            break;

        case 'name_list_asc':
        case 'name_list_desc':
            $args['orderby'] = array( 'title' => 'name_list_asc' === $requested ? 'ASC' : 'DESC' );
            $args['order']   = '';
            unset( $args['meta_key'] );
            break;
    }

    return $args;
} );

// ── "Stand vom" unter der Produktliste ───────────────────────────────────────────

add_action( 'woocommerce_after_shop_loop', static function (): void {
    global $wp_query;

    $latest = 0;
    foreach ( (array) $wp_query->posts as $post ) {
        $latest = max( $latest, (int) get_post_modified_time( 'U', false, $post ) );
    }
    if ( ! $latest ) {
        return;
    }

    printf(
        '<p class="shop-date-modified">%s</p>',
        esc_html( sprintf( 'Stand vom %s', wp_date( 'd.m.Y, H:i', $latest ) ) )
    );
}, 15 );

// ── Merkzettel (Wunschliste des Media-Lab-Plugins) in der Sprache der bisherigen Seite ──

add_filter( 'mlw_wishlist_button_style', static fn() => 'icon_text' );
