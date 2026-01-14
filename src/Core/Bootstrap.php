<?php
declare(strict_types=1);

namespace ProgradeOort\Core;

/**
 * Main plugin bootstrap class.
 * Handles component initialization and provides access to them.
 */
class Bootstrap
{
    /** @var self|null Singleton instance of the class */
    private static ?self $instance = null;

    /** @var array<string, mixed> List of initialized components */
    private array $components = [];

    /**
     * Get the singleton instance.
     *
     * @return self
     */
    public static function instance(): self
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor is private to enforce singleton pattern.
     */
    private function __construct()
    {
        $this->init_components();
    }

    /**
     * Initialize all plugin components.
     *
     * @return void
     */
    private function init_components(): void
    {
        $this->components['log'] = \ProgradeOort\Log\Logger::instance();
        $this->components['router'] = \ProgradeOort\Api\Router::instance();
        $this->components['engine'] = \ProgradeOort\Automation\Engine::instance();
        $this->components['events'] = \ProgradeOort\Automation\Events::instance();
        $this->components['dispatcher'] = \ProgradeOort\Automation\WebhookDispatcher::instance();
        $this->components['runner'] = \ProgradeOort\Consumption\Runner::instance();
        $this->components['admin'] = \ProgradeOort\Admin\Editor::instance();
        $this->components['log_viewer'] = \ProgradeOort\Admin\LogViewer::instance();
        $this->components['portability'] = \ProgradeOort\Admin\PortabilityPage::instance();
        $this->components['post_types'] = \ProgradeOort\Core\PostTypes::instance();
        $this->components['metaboxes'] = \ProgradeOort\Integration\ScfMetaboxes::instance();
        $this->components['queue'] = \ProgradeOort\Consumption\Queue::instance();
        $this->components['pointers'] = \ProgradeOort\Admin\Pointers::instance();

        // Register Examples on initialization
        add_action('init', function () {
            \ProgradeOort\Integration\Scenarios::register_examples();
        });
    }

    /**
     * Get a registered component by its name.
     *
     * @param string $name The component key.
     * @return mixed|null The component instance or null if not found.
     */
    public function get_component(string $name): mixed
    {
        return $this->components[$name] ?? null;
    }
}
