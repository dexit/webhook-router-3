<?php
declare(strict_types=1);

namespace ProgradeOort\Consumption;

/**
 * Background Processing Queue for Data Ingestion.
 * Uses Action Scheduler to process large data feeds in segments.
 */
class Queue
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;

    /** @var string Action name for processing batches */
    private const ACTION_NAME = 'prograde_oort_process_batch';

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
     * Constructor registers the action hook.
     */
    private function __construct()
    {
        add_action(self::ACTION_NAME, [$this, 'process_batch'], 10, 2);
    }

    /**
     * Enqueue a collection of items for background processing.
     *
     * @param array<int, mixed> $items The items to process.
     * @param array<string, mixed> $config Pipeline configuration.
     * @param int $batch_size How many items to process per background job.
     * @return int Total number of items enqueued.
     */
    public function enqueue_items(array $items, array $config, int $batch_size = 50): int
    {
        $total = count($items);
        $batches = array_chunk($items, $batch_size);

        foreach ($batches as $index => $batch) {
            // Schedule each batch with a slight delay to spread load
            if (function_exists('as_enqueue_async_action')) {
                as_enqueue_async_action(self::ACTION_NAME, [
                    'batch' => $batch,
                    'config' => $config
                ], 'prograde-oort-ingestion');
            } else {
                // Fallback to synchronous processing if AS is missing
                $this->process_batch($batch, $config);
            }
        }

        \ProgradeOort\Log\Logger::instance()->info(
            "Enqueued background ingestion",
            ['total_items' => $total, 'batches' => count($batches)],
            'ingestion'
        );

        return $total;
    }

    /**
     * Process a single batch of items.
     * Called via Action Scheduler or directly as fallback.
     *
     * @param array<int, mixed> $batch
     * @param array<string, mixed> $config
     */
    public function process_batch(array $batch, array $config): void
    {
        $pipeline = new Pipeline($config);
        $stats = $pipeline->process($batch);

        \ProgradeOort\Log\Logger::instance()->info(
            "Background batch processed",
            $stats,
            'ingestion'
        );
    }
}
