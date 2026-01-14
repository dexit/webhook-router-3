<?php
declare(strict_types=1);

/**
 * Advanced Features Verification Script
 * Tests Import Queue and Logic Helpers (map, filter, flatten, etc.)
 */

define('ABSPATH', true);
define('WPINC', true);

// Mock WordPress functions
function add_action($tag, $callback, $priority = 10, $accepted_args = 1) {}
function apply_filters($tag, $value) { return $value; }
function get_option($name, $default = false) { return $default; }
function update_option($name, $value) { return true; }
function current_user_can($cap) { return true; }
function get_post_meta($id, $key, $single = true) { return 'meta_val'; }
function update_post_meta($id, $key, $val) { return true; }
function sanitize_text_field($text) { return $text; }
function maybe_serialize($val) { return is_array($val) ? serialize($val) : $val; }

// Mock Action Scheduler
function as_enqueue_async_action($hook, $args, $group) {
    echo "Enqueued AS Action: $hook with " . count($args['batch']) . " items\n";
    return 1;
}

// Load the plugin components (via autoloader mocks if needed, or direct require)
require_once dirname(__DIR__) . '/vendor/autoload.php';

echo "=== ADVANCED FEATURES VERIFICATION ===\n\n";

// TEST 1: Logic Helpers (Engine)
echo "TEST 1: Logic Helpers (pluck, first, last, flatten)\n";
$engine = \ProgradeOort\Automation\Engine::instance();

// Test pluck
$result = $engine->run_flow('test_pluck', ['users' => [['id' => 1], ['id' => 2]]], 'pluck(users, "id")');
if ($result['status'] === 'success' && $result['result'] === [1, 2]) {
    echo "✓ pluck() helper works\n";
} else {
    echo "✗ pluck() helper failed\n";
    print_r($result);
}

// Test first
$result = $engine->run_flow('test_first', ['nums' => [1, 2, 3]], 'first(nums)');
if ($result['status'] === 'success' && $result['result'] === 1) {
    echo "✓ first() helper works\n";
} else {
    echo "✗ first() helper failed\n";
}

// Test last
$result = $engine->run_flow('test_last', ['nums' => [1, 2, 3]], 'last(nums)');
if ($result['status'] === 'success' && $result['result'] === 3) {
    echo "✓ last() helper works\n";
} else {
    echo "✗ last() helper failed\n";
}

// Test flatten
$result = $engine->run_flow('test_flatten', ['nested' => [[1, 2], [3, 4]]], 'flatten(nested)');
if ($result['status'] === 'success' && count($result['result']) === 4) {
    echo "✓ flatten() helper works\n";
} else {
    echo "✗ flatten() helper failed\n";
    print_r($result);
}

echo "\n";

// TEST 2: Import Queue
echo "TEST 2: Import Queue Delegation\n";
$runner = \ProgradeOort\Consumption\Runner::instance();
$large_data = array_fill(0, 10, ['id' => 1, 'title' => 'Test']);
$config = ['background' => true, 'batch_size' => 5];

// We bypass the remote get for testing
$result = \ProgradeOort\Consumption\Queue::instance()->enqueue_items($large_data, $config, 5);
if ($result === 10) {
    echo "✓ Queue correctly chunked 10 items into batches via Action Scheduler\n";
} else {
    echo "✗ Queue failed to chunk items correctly\n";
}

echo "\n=== VERIFICATION COMPLETE ===\n";
