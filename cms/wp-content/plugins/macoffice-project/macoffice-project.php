<?php
/**
 * Plugin Name: macoffice Plugin
 * Plugin URI: https://github.com/media-admin/macoffice.at
 * Description: Projektspezifische CPTs, Taxonomien, ACF-Felder und Shortcodes für macoffice.at.
 * Version: 0.1.0
 * Author: Media Lab
 * Author URI: https://www.media-lab.at
 * Text Domain: macoffice
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.0
 * Requires Plugins: media-lab-agency-core
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin Constants
define('MACOFFICE_PROJECT_VERSION', '0.1.0');
define('MACOFFICE_PROJECT_FILE', __FILE__);
define('MACOFFICE_PROJECT_PATH', plugin_dir_path(__FILE__));
define('MACOFFICE_PROJECT_URL', plugin_dir_url(__FILE__));
define('MACOFFICE_PROJECT_BASENAME', plugin_basename(__FILE__));

/**
 * Check if Core Plugin is active
 */
function macoffice_project_check_dependencies() {
    if (!function_exists('medialab_core_version')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo '<strong>macoffice Plugin</strong> requires ';
            echo '<strong>Media Lab Agency Core</strong> plugin to be installed and activated.';
            echo '</p></div>';
        });
        return false;
    }
    return true;
}

/**
 * Initialize Plugin
 */
function macoffice_project_init() {
    // Check dependencies first
    if (!macoffice_project_check_dependencies()) {
        return;
    }
    
    // Load text domain
    load_plugin_textdomain('macoffice', false, dirname(MACOFFICE_PROJECT_BASENAME) . '/languages');
    
    // Load components
    require_once MACOFFICE_PROJECT_PATH . 'inc/custom-post-types.php';
    require_once MACOFFICE_PROJECT_PATH . 'inc/taxonomies.php';
    require_once MACOFFICE_PROJECT_PATH . 'inc/acf-config.php';
    require_once MACOFFICE_PROJECT_PATH . 'inc/content-shortcodes.php';
    require_once MACOFFICE_PROJECT_PATH . 'inc/navigation.php';
    if (class_exists('WooCommerce')) {
        require_once MACOFFICE_PROJECT_PATH . 'inc/shop.php';
    }
    // Artikel-Import (Backend-Seite, WP-Cron, WP-CLI): braucht WP All Import, nicht zwingend WooCommerce beim Laden.
    require_once MACOFFICE_PROJECT_PATH . 'inc/product-feed.php';
}
add_action('plugins_loaded', 'macoffice_project_init', 10);

/**
 * Permalinks einmalig neu schreiben, wenn sich Rewrite-Regeln des Plugins ändern (z. B. Beitragstyp "service":
 * kein Archiv mehr unter /leistungen). Version hochzählen, wenn wieder etwas geändert wird.
 */
add_action('init', function () {
    if (get_option('macoffice_rewrite_version') !== '2') {
        flush_rewrite_rules(false);
        update_option('macoffice_rewrite_version', '2', false);
    }
}, 99);

/**
 * Activation Hook
 */
function macoffice_project_activate() {
    // Check dependencies
    if (!macoffice_project_check_dependencies()) {
        deactivate_plugins(MACOFFICE_PROJECT_BASENAME);
        wp_die(
            '<strong>macoffice Plugin</strong> requires <strong>Media Lab Agency Core</strong> plugin.',
            'Plugin Dependency Check',
            array('back_link' => true)
        );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'macoffice_project_activate');

/**
 * Deactivation Hook
 */
function macoffice_project_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'macoffice_project_deactivate');
