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
        $separator = " $separator "; // add spaces to the separator
        return self::removeExtraSpaces(implode($separator, $strings));
    }

    /**
     * @param array $data
     * @param array $fields
     * @param $newField
     * @return array
     */
    public static function concat_multi_array_assoc(array $data, array $fields, $newField, $separator = " "): array
    {

        $separator = " $separator "; // add spaces to the separator

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
     * @param $data
     * @param $key
     * @return mixed|string
     */
    public static function get_from_object($data, $key) {

        if (array_key_exists($key, $data)) {
            return $data[$key];
        }
        return "";
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

        $isList = is_array($query);

        if (!$isList) {
            $query = [$query];
        }

        $extracted = [];

        if (!empty($query)) {
            foreach ($query as $key => $search) {

                // set default
                $extracted["$key-$search"] = $defaultChoice;

                $result = $fuzzProcess->extractOne($search, $choices, null, [$fuzz, $fuzzyMethod]);

                if (!empty($result)) {
                    $choice = $result[0];
                    $score = $result[1];

                    if ($score >= $minScore) {
                        $extracted["$key-$search"] = $choice;
                    }
                }
            }

            // We get the responses.
            $response = array_values($extracted);

            return !$isList ? $response[0] : $response;
        }
        return null;
    }

    public static function full_unescape($string) {
        return html_entity_decode(htmlspecialchars_decode($string, ENT_QUOTES), ENT_QUOTES);
    }

    /**
     * @param string $text
     * @return string
     */
    public static function removeExtraSpaces(string $text): string {
        return preg_replace('/\s+/', ' ', $text);
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
            'str_replace' => function($subject, $search, $replacement) {
                if (!empty($replacement) && str_contains($subject, $replacement)) {
                    return self::removeExtraSpaces($subject);
                }
                return self::removeExtraSpaces(str_replace($search, $replacement, $subject));
            },
            'preg_replace' => function($subject, $pattern, $replacement, $addSpacer=true, $isCaseSensitive=false) {

                $pattern = "/$pattern/";
                if (!$isCaseSensitive) {
                    $pattern .= "i";
                }

                if (!preg_match($pattern, $subject)) {
                    return $subject;
                }

                // Add a spacer to replacement
                if ($addSpacer) {
                    $replacement = " $replacement ";
                }

                return self::removeExtraSpaces(preg_replace($pattern, $replacement, $subject));
            },
            'explode' => function($string, $separator,) {
                return explode($separator, $string);
            },
            'string_to_date_time' => function($data, $pre_modifier="", $post_modifier="") {
                $date = null;
                if (is_array($data)) {
                    foreach ($data as $datum) {
                        $dateString = self::removeExtraSpaces("$pre_modifier $datum $post_modifier");
                        $date[] = date("Y-m-d H:i:s", strtotime($dateString));
                    }
                } else {
                    $dateString = self::removeExtraSpaces("$pre_modifier $data $post_modifier");
                    $date = date("Y-m-d H:i:s", strtotime($dateString));
                }
                return $date;
            },
            'dictionary_mapper' => function($value, $mappings) {
                // Set keys to lower case
                $mappings = array_change_key_case($mappings, CASE_LOWER);
                return $mappings[strtolower($value)] ?? $value;
            },
            'regex_mapper' => function($value, $mappings, $isCaseSensitive = false) {
                $modifier = !$isCaseSensitive ? 'i' : '';

                foreach ($mappings as $search => $replace) {

                    if (empty($replace) || !str_contains($value, $replace)) {

                        $pattern = '/\b' . self::full_unescape($search) . '\b/'.$modifier;
                        $value = preg_replace($pattern, $replace, $value);
                    }
                }
                return self::removeExtraSpaces($value);
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

    public static function removeKeysFromAssocArray($data, $keysToRemove): array
    {
        return array_diff_key($data, array_flip($keysToRemove));
    }

    /**
     * An associative array in php - akin to objects in other lands
     * @param $value
     * @return bool
     */
    public static function isObject($value) {
        return (is_array($value) && array_keys($value) !== range(0, count($value) - 1));
    }

}
