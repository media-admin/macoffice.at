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

// ── Produktseite wie auf der bisherigen Seite ────────────────────────────────────
// Reihenfolge: Titel, Art.-Nr., Preis (alter Preis durchgestrichen) mit MwSt.-Hinweis, Lieferstatus, Merkzettel-Button,
// darunter "Produktbeschreibung" mit "Stand vom …". Die Kit-Reihenfolge (Kategorien/SKU oben, Beschreibung unter dem Titel,
// Preis hinter dem Button) wird hier zurückgebaut. Das Kit registriert seine Hooks bei init/20, deshalb init/30.

add_action( 'init', static function (): void {
    if ( class_exists( 'MediaLab_Single_Product_Layout' ) ) {
        remove_action( 'woocommerce_single_product_summary', array( 'MediaLab_Single_Product_Layout', 'move_price' ), 9 );
        remove_action( 'woocommerce_single_product_summary', array( 'MediaLab_Single_Product_Layout', 'render_description' ), 6 );
    }
    // Kategorien und Artikelnummer als Meta-Zeile entfallen (die Live-Seite zeigt nur "Art.-Nr.")
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 1 );
    remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40 );

    add_action( 'woocommerce_single_product_summary', 'macoffice_single_sku', 6 );
    add_action( 'woocommerce_single_product_summary', 'macoffice_single_tax_note', 11 );
    add_action( 'woocommerce_single_product_summary', 'macoffice_single_description', 45 );
}, 30 );

/** "Art.-Nr.: 609063" unter dem Titel. */
function macoffice_single_sku(): void {
    global $product;
    if ( ! $product instanceof WC_Product || '' === (string) $product->get_sku() ) {
        return;
    }
    printf(
        '<p class="product-sku"><span class="product-sku__label">%s</span> %s</p>',
        esc_html__( 'Art.-Nr.:', 'macoffice' ),
        esc_html( $product->get_sku() )
    );
}

/**
 * MwSt.-Hinweis: "inkl. 20 % MwSt." Der Satz kommt aus der Steuerklasse des Produkts (Basis-Steuersatz des Shops),
 * ohne hinterlegten Satz gilt der österreichische Normalsatz (Filter macoffice_default_vat_rate).
 */
function macoffice_single_tax_note(): void {
    global $product;
    if ( ! $product instanceof WC_Product || '' === (string) $product->get_price() || ! $product->is_taxable() ) {
        return;
    }

    $rate  = (float) apply_filters( 'macoffice_default_vat_rate', 20.0 );
    $rates = class_exists( 'WC_Tax' ) ? WC_Tax::get_base_tax_rates( $product->get_tax_class() ) : array();
    if ( $rates ) {
        $first = reset( $rates );
        if ( isset( $first['rate'] ) ) {
            $rate = (float) $first['rate'];
        }
    }

    $incl   = 'incl' === get_option( 'woocommerce_tax_display_shop', 'incl' );
    $number = number_format_i18n( $rate, floor( $rate ) === $rate ? 0 : 1 );

    printf(
        '<p class="price-tax-note">%s</p>',
        esc_html( sprintf( '%s %s %% MwSt.', $incl ? 'inkl.' : 'zzgl.', $number ) )
    );
}

/** "Produktbeschreibung" mit Text und "Stand vom …" (Änderungsdatum des Produkts). */
function macoffice_single_description(): void {
    global $product;
    if ( ! $product instanceof WC_Product ) {
        return;
    }

    $desc = $product->get_description();
    if ( '' === trim( $desc ) ) {
        return;
    }

    echo '<section class="product-description">';
    echo '<h2 class="product-description__title">' . esc_html__( 'Produktbeschreibung', 'macoffice' ) . '</h2>';
    echo '<div class="product-description__text">' . wp_kses_post( wpautop( wptexturize( do_shortcode( $desc ) ) ) ) . '</div>';
    $modified = (int) get_post_modified_time( 'U', false, $product->get_id() );
    if ( $modified ) {
        printf(
            '<p class="product-description__date">%s</p>',
            esc_html( sprintf( 'Stand vom %s', wp_date( 'd.m.Y, H:i', $modified ) ) )
        );
    }
    echo '</section>';
}

// Überschrift der verwandten Produkte wie auf der bisherigen Seite
add_filter( 'woocommerce_product_related_products_heading', static fn() => 'Weitere beliebte Artikel' );
