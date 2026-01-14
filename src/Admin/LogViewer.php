<?php

namespace ProgradeOort\Admin;

class LogViewer
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
        add_action('admin_menu', [$this, 'register_menu']);
    }

    public function register_menu()
    {
        add_submenu_page(
            'prograde-oort',
            __('Log Viewer', 'prograde-oort'),
            __('Log Viewer', 'prograde-oort'),
            'manage_options',
            'oort-logs',
            [$this, 'render_page']
        );
    }

    public function render_page()
    {
        $channel = isset($_GET['channel']) ? sanitize_key($_GET['channel']) : 'webhooks';
        $logs = \ProgradeOort\Log\Logger::instance()->get_logs($channel);
?>
        <div class="wrap">
            <h1><?php _e('Prograde Oort Log Viewer', 'prograde-oort'); ?></h1>
            <p>
                <a href="<?php echo esc_url(add_query_arg('channel', 'webhooks')); ?>" class="button <?php echo $channel === 'webhooks' ? 'button-primary' : ''; ?>"><?php _e('Webhooks', 'prograde-oort'); ?></a>
                <a href="<?php echo esc_url(add_query_arg('channel', 'execution')); ?>" class="button <?php echo $channel === 'execution' ? 'button-primary' : ''; ?>"><?php _e('Execution', 'prograde-oort'); ?></a>
                <a href="<?php echo esc_url(add_query_arg('channel', 'ingestion')); ?>" class="button <?php echo $channel === 'ingestion' ? 'button-primary' : ''; ?>"><?php _e('Ingestion', 'prograde-oort'); ?></a>
                <a href="<?php echo esc_url(add_query_arg('channel', 'security')); ?>" class="button <?php echo $channel === 'security' ? 'button-primary' : ''; ?>"><?php _e('Security', 'prograde-oort'); ?></a>
            </p>
            <textarea readonly style="width: 100%; height: 600px; font-family: monospace; background: #272822; color: #f8f8f2; padding: 10px;"><?php echo esc_textarea($logs); ?></textarea>
        </div>
<?php
    }
}
