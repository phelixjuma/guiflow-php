<?php

namespace PhelixJuma\DataTransformer\Utils;

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

    public static function concat(array $strings): string
    {
        return implode(" ", $strings);
    }

    /**
     * @param array $data
     * @param array $fields
     * @param $newField
     * @return array
     */
    public static function concat_multi_array_assoc(array $data, array $fields, $newField): array
    {

        array_walk($data, function (&$value, $key) use($fields, $newField) {
            $dataToConcat = [];
            foreach ($value as $key => $v) {
                if (in_array($key, $fields)) {
                    $dataToConcat[] = $v;
                }
            }
            $value[$newField] = self::concat($dataToConcat);
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
            'regex_mapper' => function($value, $mappings, $isCaseSensitive = false, $retainSearch=true) {
                $modifier = !$isCaseSensitive ? 'i' : '';
                foreach ($mappings as $search => $replace) {

                    $replace = $retainSearch && !empty($replace) ? "$search ($replace)" : $replace;

                    if (!str_contains($value, $replace)) {
                        $value = preg_replace('/\b' . preg_quote($search, '/') . '\b/' . $modifier, $replace, $value);
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
