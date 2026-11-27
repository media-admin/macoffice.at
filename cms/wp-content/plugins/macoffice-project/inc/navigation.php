<?php
/**
 * Hauptmenü: Im Business-Bereich steht "Privat", sonst "Business".
 *
 * Das Menü enthält den Punkt "Business" (Seite /business). Unter /business und darunter wird er zu "Privat"
 * (Seite /privat); die Umschaltung läuft nur im Hauptmenü (Position primary).
 *
 * @package Macoffice_Project
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function macoffice_is_business_context(): bool {
    $business = get_page_by_path( 'business' );
    if ( ! $business ) {
        return false;
    }

    $current = get_queried_object_id();
    if ( ! is_page() || ! $current ) {
        return false;
    }

    return (int) $current === (int) $business->ID || in_array( (int) $business->ID, array_map( 'intval', get_post_ancestors( $current ) ), true );
}

add_filter( 'wp_nav_menu_objects', static function ( $items, $args ) {
    if ( empty( $args->theme_location ) || 'primary' !== $args->theme_location || ! macoffice_is_business_context() ) {
        return $items;
    }

    $business = get_page_by_path( 'business' );
    $private  = get_page_by_path( 'privat' );
    if ( ! $business || ! $private ) {
        return $items;
    }

    $business_url = trailingslashit( get_permalink( $business ) );

    foreach ( $items as $item ) {
        if ( trailingslashit( (string) $item->url ) !== $business_url ) {
            continue;
        }

        $item->title            = get_the_title( $private );
        $item->url              = get_permalink( $private );
        $item->object_id        = (string) $private->ID;
        $item->current          = false;
        $item->current_item_ancestor = false;
        $item->classes          = array_values( array_diff( (array) $item->classes, array( 'current-menu-item', 'current_page_item', 'current-menu-ancestor', 'current-page-ancestor' ) ) );
    }

    return $items;
}, 20, 2 );
