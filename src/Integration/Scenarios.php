<?php
declare(strict_types=1);

namespace ProgradeOort\Integration;

/**
 * Pre-defined example configurations to showcase Oort capabilities.
 */
class Scenarios
{
    /**
     * Register example endpoints.
     */
    public static function register_examples(): void
    {
        // Use atomic add_option to prevent race conditions
        if (!add_option('prograde_oort_examples_installed', (string)time(), '', 'no')) {
            return;
        }

        // 1. Example: Inbound Webhook Logging (Expression Language)
        self::create_endpoint(
            'Example: Generic Webhook',
            'webhook',
            'api/v1/log-it',
            'log("Received generic webhook from " ~ (params.ip ?? "unknown"))'
        );

        // 2. Example: Dynamic Metadata (Logic Helpers)
        self::create_endpoint(
            'Example: Metadata Update',
            'event',
            'wp_insert_post',
            'set_meta(data.post_id, "_oort_processed", timestamp)'
        );

        // 3. Example: Data Ingestion (Action Scheduler)
        self::create_endpoint(
            'Example: Async Task',
            'event',
            'oort_custom_event',
            'log("Queueing async processing for " ~ (data.id ?? "none"))'
        );

        update_option('prograde_oort_examples_installed', '1');
    }

    /**
     * Helper to create an endpoint post.
     */
    private static function create_endpoint(string $title, string $type, string $path, string $code): int
    {
        $post_id = wp_insert_post([
            'post_title' => sanitize_text_field($title),
            'post_type'  => 'oort_endpoint',
            'post_status' => 'publish'
        ]);

        if (is_int($post_id) && $post_id > 0) {
            update_post_meta($post_id, '_oort_route_type', sanitize_text_field($type));
            update_post_meta($post_id, '_oort_route_path', sanitize_text_field($path));
            update_post_meta($post_id, '_oort_logic', $code);
            return $post_id;
        }

        return 0;
    }
}
