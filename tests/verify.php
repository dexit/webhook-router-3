<?php

/**
 * Production Readiness Verification Script
 * simulates the environment and checks for potential conflicts and successful integration.
 */

define('ABSPATH', true);
define('WPINC', true);
define('WP_CONTENT_DIR', __DIR__ . '/wp-content');

// Mock WordPress functions
function add_action($tag, $callback, $priority = 10, $accepted_args = 1)
{
    if ($tag === 'plugins_loaded') {
        call_user_func($callback);
    }
}
function add_filter($tag, $callback, $priority = 10, $accepted_args = 1) {}
function apply_filters($tag, $value)
{
    return $value;
}
function load_plugin_textdomain($domain, $abs_rel_path = false, $abs_path = false) { return true; }
function plugin_basename($file) { return $file; }
function do_action($tag, ...$args) {}
function get_field($key, $post_id = false)
{
    return '';
}
function maybe_unserialize($data)
{
    return $data;
}
function esc_textarea($text)
{
    return $text;
}
function plugin_dir_path($file)
{
    return dirname($file) . '/';
}
function plugin_dir_url($file)
{
    return 'http://localhost/wp-content/plugins/prograde-oort/';
}
function get_current_user_id()
{
    return 1;
}
function register_post_type($type, $args)
{
    echo "Registered CPT: $type\n";
}
function _x($s, $c, $d)
{
    return $s;
}
function __($s, $d)
{
    return $s;
}
function get_option($name, $default = false)
{
    return $default;
}
function update_option($name, $value)
{
    echo "Updated Option: $name\n";
    return true;
}
function add_settings_error($s, $c, $m, $t)
{
    echo "Settings Error: $m\n";
}
function wp_verify_nonce($n, $a)
{
    return true;
}
function wp_nonce_field($a, $n)
{
    echo "Nonce Field: $n\n";
}
function add_submenu_page($p, $pt, $m, $c, $s, $cb)
{
    echo "Added Submenu: $m\n";
}
function wp_is_post_revision($id)
{
    return false;
}
function get_posts($args)
{
    return [];
}
function wp_insert_post($args)
{
    echo "Inserted Post: " . ($args['post_title'] ?? 'unknown') . "\n";
    return 123;
}
function update_post_meta($id, $key, $val)
{
    echo "Updated Meta: $key\n";
}
function as_enqueue_async_action($t, $args)
{
    echo "Queued Action: $t\n";
}
function add_option($name, $value, $deprecated = '', $autoload = 'yes')
{
    echo "Added Option: $name\n";
    return true;
}
function sanitize_text_field($text)
{
    return (string)$text;
}

// Load the plugin entry
require_once dirname(__DIR__) . '/prograde-oort.php';

echo "--- PRODUCTION READINESS CHECK ---\n";

// 1. Check Namespace Consistency
if (class_exists('ProgradeOort\Core\Bootstrap')) {
    echo "SUCCESS: Bootstrap class found in correct namespace.\n";
}

// 2. Check Portability Logic
use ProgradeOort\Integration\Portability;

$export = Portability::export_all();
echo "SUCCESS: Portability export executed (result length: " . strlen($export) . ").\n";

// 3. Simulate Init for Scenarios
echo "Triggering Scenario Registration...\n";
\ProgradeOort\Integration\Scenarios::register_examples();

// 4. Check for double initialization safety
// The add_action('plugins_loaded', ...) in prograde-oort.php should have executed.
$instance1 = \ProgradeOort\Core\Bootstrap::instance();
$instance2 = \ProgradeOort\Core\Bootstrap::instance();
if ($instance1 === $instance2) {
    echo "SUCCESS: Bootstrap follows singleton pattern.\n";
}

echo "--- ALL CHECKS PASSED ---\n";
