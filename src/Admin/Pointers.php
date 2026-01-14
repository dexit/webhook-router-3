<?php
declare(strict_types=1);

namespace ProgradeOort\Admin;

/**
 * Handles WordPress Admin Pointers for user onboarding.
 */
class Pointers
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /**
     * Get the singleton instance.
     */
    public static function instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor registers hooks.
     */
    private function __construct()
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue_pointers']);
    }

    /**
     * Enqueue pointers on relevant pages.
     */
    public function enqueue_pointers(string $hook_suffix): void
    {
        // Only show if pointers are supported and we're on our pages
        if (!current_user_can('manage_options') || strpos($hook_suffix, 'prograde-oort') === false && get_post_type() !== 'oort_endpoint') {
            return;
        }

        $pointers = $this->get_pointers();
        if (empty($pointers)) {
            return;
        }

        wp_enqueue_style('wp-pointer');
        wp_enqueue_script('wp-pointer');

        add_action('admin_print_footer_scripts', function () use ($pointers) {
            $this->print_pointer_scripts($pointers);
        });
    }

    /**
     * Define pointers for the plugin.
     *
     * @return array<string, array<string, mixed>>
     */
    private function get_pointers(): array
    {
        $dismissed = explode(',', (string) get_user_meta(get_current_user_id(), 'dismissed_wp_pointers', true));
        $pointers = [];

        // Dashboard pointer
        if (!in_array('oort_dash_ptr', $dismissed, true)) {
            $pointers['oort_dash_ptr'] = [
                'target'  => '#toplevel_page_prograde-oort',
                'options' => [
                    'content'  => '<h3>' . __('Welcome to Oort!', 'prograde-oort') . '</h3><p>' . __('This is your unified automation command center. Start by creating endpoints or exploring the dashboard.', 'prograde-oort') . '</p>',
                    'position' => ['edge' => 'left', 'align' => 'center'],
                ],
            ];
        }

        // Logic Editor pointer
        if (get_post_type() === 'oort_endpoint' && !in_array('oort_editor_ptr', $dismissed, true)) {
            $pointers['oort_editor_ptr'] = [
                'target'  => '#oort_code_editor',
                'options' => [
                    'content'  => '<h3>' . __('Custom Logic Editor', 'prograde-oort') . '</h3><p>' . __('Use Symfony Expression Language to transform data dynamically. Use autocomplete tips for functions like pluck() and get_meta().', 'prograde-oort') . '</p>',
                    'position' => ['edge' => 'top', 'align' => 'center'],
                ],
            ];
        }

        return $pointers;
    }

    /**
     * Print the JavaScript to initialize pointers.
     *
     * @param array<string, array<string, mixed>> $pointers
     */
    private function print_pointer_scripts(array $pointers): void
    {
        ?>
        <script type="text/javascript">
            (function($) {
                $(document).ready(function() {
                    <?php foreach ($pointers as $id => $data) : ?>
                        $('<?php echo esc_js($data['target']); ?>').pointer({
                            content: '<?php echo wp_kses_post($data['options']['content']); ?>',
                            position: <?php echo json_encode($data['options']['position']); ?>,
                            close: function() {
                                $.post(ajaxurl, {
                                    pointer: '<?php echo esc_js($id); ?>',
                                    action: 'dismiss-wp-pointer'
                                });
                            }
                        }).pointer('open');
                    <?php endforeach; ?>
                });
            })(jQuery);
        </script>
        <?php
    }
}
