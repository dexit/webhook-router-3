<?php

namespace ProgradeOort\Integration;

class ScfMetaboxes
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
        add_action('acf/init', [$this, 'register_fields']);

        // Check if ACF is installed
        if (!$this->is_acf_available()) {
            add_action('admin_notices', [$this, 'acf_missing_notice']);
        }
    }

    /**
     * Check if ACF or compatible plugin is available
     */
    private function is_acf_available()
    {
        return function_exists('acf_add_local_field_group') ||
            function_exists('register_field_group'); // SCF compatibility
    }

    /**
     * Display admin notice if ACF is missing
     */
    public function acf_missing_notice()
    {
?>
        <div class="notice notice-error is-dismissible">
            <p>
                <strong><?php _e('Prograde Oort:', 'prograde-oort'); ?></strong>
                <?php _e('Advanced Custom Fields (ACF) or Secure Custom Fields is required for endpoint configuration. Please install and activate it.', 'prograde-oort'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('plugin-install.php?s=advanced+custom+fields&tab=search&type=term')); ?>" class="button button-primary">
                    <?php _e('Install ACF Now', 'prograde-oort'); ?>
                </a>
            </p>
        </div>
<?php
    }

    public function register_fields()
    {
        if (! function_exists('acf_add_local_field_group')) {
            return;
        }

        acf_add_local_field_group([
            'key'    => 'group_oort_endpoint_settings',
            'title'  => __('Endpoint Settings', 'prograde-oort'),
            'fields' => [
                [
                    'key'     => 'field_oort_route_type',
                    'label'   => __('Route Type', 'prograde-oort'),
                    'name'    => '_oort_route_type',
                    'type'    => 'select',
                    'choices' => [
                        'rest' => __('REST API Endpoint', 'prograde-oort'),
                        'path' => __('Custom Path Dispatcher', 'prograde-oort'),
                    ],
                    'default_value' => 'rest',
                ],
                [
                    'key'   => 'field_oort_route_path',
                    'label' => __('Route Path', 'prograde-oort'),
                    'name'  => '_oort_route_path',
                    'type'  => 'text',
                    'instructions' => __('e.g., /webhook/my-flow or my-path', 'prograde-oort'),
                    'required' => 1,
                ],
                [
                    'key'     => 'field_oort_trigger',
                    'label'   => __('Trigger Event', 'prograde-oort'),
                    'name'    => '_oort_trigger',
                    'type'    => 'select',
                    'choices' => [
                        'webhook' => __('Incoming Webhook', 'prograde-oort'),
                        'ingestion' => __('Feed Ingestion Runner', 'prograde-oort'),
                        'event' => __('Internal Event (Post Save/Login)', 'prograde-oort'),
                        'manual' => __('Manual / API Call only', 'prograde-oort'),
                    ],
                    'default_value' => 'webhook',
                ],
                [
                    'key'   => 'field_oort_logic',
                    'label' => __('Automation Logic (Expression Language / PHP)', 'prograde-oort'),
                    'name'  => '_oort_logic',
                    'type'  => 'textarea',
                    'rows'  => 20,
                    'instructions' => __('Enter expression logic (safe) or PHP code (if enabled). Variable $params contains payload.', 'prograde-oort'),
                ],
            ],
            'location' => [
                [
                    [
                        'param'    => 'post_type',
                        'operator' => '==',
                        'value'    => 'oort_endpoint',
                    ],
                ],
            ],
        ]);
    }
}
