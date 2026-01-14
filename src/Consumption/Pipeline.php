<?php
declare(strict_types=1);

namespace ProgradeOort\Consumption;

/**
 * Data Processing Pipeline.
 * Maps raw data items to WordPress posts and metadata.
 */
class Pipeline
{
    /** @var array<string, mixed> Configuration for mapping and processing */
    private array $config;

    /**
     * Pipeline constructor.
     *
     * @param array<string, mixed> $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Process a collection of items through the pipeline.
     *
     * @param array<int, mixed> $items
     * @return array<string, int> Processing stats.
     */
    public function process(array $items): array
    {
        $stats = ['total' => count($items), 'created' => 0, 'updated' => 0, 'failed' => 0];

        foreach ($items as $item) {
            if (!is_array($item)) {
                $stats['failed']++;
                continue;
            }

            try {
                $result = $this->process_item($item);
                if ($result === 'created') {
                    $stats['created']++;
                } else {
                    $stats['updated']++;
                }
            } catch (\Throwable $e) {
                $stats['failed']++;
                \ProgradeOort\Log\Logger::instance()->error(
                    __('Failed to process item', 'prograde-oort'),
                    ['error' => $e->getMessage(), 'item' => $item],
                    'ingestion'
                );
            }
        }

        return $stats;
    }

    /**
     * Process a single item and map it to WordPress.
     *
     * @param array<string, mixed> $item
     * @return string Either 'created' or 'updated'.
     * @throws \Exception If validation fails or insertion errors occur.
     */
    private function process_item(array $item): string
    {
        $id_field = $this->config['id_field'] ?? 'id';
        $post_type = $this->config['post_type'] ?? 'post';
        
        if (empty($item[$id_field])) {
            throw new \Exception("Missing item ID field: {$id_field}");
        }

        $external_id = (string)$item[$id_field];
        
        // Check for existing post by external ID mapping
        $existing_posts = get_posts([
            'post_type' => $post_type,
            'meta_query' => [
                [
                    'key' => '_oort_external_id',
                    'value' => $external_id,
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids',
        ]);

        $post_data = [
            'post_type' => $post_type,
            'post_title' => $item['title'] ?? "Imported Item {$external_id}",
            'post_content' => $item['content'] ?? '',
            'post_status' => $this->config['post_status'] ?? 'publish',
        ];

        if (!empty($existing_posts)) {
            $post_id = (int)$existing_posts[0];
            $post_data['ID'] = $post_id;
            $result = wp_insert_post($post_data, true);
            $outcome = 'updated';
        } else {
            $result = wp_insert_post($post_data, true);
            $post_id = is_int($result) ? $result : 0;
            $outcome = 'created';
        }

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        // Save external ID reference and other meta
        update_post_meta($post_id, '_oort_external_id', $external_id);
        
        if (isset($this->config['meta_mapping']) && is_array($this->config['meta_mapping'])) {
            foreach ($this->config['meta_mapping'] as $item_key => $meta_key) {
                if (isset($item[$item_key])) {
                    $val = $item[$item_key];
                    // Handle repeaters/nested arrays by serializing or passing to specific logic
                    if (is_array($val)) {
                        $val = maybe_serialize($val);
                    }
                    update_post_meta($post_id, (string)$meta_key, $val);
                }
            }
        }

        return $outcome;
    }
}
