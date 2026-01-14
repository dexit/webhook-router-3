<?php
declare(strict_types=1);

namespace ProgradeOort\Api;

/**
 * REST API and Custom Path Router.
 * Handles incoming webhooks and dispatches them to the automation engine.
 */
class Router
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var array<string, callable> Registered custom paths */
    private array $paths = [];

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
     * Constructor registers WordPress hooks.
     */
    private function __construct()
    {
        add_action('rest_api_init', [$this, 'register_rest_routes']);
        add_action('parse_request', [$this, 'dispatch_custom_paths']);
    }

    /**
     * Register dynamic REST routes based on Oort Endpoints.
     */
    public function register_rest_routes(): void
    {
        $endpoints = get_posts([
            'post_type'      => 'oort_endpoint',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'   => '_oort_route_type',
                    'value' => 'rest',
                ],
            ],
        ]);

        foreach ($endpoints as $endpoint) {
            $path = get_post_meta($endpoint->ID, '_oort_route_path', true);
            if (! $path || !is_string($path)) continue;

            register_rest_route('prograde-oort/v1', ltrim($path, '/'), [
                'methods'             => 'POST',
                'callback'            => function (\WP_REST_Request $request) use ($endpoint) {
                    return $this->handle_dynamic_rest($request, $endpoint);
                },
                'permission_callback' => function (\WP_REST_Request $request) {
                    return $this->check_auth($request);
                },
            ]);
        }
    }

    /**
     * Handle incoming REST request for an Oort Endpoint.
     */
    public function handle_dynamic_rest(\WP_REST_Request $request, \WP_Post $endpoint): mixed
    {
        $params = $request->get_json_params() ?? [];
        $logic = get_post_meta($endpoint->ID, '_oort_logic', true);
        
        if (!is_string($logic)) {
            $logic = '';
        }

        \ProgradeOort\Log\Logger::instance()->info("Dynamic REST call: " . $endpoint->post_title, $params, 'webhooks');

        return \ProgradeOort\Automation\Engine::instance()->run_flow("endpoint_{$endpoint->ID}", $params, $logic);
    }

    /**
     * Register a custom non-REST path.
     */
    public function register_path(string $path, callable $callback): void
    {
        $this->paths[trim($path, '/')] = $callback;
    }

    /**
     * Dispatch custom paths from parse_request hook.
     */
    public function dispatch_custom_paths(\WP $wp): void
    {
        $request_path = trim($wp->request, '/');
        if (isset($this->paths[$request_path])) {
            call_user_func($this->paths[$request_path]);
            exit;
        }
    }

    /**
     * Check API key authentication.
     *
     * @param \WP_REST_Request $request
     * @return bool|\WP_Error
     */
    private function check_auth(\WP_REST_Request $request): bool|\WP_Error
    {
        $api_key = $request->get_header('X-Prograde-Key');
        $stored_key = get_option('prograde_oort_api_key');

        if (empty($stored_key)) {
            $stored_key = $this->generate_secure_key();
            update_option('prograde_oort_api_key', $stored_key);

            \ProgradeOort\Log\Logger::instance()->warning(
                'New API key generated. Please update your webhook clients.',
                ['key_preview' => substr((string)$stored_key, 0, 8) . '...'],
                'security'
            );
        }

        if (!is_string($api_key) || !hash_equals((string)$stored_key, $api_key)) {
            \ProgradeOort\Log\Logger::instance()->error(
                'Unauthorized API access attempt blocked',
                [
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                    'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
                ],
                'security'
            );

            return new \WP_Error(
                'rest_forbidden',
                __('Invalid API key', 'prograde-oort'),
                ['status' => 401]
            );
        }

        return true;
    }

    /**
     * Generate cryptographically secure API key.
     */
    private function generate_secure_key(): string
    {
        if (function_exists('wp_generate_password')) {
            return wp_generate_password(64, true, true);
        }

        return bin2hex(random_bytes(32));
    }
}
