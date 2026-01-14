<?php
declare(strict_types=1);

namespace ProgradeOort\Consumption;

/**
 * Feed Ingestion Runner.
 * Fetches remote data and passes it to the processing pipeline.
 */
class Runner
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
     * Run an ingestion process for a specific feed.
     *
     * @param string $feed_url The source URL.
     * @param array<string, mixed> $config Ingestion configuration (mapping, types).
     * @return array<string, mixed> Stats of the ingestion.
     */
    public function run(string $feed_url, array $config = []): array
    {
        \ProgradeOort\Log\Logger::instance()->info("Starting ingestion from: $feed_url", $config, 'ingestion');

        $response = wp_remote_get($feed_url, [
            'timeout' => 30,
            'redirection' => 5,
            'httpversion' => '1.1',
        ]);

        if (is_wp_error($response)) {
            return ['status' => 'error', 'message' => $response->get_error_message()];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data)) {
            return ['status' => 'error', 'message' => 'Invalid data format received or empty response.'];
        }

        // Check if background processing is requested
        if (!empty($config['background']) && count($data) > 5) {
            $total = \ProgradeOort\Consumption\Queue::instance()->enqueue_items(
                $data,
                $config,
                (int)($config['batch_size'] ?? 50)
            );
            return [
                'status' => 'queued',
                'message' => "Enqueued {$total} items for background processing.",
                'total' => $total
            ];
        }

        $pipeline = new Pipeline($config);
        $stats = $pipeline->process($data);

        \ProgradeOort\Log\Logger::instance()->info("Ingestion completed", $stats, 'ingestion');

        return ['status' => 'success', 'stats' => $stats];
    }
}
