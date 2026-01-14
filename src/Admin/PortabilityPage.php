<?php

namespace ProgradeOort\Admin;

use ProgradeOort\Integration\Portability;

/**
 * Admin UI for Import/Export settings
 */
class PortabilityPage
{
    private static $instance = null;

    public static function instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_init', [$this, 'handle_actions']);
    }

    public function add_menu()
    {
        add_submenu_page(
            'prograde-oort',
            'Portability',
            'Portability',
            'manage_options',
            'oort-portability',
            [$this, 'render_page']
        );
    }

    public function handle_actions()
    {
        // Must be logged in
        if (!is_user_logged_in()) {
            return;
        }

        // Must have permission
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions', 'prograde-oort'));
        }

        // Check if this is our form submission
        if (!isset($_POST['oort_portability_nonce'])) {
            return;
        }

        // Verify nonce and referer
        check_admin_referer('oort_portability_action', 'oort_portability_nonce');

        // Handle import
        if (isset($_POST['oort_import'])) {
            $json = wp_unslash($_POST['import_data'] ?? '');

            // Validate input is not empty
            if (empty($json)) {
                add_settings_error(
                    'oort_portability',
                    'empty',
                    __('Import data cannot be empty', 'prograde-oort'),
                    'error'
                );
                return;
            }

            // Size limit check (2 MB)
            if (strlen($json) > 2 * MB_IN_BYTES) {
                add_settings_error(
                    'oort_portability',
                    'size',
                    __('Import data is too large (max 2MB)', 'prograde-oort'),
                    'error'
                );
                return;
            }

            // Validate JSON format
            $decoded = json_decode($json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                add_settings_error(
                    'oort_portability',
                    'invalid_json',
                    sprintf(
                        __('Invalid JSON format: %s', 'prograde-oort'),
                        json_last_error_msg()
                    ),
                    'error'
                );
                return;
            }

            // Attempt import
            if (Portability::import_data($json)) {
                add_settings_error(
                    'oort_portability',
                    'imported',
                    __('Configurations imported successfully.', 'prograde-oort'),
                    'updated'
                );
            } else {
                add_settings_error(
                    'oort_portability',
                    'failed',
                    __('Import failed. Invalid data structure or no endpoints to import.', 'prograde-oort'),
                    'error'
                );
            }
        }
    }

    public function render_page()
    {
        $export_json = Portability::export_all();
?>
        <div class="wrap">
            <h1><?php _e('Oort Portability (Import/Export)', 'prograde-oort'); ?></h1>
            <?php settings_errors('oort_portability'); ?>

            <div class="card">
                <h2><?php _e('Export Configurations', 'prograde-oort'); ?></h2>
                <p><?php _e('Copy this JSON to migrate your endpoints to another site.', 'prograde-oort'); ?></p>
                <textarea class="large-text code" rows="10" readonly><?php echo esc_textarea($export_json); ?></textarea>
            </div>

            <div class="card">
                <h2><?php _e('Import Configurations', 'prograde-oort'); ?></h2>
                <p><?php _e('Paste Oort JSON here to import endpoints.', 'prograde-oort'); ?></p>
                <form method="post" action="">
                    <?php wp_nonce_field('oort_portability_action', 'oort_portability_nonce'); ?>
                    <textarea name="import_data" class="large-text code" rows="10" placeholder="<?php esc_attr_e('Paste JSON here...', 'prograde-oort'); ?>"></textarea>
                    <p class="submit">
                        <input type="submit" name="oort_import" class="button button-primary" value="<?php esc_attr_e('Import Endpoints', 'prograde-oort'); ?>">
                    </p>
                </form>
            </div>
        </div>
        <style>
            .card {
                max-width: 800px;
                margin-top: 20px;
                padding: 20px;
                background: #fff;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }
        </style>
<?php
    }
}
