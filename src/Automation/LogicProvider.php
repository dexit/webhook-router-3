<?php
declare(strict_types=1);

namespace ProgradeOort\Automation;

use Symfony\Component\ExpressionLanguage\ExpressionFunction;
use Symfony\Component\ExpressionLanguage\ExpressionFunctionProviderInterface;

/**
 * Custom function provider for Oort Expression Language.
 */
class LogicProvider implements ExpressionFunctionProviderInterface
{
    public function getFunctions(): array
    {
        return [
            ExpressionFunction::fromPhp('date'),
            ExpressionFunction::fromPhp('json_encode', 'json'),
            
            new ExpressionFunction('pluck', function ($array, $key) {
                return sprintf('array_column(%s, %s)', $array, $key);
            }, function ($arguments, $array, $key) {
                if (!is_array($array)) return [];
                return array_column($array, (string)$key);
            }),

            new ExpressionFunction('first', function ($array) {
                return sprintf('reset(%s)', $array);
            }, function ($arguments, $array) {
                if (!is_array($array) || empty($array)) return null;
                return reset($array);
            }),

            new ExpressionFunction('last', function ($array) {
                return sprintf('end(%s)', $array);
            }, function ($arguments, $array) {
                if (!is_array($array) || empty($array)) return null;
                return end($array);
            }),

            new ExpressionFunction('flatten', function ($array) {
                return sprintf('iterator_to_array(new RecursiveIteratorIterator(new RecursiveArrayIterator(%s)), false)', $array);
            }, function ($arguments, $array) {
                if (!is_array($array)) return [];
                $it = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($array));
                return iterator_to_array($it, false);
            }),

            new ExpressionFunction('get_meta', function ($post_id, $key, $single = true) {
                return sprintf('get_post_meta(%s, %s, %s)', $post_id, $key, $single);
            }, function ($arguments, $post_id, $key, $single = true) {
                return get_post_meta((int)$post_id, (string)$key, (bool)$single);
            }),

            new ExpressionFunction('set_meta', function ($post_id, $key, $value) {
                return sprintf('update_post_meta(%s, %s, %s)', $post_id, $key, $value);
            }, function ($arguments, $post_id, $key, $value) {
                return update_post_meta((int)$post_id, (string)$key, $value);
            }),

            new ExpressionFunction('log', function ($str) {
                return sprintf('\ProgradeOort\Log\Logger::instance()->info(%s)', $str);
            }, function ($arguments, $message) {
                \ProgradeOort\Log\Logger::instance()->info((string)$message, [], 'execution');
                return $message;
            }),

            new ExpressionFunction('concat', function () {
                return 'implode("", func_get_args())';
            }, function ($arguments, ...$strings) {
                return implode('', $strings);
            }),
        ];
    }
}
