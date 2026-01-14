<?php
declare(strict_types=1);

namespace ProgradeOort\Integration;

/**
 * Utility for Importing and Exporting Endpoint Configurations.
 * Handles batch export and schema-validated imports.
 */
class Portability
{
    /**
     * Export all endpoints to a JSON string.
     *
     * @return string JSON representation of all endpoints.
     */
    public static function export_all(): string
    {
        $batch_size = 100;
        $paged = 1;
        $export_data = [];
        $total_exported = 0;

        do {
            $endpoints = get_posts([
                'post_type' => 'oort_endpoint',
                'posts_per_page' => $batch_size,
                'paged' => $paged++,
                'post_status' => 'any',
                'fields' => 'all'
            ]);

            if (!is_array($endpoints)) break;

            foreach ($endpoints as $post) {
                if (!($post instanceof \WP_Post)) continue;

                $meta = get_post_custom($post->ID);
                $cleaned_meta = [];
                if (is_array($meta)) {
                    foreach ($meta as $key => $values) {
                        $key_str = (string)$key;
                        // Include our prefixed keys, even if they start with _
                        if (str_starts_with($key_str, '_oort_')) {
                            $cleaned_meta[$key_str] = maybe_unserialize($values[0]);
                        }
                    }
                }

                $export_data[] = [
                    'post_title'   => $post->post_title,
                    'post_content' => $post->post_content,
                    'post_status'  => $post->post_status,
                    'post_type'    => $post->post_type,
                    'meta'         => $cleaned_meta
                ];
                $total_exported++;
            }
        } while (count($endpoints) === $batch_size);

        \ProgradeOort\Log\Logger::instance()->info(
            __('Export completed', 'prograde-oort'),
            ['total_endpoints' => $total_exported],
            'portability'
        );

        return (string) json_encode([
            'version'   => '1.1',
            'timestamp' => time(),
            'count'     => $total_exported,
            'data'      => $export_data
        ], JSON_PRETTY_PRINT);
    }

    /**
     * Import endpoints from a JSON string.
     *
     * @param string $json_data Raw JSON input.
     * @return bool True if at least one item was imported successfully.
     */
    public static function import_data(string $json_data): bool
    {
        $decoded = json_decode($json_data, true);

        if (!is_array($decoded) || !isset($decoded['version'], $decoded['data']) || !is_array($decoded['data'])) {
            \ProgradeOort\Log\Logger::instance()->error(
                __('Import failed: Invalid JSON structure', 'prograde-oort'),
                [],
                'portability'
            );
            return false;
        }

        if (version_compare((string)$decoded['version'], '1.0', '<')) {
            \ProgradeOort\Log\Logger::instance()->error(
                __('Import failed: Unsupported version', 'prograde-oort'),
                ['version' => $decoded['version']],
                'portability'
            );
            return false;
        }

        $allowed_statuses = ['publish', 'draft', 'pending', 'private'];
        $allowed_meta_keys = [
            '_oort_route_type', '_oort_route_path', '_oort_logic', '_oort_trigger',
            'oort_route_type', 'oort_route_path', 'oort_logic', 'oort_trigger',
            'route_type', 'route_path', 'logic_code'
        ];

        $imported_count = 0;
        $skipped_count = 0;

        foreach ($decoded['data'] as $item) {
            if (!is_array($item)) continue;
            
            if (!isset($item['post_title']) || !isset($item['post_type']) || $item['post_type'] !== 'oort_endpoint') {
                $skipped_count++;
                continue;
            }

            $post_status = isset($item['post_status']) && in_array($item['post_status'], $allowed_statuses, true)
                ? (string) $item['post_status']
                : 'draft';

            $post_id = wp_insert_post([
                'post_title'   => sanitize_text_field((string)$item['post_title']),
                'post_content' => wp_kses_post((string)($item['post_content'] ?? '')),
                'post_status'  => $post_status,
                'post_type'    => 'oort_endpoint'
            ], true);

            if (is_wp_error($post_id)) {
                \ProgradeOort\Log\Logger::instance()->error(
                    __('Failed to import endpoint', 'prograde-oort'),
                    ['title' => $item['post_title'] ?? 'unknown', 'error' => $post_id->get_error_message()],
                    'portability'
                );
                $skipped_count++;
                continue;
            }

            if (isset($item['meta']) && is_array($item['meta'])) {
                foreach ($item['meta'] as $key => $value) {
                    if (!is_string($key) || !in_array($key, $allowed_meta_keys, true)) {
                        continue;
                    }

                    // Map legacy keys to new ones
                    $final_key = $key;
                    if ($key === 'logic_code' || $key === 'oort_logic') $final_key = '_oort_logic';
                    if ($key === 'route_type' || $key === 'oort_route_type') $final_key = '_oort_route_type';
                    if ($key === 'route_path' || $key === 'oort_route_path') $final_key = '_oort_route_path';
                    if ($key === 'oort_trigger') $final_key = '_oort_trigger';

                    $sanitized_value = is_string($value)
                        ? sanitize_text_field($value)
                        : $value;

                    update_post_meta((int)$post_id, (string)$final_key, $sanitized_value);
                }
            }

            $imported_count++;
        }

        \ProgradeOort\Log\Logger::instance()->info(
            __('Import completed', 'prograde-oort'),
            ['imported' => $imported_count, 'skipped' => $skipped_count],
            'portability'
        );

        return $imported_count > 0;
    }
}
