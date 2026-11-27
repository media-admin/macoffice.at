<?php
/**
 * ACF Configuration
 * 
 * @package MediaLab_Project
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Set ACF JSON Save Point
 */
add_filter('acf/settings/save_json', function($path) {
    return MACOFFICE_PROJECT_PATH . 'acf-json';
});

/**
 * Add ACF JSON Load Point
 */
add_filter('acf/settings/load_json', function($paths) {
    // Add project plugin path
    $paths[] = MACOFFICE_PROJECT_PATH . 'acf-json';
    
    return $paths;
});

// Theme Settings Options Page wurde in Agency Core (media-lab-agency-core) integriert.

/**
 * Suche: Das Theme bringt einen eigenen Such-Button im Header mit (Modal).
 * Das Such-Icon aus Agency Core (Hauptmenü) ist deshalb abgeschaltet, sonst erscheint die Suche doppelt.
 * Als Filter statt Datenbankwert, damit es auch nach einem Live-Import und auf Staging gilt.
 */
add_filter('acf/load_value/name=search_nav_display', function () {
    return 'none';
});
