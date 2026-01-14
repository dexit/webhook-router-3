<?php
declare(strict_types=1);

/**
 * Plugin Name: Prograde Oort
 * Description: Unified Webhook & Automation Engine combining Datamachine, Feed Consumer, Path Dispatch, and Custom API logic.
 * Version:           1.1.0
 * Author:            Antigravity
 * Author URI:        https://google.com
 * License:           GPL-2.0-or-later
 * Text Domain:       prograde-oort
 * Requires PHP:      8.2
 */

// Basic Security Check
if (!defined('WPINC')) {
    die;
}

// PHP 8.2+ Check
if (version_compare(PHP_VERSION, '8.2.0', '<')) {
    add_action('admin_notices', function () {
        $message = sprintf(
            /* translators: %s: Current PHP version */
            __('Prograde Oort requires PHP 8.2.0 or higher. Current version: %s', 'prograde-oort'),
            PHP_VERSION
        );
        echo '<div class="error"><p>' . esc_html($message) . '</p></div>';
    });
    return;
}

// Define plugin constants
if (!defined('PROGRADE_OORT_PATH')) {
    define('PROGRADE_OORT_PATH', plugin_dir_path(__FILE__));
}
if (!defined('PROGRADE_OORT_URL')) {
    define('PROGRADE_OORT_URL', plugin_dir_url(__FILE__));
}

// Use Composer Autoloader
if (file_exists(PROGRADE_OORT_PATH . 'vendor/autoload.php')) {
    require_once PROGRADE_OORT_PATH . 'vendor/autoload.php';
}

/**
 * Initialize the plugin after all plugins are loaded.
 */
add_action('plugins_loaded', function () {
    // Load text domain
    load_plugin_textdomain('prograde-oort', false, dirname(plugin_basename(__FILE__)) . '/languages');

    if (class_exists('\ProgradeOort\Core\Bootstrap')) {
        \ProgradeOort\Core\Bootstrap::instance();
    }
});
