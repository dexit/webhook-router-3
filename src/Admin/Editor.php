<?php

namespace ProgradeOort\Admin;

/**
 * Admin Editor with Monaco Code Editor integration
 * Provides PHP code editing with WordPress autocomplete
 */
class Editor
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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('add_meta_boxes', [$this, 'add_code_editor_metabox']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_head', [$this, 'add_help_tabs']);
    }

    public function register_settings(): void
    {
        register_setting('prograde_oort_settings', 'prograde_oort_allow_eval');
    }

    public function register_menu()
    {
        add_menu_page(
            __('Prograde Oort', 'prograde-oort'),
            __('Oort', 'prograde-oort'),
            'manage_options',
            'prograde-oort',
            [$this, 'render_dashboard'],
            'dashicons-admin-generic'
        );
    }

    public function enqueue_assets($hook)
    {
        // Only load on Oort pages
        if (strpos($hook, 'prograde-oort') === false && get_post_type() !== 'oort_endpoint') {
            return;
        }

        // Enqueue Monaco Editor bundle
        wp_enqueue_script(
            'oort-monaco-editor',
            PROGRADE_OORT_URL . 'assets/dist/oort-editor.js',
            ['react', 'react-dom'],
            '1.1.0',
            true
        );

        // Enqueue React (WordPress includes it by default in Gutenberg)
        wp_enqueue_script('react');
        wp_enqueue_script('react-dom');

        // Editor styles
        wp_enqueue_style(
            'oort-editor-styles',
            PROGRADE_OORT_URL . 'assets/css/editor.css',
            [],
            '1.1.0'
        );

        // Pass configuration to JavaScript
        wp_localize_script('oort-monaco-editor', 'oortEditorConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('oort_editor'),
            'features' => [
                'actionScheduler' => function_exists('as_enqueue_async_action'),
                'guzzle' => class_exists('GuzzleHttp\\Client'),
                'monolog' => class_exists('Monolog\\Logger')
            ],
            'autocomplete' => [
                'functions' => [
                    ['label' => 'pluck', 'insertText' => 'pluck(${1:array}, "${2:key}")', 'detail' => 'Extract a list of values for a key from an array of objects'],
                    ['label' => 'first', 'insertText' => 'first(${1:array})', 'detail' => 'Get the first element of an array'],
                    ['label' => 'last', 'insertText' => 'last(${1:array})', 'detail' => 'Get the last element of an array'],
                    ['label' => 'get_meta', 'insertText' => 'get_meta(${1:post_id}, "${2:key}")', 'detail' => 'Retrieve WordPress post metadata'],
                    ['label' => 'set_meta', 'insertText' => 'set_meta(${1:post_id}, "${2:key}", ${3:value})', 'detail' => 'Update WordPress post metadata'],
                    ['label' => 'flatten', 'insertText' => 'flatten(${1:array})', 'detail' => 'Flatten a multidimensional array'],
                    ['label' => 'log', 'insertText' => 'log(${1:message})', 'detail' => 'Log an informational message'],
                    ['label' => 'json', 'insertText' => 'json(${1:data})', 'detail' => 'JSON encode data'],
                    ['label' => 'concat', 'insertText' => 'concat(${1:str1}, ${2:str2})', 'detail' => 'Concatenate multiple strings']
                ],
                'variables' => [
                    ['label' => 'params', 'detail' => 'Raw webhook payload (array)'],
                    ['label' => 'data', 'detail' => 'Contextual data/entity values']
                ],
                'templates' => [
                    ['label' => 'Slack Notification', 'code' => 'log("Notification: " ~ params.message)'],
                    ['label' => 'Repeater Filter', 'code' => 'filter(params.items, v => v.status == "active")'],
                    ['label' => 'Entity Meta Sync', 'code' => 'set_meta(data.id, "_synced", 1)'],
                    ['label' => 'Background Batch', 'code' => 'pluck(params.data, "id")']
                ]
            ]
        ]);
    }

    public function add_code_editor_metabox()
    {
        add_meta_box(
            'oort_code_editor',
            __('Custom Logic Editor', 'prograde-oort'),
            [$this, 'render_code_editor'],
            'oort_endpoint',
            'normal',
            'high'
        );
    }

    public function render_code_editor($post)
    {
        $code = get_post_meta($post->ID, '_oort_logic', true);
        if (empty($code)) {
            $code = "<?php\n// Available: \$params (webhook data), \$data (contextual data)\n// Action Scheduler: as_enqueue_async_action('hook', \$args)\n// HTTP Client: use GuzzleHttp\\Client;\n// Logger: \\ProgradeOort\\Log\\Logger::instance()->info(\$message)\n\nreturn ['status' => 'success'];\n";
        }
?>
        <div class="oort-code-editor-wrapper">
            <!-- React mounts here -->
            <div id="oort-react-editor-root"></div>

            <!-- Hidden field for WordPress form submission -->
            <textarea id="oort_logic_code" name="_oort_logic" style="display:none;"><?php echo esc_textarea($code); ?></textarea>

            <div class="oort-editor-footer">
                <p class="description">
                    <strong><?php _e('💡 Quick Hints:', 'prograde-oort'); ?></strong><br />
                    <?php _e('• Access webhook data via', 'prograde-oort'); ?> <code>$params</code><br />
                    <?php _e('• Schedule background tasks:', 'prograde-oort'); ?> <code>as_enqueue_async_action('my_hook', $args)</code><br />
                    <?php _e('• Make HTTP requests:', 'prograde-oort'); ?> <code>$client = new GuzzleHttp\Client()</code><br />
                    <?php _e('• Log events:', 'prograde-oort'); ?> <code>\ProgradeOort\Log\Logger::instance()->info($message)</code>
                </p>
            </div>
        </div>
    <?php
    }

    public function render_dashboard()
    {
    ?>
        <div class="wrap">
            <div class="oort-hero">
                <div class="oort-hero-content">
                    <h1><?php _e('Welcome to Prograde Oort', 'prograde-oort'); ?></h1>
                    <p class="oort-hero-lead"><?php _e('The unified automation engine for WordPress. Connect webhooks, process data feeds, and transform content with ease.', 'prograde-oort'); ?></p>
                    <div class="oort-hero-actions">
                        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=oort_endpoint')); ?>" class="button button-primary button-hero"><?php _e('🚀 Create Your First Endpoint', 'prograde-oort'); ?></a>
                        <a href="https://google.com" target="_blank" class="button button-hero"><?php _e('📚 Documentation', 'prograde-oort'); ?></a>
                    </div>
                </div>
            </div>

            <div class="oort-dashboard-grid">
                <div class="oort-card">
                    <h2><?php _e('📋 Endpoints', 'prograde-oort'); ?></h2>
                    <p><?php _e('Manage your API routes and automation workflows.', 'prograde-oort'); ?></p>
                    <a href="<?php echo esc_url(admin_url('edit.php?post_type=oort_endpoint')); ?>" class="button button-primary">
                        <?php _e('View Endpoints', 'prograde-oort'); ?>
                    </a>
                </div>
                <div class="oort-card">
                    <h2><?php _e('📊 Logs', 'prograde-oort'); ?></h2>
                    <p><?php _e('Monitor webhook activity and execution logs.', 'prograde-oort'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=oort-logs')); ?>" class="button">
                        <?php _e('View Logs', 'prograde-oort'); ?>
                    </a>
                </div>
                <div class="oort-card">
                    <h2><?php _e('🔄 Import/Export', 'prograde-oort'); ?></h2>
                    <p><?php _e('Migrate configurations across environments.', 'prograde-oort'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=oort-portability')); ?>" class="button">
                        <?php _e('Manage', 'prograde-oort'); ?>
                    </a>
                </div>
                <div class="oort-card">
                    <h2><?php _e('🔐 API Settings', 'prograde-oort'); ?></h2>
                    <?php 
                    $api_key = get_option('prograde_oort_api_key', '');
                    $preview = substr((string)$api_key, 0, 16);
                    ?>
                    <p><?php printf(__('Your API Key: %s...', 'prograde-oort'), '<code>' . esc_html($preview) . '</code>'); ?></p>
                    <p class="description"><?php _e('Use this key in the X-Prograde-Key header.', 'prograde-oort'); ?></p>
                </div>
                <div class="oort-card">
                    <h2><?php _e('⚙️ System Settings', 'prograde-oort'); ?></h2>
                    <form method="post" action="options.php">
                        <?php 
                        settings_fields('prograde_oort_settings');
                        $allow_php = get_option('prograde_oort_allow_eval', '0');
                        ?>
                        <label>
                            <input type="checkbox" name="prograde_oort_allow_eval" value="1" <?php checked('1', (string)$allow_php); ?>>
                            <?php _e('Enable Legacy PHP execution (Not Recommended)', 'prograde-oort'); ?>
                        </label>
                        <p class="description"><?php _e('Required for complex logic that cannot be expressed safely.', 'prograde-oort'); ?></p>
                        <?php submit_button(__('Save Settings', 'prograde-oort'), 'secondary', 'submit', false); ?>
                    </form>
                </div>
            </div>
        </div>
        <style>
            .oort-hero {
                background: linear-gradient(135deg, #2271b1 0%, #135e96 100%);
                color: white;
                padding: 40px;
                border-radius: 8px;
                margin-bottom: 20px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            }
            .oort-hero h1 { color: white; margin-top: 0; font-size: 2.5em; }
            .oort-hero-lead { font-size: 1.25em; max-width: 800px; opacity: 0.9; margin-bottom: 30px; }
            .oort-hero .button-hero { padding: 10px 24px; height: auto; font-size: 1.1em; }
            
            .oort-dashboard-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }

            .oort-card {
                background: white;
                padding: 20px;
                border: 1px solid #ccd0d4;
                box-shadow: 0 1px 1px rgba(0, 0, 0, .04);
            }

            .oort-card h2 {
                margin-top: 0;
            }

            .oort-card code {
                background: #f0f0f1;
                padding: 2px 6px;
                border-radius: 3px;
            }
        </style>
    <?php
    }

    /**
     * Add contextual help tabs to Oort-related screens.
     */
    public function add_help_tabs(): void
    {
        $screen = get_current_screen();
        if (!$screen) return;

        // Only add to our specific pages
        if (strpos((string)$screen->id, 'prograde-oort') === false && $screen->post_type !== 'oort_endpoint') {
            return;
        }

        $screen->add_help_tab([
            'id'      => 'oort_overview',
            'title'   => __('Overview', 'prograde-oort'),
            'content' => '<p>' . __('Welcome to Prograde Oort. This plugin allows you to create high-performance webhook endpoints and automation flows using a safe Expression Language or legacy PHP.', 'prograde-oort') . '</p>',
        ]);

        $screen->add_help_tab([
            'id'      => 'oort_syntax',
            'title'   => __('Syntax Guide', 'prograde-oort'),
            'content' => '<h4>' . __('Available Variables', 'prograde-oort') . '</h4>' .
                '<ul>' .
                '<li><code>$params</code>: ' . __('The raw payload from the incoming request.', 'prograde-oort') . '</li>' .
                '<li><code>$data</code>: ' . __('Contextual data gathered during processing.', 'prograde-oort') . '</li>' .
                '</ul>' .
                '<h4>' . __('Functions', 'prograde-oort') . '</h4>' .
                '<ul>' .
                '<li><code>pluck(array, key)</code>: ' . __('Extract values for a key.', 'prograde-oort') . '</li>' .
                '<li><code>get_meta(id, key)</code>: ' . __('Get WP metadata.', 'prograde-oort') . '</li>' .
                '<li><code>log(message)</code>: ' . __('Log to Oort system logs.', 'prograde-oort') . '</li>' .
                '</ul>',
        ]);

        $screen->add_help_tab([
            'id'      => 'oort_advanced',
            'title'   => __('Advanced Logic', 'prograde-oort'),
            'content' => '<p>' . __('For complex logic, you can enable "Legacy PHP execution" in System Settings. Note that this is less secure than the default Expression Language.', 'prograde-oort') . '</p>' .
                '<p>' . __('Action Scheduler integration is supported for background batch processing of large datasets.', 'prograde-oort') . '</p>',
        ]);

        $screen->set_help_sidebar(
            '<p><strong>' . __('More Information', 'prograde-oort') . '</strong></p>' .
                '<p><a href="https://google.com" target="_blank">' . __('Documentation', 'prograde-oort') . '</a></p>' .
                '<p><a href="https://google.com" target="_blank">' . __('Support Forum', 'prograde-oort') . '</a></p>'
        );
    }
}
