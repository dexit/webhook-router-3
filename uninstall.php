<?php

/**
 * Uninstall script for Prograde Oort
 * Handles complete cleanup of plugin data
 */

// Security: Only run if uninstalling
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Log uninstallation
if (class_exists('ProgradeOort\Log\Logger')) {
    ProgradeOort\Log\Logger::instance()->info(
        'Prograde Oort uninstall initiated',
        ['timestamp' => time()],
        'system'
    );
}

// 1. Delete all endpoint posts
$endpoints = get_posts([
    'post_type' => 'oort_endpoint',
    'posts_per_page' => -1,
    'post_status' => 'any'
]);

foreach ($endpoints as $post) {
    wp_delete_post($post->ID, true); // Force delete, bypass trash
}

// 2. Delete plugin options
$options_to_delete = [
    'prograde_oort_examples_installed',
    'prograde_oort_api_key',
    'prograde_oort_version',
    'prograde_oort_allow_eval',
    // Add any other options here
];

foreach ($options_to_delete as $option) {
    delete_option($option);
}

// 3. Delete post meta patterns (cleanup orphaned meta)
global $wpdb;
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'oort_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE 'route_%'");
$wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_oort_%'");

// 4. Delete log files
$log_dir = defined('WP_CONTENT_DIR')
    ? WP_CONTENT_DIR . '/uploads/prograde-oort-logs/'
    : ABSPATH . 'wp-content/uploads/prograde-oort-logs/';

if (is_dir($log_dir)) {
    $files = glob($log_dir . '*');
    if ($files) {
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    @rmdir($log_dir);
}

// 5. Delete transients
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_oort_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_oort_%'");
$wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_prograde_oort_%'");

// 6. Clean up scheduled events
if (function_exists('as_unschedule_all_actions')) {
    // Clear Action Scheduler tasks
    as_unschedule_all_actions('prograde_oort_process_batch');
    as_unschedule_all_actions('oort_ingestion_task');
    as_unschedule_all_actions('oort_cleanup');
}

// Note: We don't remove the CPT registration or flush rewrite rules here
// as this file runs after the plugin is already deactivated
