<?php
declare(strict_types=1);

namespace ProgradeOort\Automation;

use Symfony\Component\ExpressionLanguage\ExpressionLanguage;

/**
 * Automation Engine for executing custom logic.
 * Supports Symfony Expression Language (safe) and legacy PHP (restricted).
 */
class Engine
{
    /** @var self|null Singleton instance */
    private static ?self $instance = null;
    
    /** @var ExpressionLanguage Symfony Expression Language instance */
    private ExpressionLanguage $expressionLanguage;
    
    /** @var bool Whether eval() execution is allowed */
    private bool $allowEval = false;

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
     * Constructor initializes the expression language and checks settings.
     */
    private function __construct()
    {
        $this->expressionLanguage = new ExpressionLanguage();
        $this->expressionLanguage->registerProvider(new LogicProvider());

        // PHP execution disabled by default with security enhancements
        $this->allowEval = (bool) apply_filters('prograde_oort_allow_eval', false);

        if (!$this->allowEval) {
            \ProgradeOort\Log\Logger::instance()->info(
                "PHP execution disabled - Expression Language only mode active",
                [],
                'system'
            );
        }
    }

    /**
     * Run an automation flow.
     *
     * @param string $flow_id Unique identifier for the flow.
     * @param array<string, mixed> $data Input data for the flow.
     * @param string|null $custom_logic The logic to execute.
     * @return array<string, mixed> Execution result.
     */
    public function run_flow(string $flow_id, array $data, ?string $custom_logic = null): array
    {
        \ProgradeOort\Log\Logger::instance()->info("Running flow: {$flow_id}", $data);

        // If logic is not provided, try to find it in options (backward compatibility)
        if (is_null($custom_logic)) {
            $custom_logic = (string) get_option("prograde_oort_flow_{$flow_id}", '');
        }

        // Logic check
        if (is_null($custom_logic) || trim($custom_logic) === '') {
            return ['status' => 'success', 'result' => $data];
        }

        // Logic routing (Expression Language vs Legacy PHP)
        if (strpos($custom_logic, '<?php') === 0) {
            if (!$this->allowEval) {
                return [
                    'status' => 'error',
                    'message' => 'PHP execution is disabled. Use Expression Language or enable legacy mode.'
                ];
            }
            return $this->execute_php_legacy($custom_logic, $data);
        }

        try {
            // Execution context
            $context = array_merge($data, [
                'params' => $data,
                'data' => $data,
                'timestamp' => time()
            ]);

            $result = $this->expressionLanguage->evaluate($custom_logic, $context);

            return ['status' => 'success', 'result' => $result];
        } catch (\Exception $e) {
            \ProgradeOort\Log\Logger::instance()->error(
                "Automation logic failure in flow: {$flow_id}",
                ['error' => $e->getMessage(), 'logic' => $custom_logic],
                'execution'
            );

            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Restricted execution of legacy PHP code.
     * Uses pseudo-isolation and explicit capability checks.
     *
     * @param string $code Raw PHP code.
     * @param array<string, mixed> $data Context data.
     * @return array<string, mixed>
     */
    private function execute_php_legacy(string $code, array $data): array
    {
        if (!current_user_can('manage_options')) {
            return [
                'status' => 'error',
                'message' => 'Insufficient permissions for PHP execution'
            ];
        }

        try {
            // Sanitize data keys to prevent overwriting internal variables during extract()
            $safe_data = [];
            foreach ($data as $key => $value) {
                $safe_key = preg_replace('/[^a-zA-Z0-9_]/', '', (string)$key);
                if ($safe_key !== '' && !in_array($safe_key, ['code', 'data', 'safe_data', 'e', 'result'])) {
                    $safe_data[$safe_key] = is_string($value) ? sanitize_text_field($value) : $value;
                }
            }

            extract($safe_data, EXTR_SKIP);

            // Execute in an isolated scope (pseudo-isolation via Closure)
            $executor = function() use ($code, $safe_data) {
                extract($safe_data, EXTR_SKIP);
                return eval('?>' . $code);
            };

            $result = $executor();

            return ['status' => 'success', 'result' => $result];
        } catch (\Throwable $e) {
            \ProgradeOort\Log\Logger::instance()->error(
                "Legacy PHP execution error",
                ['error' => $e->getMessage()],
                'execution'
            );
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
