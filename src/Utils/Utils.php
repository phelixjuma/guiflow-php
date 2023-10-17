<?php

namespace PhelixJuma\DataTransformer\Utils;

use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use PhelixJuma\DataTransformer\Actions\FunctionAction;
use PhelixJuma\DataTransformer\Conditions\SimpleCondition;
use PhelixJuma\DataTransformer\Exceptions\UnknownOperatorException;

class Utils
{

    /**
     * @param $array
     * @param $key
     * @return mixed
     */
    public static function sortMultiAssocArrayByKey($array, $key, $order='asc'): mixed
    {
        usort($array, function($a, $b) use ($key, $order) {
            if (isset($a[$key]) && isset($b[$key])) {
                $result = ($a[$key] < $b[$key]) ? -1 : (($a[$key] > $b[$key]) ? 1 : 0);
                return ($order === 'desc') ? -$result : $result;
            }
            return 0; // If the key doesn't exist in one of the arrays, consider them equal
        });
        return $array;
    }

    /**
     * @param $date
     * @param $format
     * @return string
     */
    public static function format_date($date, $format): string
    {
        return date($format, $date);
    }

    /**
     * @param array $strings
     * @param $separator
     * @return string
     */
    public static function concat(array $strings, $separator = " "): string
    {
        return implode($separator, $strings);
    }

    /**
     * @param array $data
     * @param array $fields
     * @param $newField
     * @return array
     */
    public static function concat_multi_array_assoc(array $data, array $fields, $newField, $separator = " "): array
    {

        array_walk($data, function (&$value, $key) use($fields, $newField, $separator) {
            $dataToConcat = array_flip($fields);
            foreach ($value as $key => $v) {
                if (in_array($key, $fields)) {
                    $dataToConcat[$key] = $v;
                }
            }
            $value[$newField] = self::concat(array_values($dataToConcat), $separator);
        });

        return $data;
    }

    /**
     * @param $data
     * @param $pattern
     * @param $replacement
     * @return array|string|string[]|null
     */
    public static function custom_preg_replace($data, $pattern, $replacement): array|string|null
    {
        $newData = null;
        if (is_array($data)) {
            foreach ($data as $d) {
                $newData[] = preg_replace($pattern, $replacement, $d);
            }
        } else {
            $newData = preg_replace($pattern, $replacement, $data);
        }
        return $newData;
    }

    /**
     * @param $data
     * @param $conditionField
     * @param $conditionOperator
     * @param $conditionValue
     * @param $conditionSimilarityThreshold
     * @param $sumField
     * @return mixed
     * @throws UnknownOperatorException
     */
    public static function assoc_array_sum_if($data, $conditionField, $conditionOperator, $conditionValue, $conditionSimilarityThreshold = 80, $sumField = null): mixed
    {

        $sum = 0;

        if (!empty($data) && is_array($data)) {
            foreach ($data as $d) {
                if (SimpleCondition::compare($d[$conditionField], $conditionOperator, $conditionValue, $conditionSimilarityThreshold)) {
                    $sum += $d[$sumField];
                }
            }
        }
        return $sum;
    }

    /**
     * @param $data
     * @param $conditionField
     * @param $conditionOperator
     * @param $conditionValue
     * @param $conditionSimilarityThreshold
     * @param $conditionSimilarityTokenize
     * @param $returnKey
     * @return mixed
     * @throws UnknownOperatorException
     */
    public static function assoc_array_find($data, $conditionField, $conditionOperator, $conditionValue, $conditionSimilarityThreshold = 80, $conditionSimilarityTokenize= false, $returnKey = null): mixed
    {

        $response = null;

        if (!empty($data) && is_array($data)) {

            if (array_key_exists($conditionField, $data[0])) {
                foreach ($data as $d) {

                    if (SimpleCondition::compare($d[$conditionField], $conditionOperator, $conditionValue, $conditionSimilarityThreshold, $conditionSimilarityTokenize)) {
                        if (!empty($returnKey)) {
                            return $d[$returnKey];
                        }
                        return $d;
                    }
                }
            } else {
                foreach ($data as $d) {
                    $response[] = self::assoc_array_find($d, $conditionField, $conditionOperator, $conditionValue, $conditionSimilarityThreshold, $conditionSimilarityTokenize, $returnKey);
                }
            }
        }
        return $response;
    }

    /**
     * @param $text
     * @return string
     */
    public static function cleanText($text): string
    {
        // Convert text to lowercase
        $text = strtolower($text);

        // Remove URLs
        $text = preg_replace('/https?:\/\/\S+/', '', $text);

        // Remove or replace special characters (excluding spaces)
        $text = preg_replace('/[^a-z0-9\s]/', '', $text);

        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * @param $query
     * @param $choices
     * @return mixed|null
     */
    public static function fuzzy_extract_one($query, $choices, $minScore=50, $defaultChoice="", $fuzzyMethod = 'tokenSetRatio') {

        $fuzz = new Fuzz();
        $fuzzProcess = new Process();

        $result = $fuzzProcess->extractOne($query, $choices, null, [$fuzz, $fuzzyMethod]);

        if (!empty($result)) {
            $choice = $result[0];
            $score = $result[1];

            if ($score >= $minScore) {
                return $choice;
            }
        }
        return $defaultChoice;
    }

    public static function full_unescape($string) {
        return html_entity_decode(htmlspecialchars_decode($string, ENT_QUOTES), ENT_QUOTES);
    }

    private static function  custom_preg_escape($input) {

        // Define characters to escape
        $charsToEscape = ['/',"'", '"'];  // Add any other characters you'd like to escape

        // Escape each character
        foreach ($charsToEscape as $char) {
            $input = str_replace($char, '\\' . $char, $input);
        }

        return $input;
    }
    public static function transform_data($data, $transformFunction, $args = [], $targetKeys=[]) {

        if (!in_array($transformFunction, FunctionAction::SUPPORTED_FUNCTIONS)) {
            throw new UnknownOperatorException();
        }

        $specialFunctions = [
            'str_replace' => function($subject, $search, $replace) {
                if (str_contains($subject, $replace)) {
                    return $subject;
                }
                return str_replace($search, $replace, $subject);
            },
            'preg_replace' => function($subject, $pattern, $replacement) {
                if (str_contains($subject, $replacement)) {
                    return $subject;
                }
                return preg_replace($pattern, $replacement, $subject);
            },
            'dictionary_mapper' => function($value, $mappings) {
                // Set keys to lower case
                $mappings = array_change_key_case($mappings, CASE_LOWER);
                return $mappings[strtolower($value)] ?? $value;
            },
            'regex_mapper' => function($value, $mappings, $isCaseSensitive = false) {
                $modifier = !$isCaseSensitive ? 'i' : '';

                foreach ($mappings as $search => $replace) {

                    if (!str_contains($value, $replace)) {

                        $pattern = '/\b' . self::full_unescape($search) . '\b/'.$modifier;
                        $value = preg_replace($pattern, $replace, $value);
                    }
                }
                return preg_replace('/\s+/', ' ', $value);
            }
        ];

        // Extract options

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value)) {
                    $data[$key] = self::transform_data($value, $transformFunction, $args, $targetKeys);
                } else {
                    if (empty($targetKeys) || in_array($key, $targetKeys)) {
                        if (isset($specialFunctions[$transformFunction])) {
                            $data[$key] = $specialFunctions[$transformFunction]($value, ...$args);
                        } else {
                            $data[$key] = !empty($args) ? $transformFunction($value, ...$args) : $transformFunction($value);
                        }
                    }
                }
            }
        } elseif (empty($targetKeys)) {
            return isset($specialFunctions[$transformFunction])
                ? $specialFunctions[$transformFunction]($data, ...$args)
                : (!empty($args) ? $transformFunction($data, ...$args) : $transformFunction($data));
        }

        return $data;
    }



}
