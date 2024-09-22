<?php

namespace PhelixJuma\GUIFlow\Utils;

use ArrayJoin\Builder;
use ArrayJoin\On;
use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Workflow;
use PhelixJuma\GUIFlow\Exceptions\UnknownOperatorException;

class Utils
{

    /**
     * @param $array
     * @param $key
     * @return mixed
     */
    public static function sortMultiAssocArrayByKey($array, $key, $order='asc')
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
     * @param $input
     * @param $format
     * @return string
     */
    public static function format_date($input, $format)
    {

        //return date($format, $date);
        if (is_numeric($input)) {
            $date = new \DateTime("@$input");
        } else {
            // Otherwise, try to parse the string directly
            try {
                $date = !empty($input) ? new \DateTime($input) : "";
            } catch (\Exception $e) {
                // If an exception is caught, the date format is not recognized
                return "Invalid date format: " . $e->getMessage();
            }
        }

        // Format the date
        return $date->format($format);
    }

    /**
     * @param $data
     * @param $stringsToAppend
     * @param $separator
     * @param $useDataAsPathValue
     * @param $valueKey
     * @param $condition
     * @return array|mixed|string|string[]|null
     */
    public static function prepend($data, $stringsToAppend, $separator = " ", $useDataAsPathValue = true, $valueKey=null, $condition = null)
    {

        $modifiedSeparator = " $separator ";
        $strings = implode($modifiedSeparator, $stringsToAppend);

        // If the data is an array, apply prepend recursively to each element
        if (is_array($data) && !self::isObject($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::prepend($value, $stringsToAppend, $modifiedSeparator, $useDataAsPathValue, $valueKey, $condition);
            }
            return $data;
        }

        // If it's not an array, apply the prepend logic to the string
        if (empty($condition) || Workflow::evaluateCondition($data, $condition, $useDataAsPathValue)) {

            if (self::isObject($data) && !empty($valueKey)) {

                $data[$valueKey] = self::removeExtraSpaces(  $strings . $modifiedSeparator . $data[$valueKey]);

                return $data;
            }
            return self::removeExtraSpaces($strings . $modifiedSeparator . $data);
        }

        return $data;
    }

    public static function append($data, $dataToAdd, $separator = " ", $useDataAsPathValue = true, $valueKey=null, $condition = null)
    {

        $stringsToAppend = self::getValues($data, $dataToAdd);

        $modifiedSeparator = " $separator ";
        $strings = implode($modifiedSeparator, $stringsToAppend);

        // If the data is an array, apply append recursively to each element
        if (is_array($data) && !self::isObject($data)) {
            foreach ($data as $key => $value) {
                $data[$key] = self::append($value, $stringsToAppend, $modifiedSeparator, $useDataAsPathValue, $valueKey, $condition);
            }

            return $data;
        }

        // If it's not an array, apply the append logic to the string
        if (empty($condition) || Workflow::evaluateCondition($data, $condition, $useDataAsPathValue)) {

            if (self::isObject($data) && !empty($valueKey)) {

                if (is_string($data[$valueKey])) {
                    $data[$valueKey] = self::removeExtraSpaces($data[$valueKey] . $modifiedSeparator . $strings);
                } else {
                    $data[$valueKey] = self::removeExtraSpaces($strings);
                }
                return $data;
            }
            if (is_string($data)) {
                return self::removeExtraSpaces($data . $modifiedSeparator . $strings);
            } else {
                return self::removeExtraSpaces($strings);
            }
        }
        return $data;
    }

    /**
     * @param $string
     * @param $enclosure
     * @return string
     */
    private static function enclose($string, $enclosure) {
        $string = trim($string);
        return match ($enclosure) {
            'brackets' => "($string)",
            'square brackets' => "[$string]",
            'curly brackets' => '{'.$string.'}',
            'forward strokes' => "/$string/",
            'backward strokes' => "\\$string\\",
            'double quotes' => '"'.$string.'"',
            'single quotes' => "'".$string."'",
            default => $string,
        };
    }

    /**
     * @param $masterData
     * @param $definitions
     * @return array|mixed|null
     */
    private static function getValues($masterData, $definitions): mixed
    {

        if (!is_array($definitions)) {
            return $definitions;
        }

        if (isset($definitions['path'])) {

            return PathResolver::getValueByPath($masterData, $definitions['path']);
        }

        foreach ($definitions as &$definition) {
            $definition = self::getValues($masterData, $definition);
        }
        return $definitions;
    }

    /**
     * @param $strings
     * @param $separator
     * @param $enclosure
     * @return array|string|string[]|null
     */
    public static function concat($data, $dataToAdd, $separator = " ", $enclosure="")
    {

        $stringsToAdd = self::getValues($data, $dataToAdd);

        $separator = " $separator "; // add spaces to the separator
        if (empty($enclosure)) {
            return self::removeExtraSpaces(implode($separator, $stringsToAdd));
        } else {
            $response = "";
            $numberOfItems = sizeof($stringsToAdd);

            for ($i = 0; $i < $numberOfItems; $i++) {

                $data = trim($stringsToAdd[$i]);

                if (!empty($data)) {
                    if ($i == 0) {
                        $response .= $separator.$data;
                    } else {
                        $response .= $separator.self::enclose($data, $enclosure);
                    }
                }
            }
            return self::removeExtraSpaces($response);
        }
    }

    /**
     * @param array $data
     * @param array $fields
     * @param $newField
     * @return array
     */
    public static function concat_multi_array_assoc($data, $fields, $newField, $separator = " ", $enclosure="")
    {

        $separator = " $separator "; // add spaces to the separator

        if (is_array($data)) {
            array_walk($data, function (&$value, $key) use($fields, $newField, $separator, $enclosure) {
                $dataToConcat = array_flip($fields);
                foreach ($value as $key => $v) {
                    if (in_array($key, $fields)) {
                        $dataToConcat[$key] = $v;
                    }
                }
                $value[$newField] = self::concat([], array_values($dataToConcat), $separator, $enclosure);
            });
        }
        return $data;
    }

    /**
     * @param $data
     * @param $uniqueKeyPath
     * @param $rankKeyPath
     * @param $rankOrder
     * @return array|mixed
     */
    public static function make_object_list_unique($data, $uniqueKeyPath, $rankKeyPath, $rankOrder='desc') {
        if (!is_array($data) || sizeof($data) == 1) {
            return $data;
        }

        // Create a unique list
        $uniqueList = [];
        $seen = [];

        $getUniqueHash =  function($item, $uniqueKeys) {
            $values = [];
            foreach ($uniqueKeys as $keys) {
                $values[] = PathResolver::getValueByPath($item, $keys);
            }
            return md5(serialize($values));
        };

        foreach ($data as $item) {
            $hash = $getUniqueHash($item, $uniqueKeyPath);
            $rankValue = PathResolver::getValueByPath($item, $rankKeyPath);

            if (!isset($seen[$hash])) {
                $seen[$hash] = ['rank' => $rankValue, 'item' => $item];
            } else {
                $betterRank = ($rankOrder === 'asc') ? $rankValue < $seen[$hash]['rank'] : $rankValue > $seen[$hash]['rank'];
                if ($betterRank) {
                    $seen[$hash] = ['rank' => $rankValue, 'item' => $item];
                }
            }
        }

        // Extract the best ranked items
        foreach ($seen as $entry) {
            $uniqueList[] = $entry['item'];
        }

        return $uniqueList;
    }

    /**
     * @param $data
     * @param $pattern
     * @param $replacement
     * @return string
     */
    public static function custom_preg_replace($data, $pattern, $replacement)
    {
        $replacement = str_ireplace("[space]", " ", $replacement);

        if (empty($pattern)) {
            return $data;
        }
        $pattern = "/".self::custom_preg_escape($pattern)."/i";

        $newData = null;
        if (self::isList($data)) {
            foreach ($data as $d) {
                $newData[] = is_string($d) ? preg_replace($pattern, $replacement, $d) : $d;
            }
        } else {
            $newData = is_string($data) ? preg_replace($pattern, $replacement, $data) : $data;
        }
        return trim($newData);
    }

    /**
     * @param $data
     * @param $key1
     * @param $key2
     * @param $regexPreModifier
     * @param $regexPostModifier
     * @param $newKey
     * @return array|mixed
     */
    public static function string_diff($data, $key1, $key2, $regexPreModifier=null, $regexPostModifier=null, $newKey=null)
    {
        if (empty($data) || empty($key1) || empty($key2)) {
            return $data;
        }

        $newData = null;
        if (self::isList($data)) {
            foreach ($data as $d) {
                $newData[] = self::string_diff($d, $key1, $key2, $regexPreModifier, $regexPostModifier, $newKey);
            }
        } elseif(self::isObject($data)) {

            // Get key 1 and 2 values
            $key1Value = self::removeExtraSpaces(PathResolver::getValueByPath($data, $key1));
            $key2Value = self::removeExtraSpaces(PathResolver::getValueByPath($data, $key2));

            if ($key1Value == null || $key2Value == null) {
                return $data;
            }

            // Create the patterns for both key1 and key2
            $pattern1 = "/{$regexPreModifier}".preg_replace('/\s+/', '\\s*', $key1Value)."{$regexPostModifier}/i";
            $pattern2 = "/{$regexPreModifier}".preg_replace('/\s+/', '\\s*', $key2Value)."{$regexPostModifier}/i";

            if (empty($newKey)) {
                $newKey = $key1;
            }

            // Check which pattern matches first
            if (preg_match($pattern1, $key2Value)) {
                PathResolver::setValueByPath($data, $newKey, self::removeExtraSpaces(preg_replace($pattern1, "", $key2Value)));
            } elseif (preg_match($pattern2, $key1Value)) {
                PathResolver::setValueByPath($data, $newKey, self::removeExtraSpaces(preg_replace($pattern2, "", $key1Value)));
            } else {
                // No match found; fallback to original data if needed
                PathResolver::setValueByPath($data, $newKey, $key1Value);
            }
            $newData = $data;
        } else {
            $newData = $data;
        }
        return $newData;
    }

    /**
     * @param $data
     * @param $mappers
     * @param $sortByOrder
     * @return array|mixed|string|string[]
     * @throws \Exception
     */
    public static function regex_mapper_multiple($data, $mappers, $sortByOrder=false)
    {

        // We sort the pattern
        if ($sortByOrder) {
            self::sortMultiAssocArrayByKey($mappers, 'order', 'asc');
        }

        $newData = null;
        if (self::isList($data)) {
            foreach ($data as $d) {
                $newData[] = self::regex_mapper_multiple($d, $mappers);
            }
        } else {

            $newData = $data;

            foreach ($mappers as $mapper) {

                try {

                    $pattern = $mapper['data']['pattern'];
                    $modifiers = $mapper['data']['modifiers'];
                    $replacementsMapper = $mapper['data']['replacements'];

                    // prepare pattern
                    $pattern = '/' . self::full_unescape($pattern) . '/'.$modifiers;

                    $tempNewData = preg_replace_callback($pattern, function($matches) use($pattern, $newData, $replacementsMapper) {

                        $replacement = $matches[1];

                        foreach ($replacementsMapper as $rMapper) {

                            $replacementPattern = str_ireplace("[space]", " ", $rMapper['pattern']);

                            $tempReplacement = preg_replace_callback("/(?:{$replacementPattern})/i", function($match) use ($rMapper) {
                                return $rMapper['replacement'];
                            }, $replacement);

                            if (!empty($tempReplacement)) {
                                $replacement = $tempReplacement;
                            }

                        }

                        return $replacement;

                    }, $newData);

                    if (!empty($tempNewData)) {
                        $newData = $tempNewData;
                    }

                    if (preg_last_error() !== PREG_NO_ERROR) {
                        throw new \Exception("Preg Error: ".self::getPregError(preg_last_error()));
                    }

                } catch (\Exception $e) {
                    print $e->getMessage();
                    throw new \Exception($e->getMessage());
                }
            }

        }

        return $newData;
    }

    /**
     * @param $data
     * @param $sumField
     * @param $condition
     * @return int|mixed
     */
    public static function assoc_array_sum_if($data, $sumField, $condition)
    {

        $sum = 0;

        if (!empty($data) && is_array($data)) {
            foreach ($data as $d) {
                if (Workflow::evaluateCondition($d, $condition, true)) {
                    $sum += $d[$sumField];
                }
            }
        }
        return $sum;
    }

    /**
     * @param $data
     * @param $condition
     * @param $returnKey
     * @return array|mixed
     */
    public static function assoc_array_find($data, $condition, $returnKey = null)
    {

        if (!empty($data) && is_array($data)) {
            foreach ($data as $d) {

                if (Workflow::evaluateCondition($d, $condition, true)) {
                    if (!empty($returnKey)) {
                        return $d[$returnKey];
                    }
                    return $d;
                }
            }
        }
        return null;
    }

    /**
     * @param $data
     * @param $days
     * @param $operator
     * @param $format
     * @return array
     */
    public static function date_add_subtract_days($data, $days, $operator, $format="Y-m-d") {

        $method = $operator == 'add' ? 'add' : 'sub';

        $response = null;

        try {

            if (is_array($data)) {
                foreach ($data as $datum) {
                    $response[] = (new \DateTime($datum))->$method(new \DateInterval("P{$days}D"))->format($format);
                }
            } else {
                $response = (new \DateTime($data))->$method(new \DateInterval("P{$days}D"))->format($format);
            }

        } catch (\Exception $e) {
        }
        return $response;
    }

    /**
     * @param $startDateString
     * @param $endDateString
     * @param $period
     * @return null
     */
    public static function date_diff($startDateString, $endDateString, $period="d") {

        $response = null;

        try {

            $startDate = new \DateTime($startDateString);
            $endDate = new \DateTime($endDateString);

            $diff = $startDate->diff($endDate);

            switch ($period) {
                case  'y':
                    $response = $diff->y;
                    break;
                case 'm':
                    $response = ($diff->y*12) + $diff->m;
                    break;
                case 'd':
                    $response = (365 * $diff->y) + (30 * $diff->m) + $diff->d;
                    break;
                case 'h':
                    $response = (24 * 365 * $diff->y) + (24 * 30 * $diff->m) + (24 * $diff->d) + $diff->h;
                    break;
                case 'i':
                    $response = (60 * 24 * 365 * $diff->y) + (60 * 24 * 30 * $diff->m) + (60 * 24 * $diff->d)  + (60 * $diff->h) + $diff->i;
                    break;
                case 's':
                    $response = (60 * 60 * 24 * 365 * $diff->y) + (60 * 60 * 24 * 30 * $diff->m) + (60 * 60 * 24 * $diff->d)  + (60 * 60 * $diff->h) + $diff->s;
                    break;
            }

        } catch (\Exception $e) {
        }
        return $response;
    }

    /**
     * @param $data
     * @param $format
     * @return array|string
     */
    public static function date_format($data, $format="Y-m-d") {

        $response = null;
        try {

            if (is_array($data)) {
                foreach ($data as $datum) {
                    $response[] = !empty($datum) ? (new \DateTime($datum))->format($format) : "";
                }
            } else {
                $response = !empty($data) ? (new \DateTime($data))->format($format) : "";
            }
        } catch (\Exception $e) {

        }
        return $response;
    }

    /**
     * @param $data
     * @param $pattern
     * @param $flag
     * @param $isCaseSensitive
     * @return string|string[]
     */
    public static function regex_extract($data, $pattern, $flag, $isCaseSensitive = false, $returnSubjectOnNull = false)
    {

        $pattern = "/".self::custom_preg_escape($pattern)."/";
        if (!$isCaseSensitive) {
            $pattern .= "i";
        }

        $response = preg_match_all($pattern, $data, $matches);

        if (!$response) {
            return $returnSubjectOnNull ? $data : "";
        }

        if (!is_array($matches[intval($flag)])) {
            return $returnSubjectOnNull ? $data : "";
        }

        return is_array($matches[intval($flag)]) ? $matches[intval($flag)][0] : '';
    }

    /**
     * @param $data
     * @param string $replacementKey The search key used to match the duplicated object to its replacement data
     * @param array $replacement The replacement data. For each replacement, add a new property defining the replacement_key name used for search
     * @param mixed $condition
     * @return mixed
     */
    public static function duplicate_list_item($data, $replacementKey = null, $replacement = null, $condition = null) {

        $length = count($data);

        for ($i = 0; $i < $length; $i++) {

            if (Workflow::evaluateCondition($data[$i], $condition)) {

                $newItem = $data[$i];

                // Optional: Modify certain values in the new item
                if (!is_null($replacement) && !is_null($replacementKey)) {

                    $replacementMappings = self::searchMultiArrayByKeyReturnKeys($replacement, $replacementKey, $data[$i][$replacementKey]);

                    if (!empty($replacementMappings)) {

                        foreach ($replacementMappings['replacements'] as $replaceKey => $replaceValue) {
                            $newItem[$replaceKey] = $replaceValue;
                        }
                    }

                }

                // Insert new item after current item
                array_splice($data, $i + 1, 0, array($newItem));

                // Skip the newly added item in the next iteration
                $i++;

                // Update the length of the array
                $length = count($data);
            }
        }

        return $data;
    }

    /**
     * @param $data
     * @param $operator
     * @param $operands
     * @param $defaultValue
     * @param $moduloHandler
     * @param $decimalPlaces
     * @param $condition
     * @return array|float|int|mixed|string|null
     */
    public static function basic_arithmetic($data, $operator, $operands, $defaultValue = "", $moduloHandler='round', $decimalPlaces = 2, $condition = null) {

        // Check condition
        if (empty($condition) || Workflow::evaluateCondition($data, $condition, false)) {

            $operandValues = [];

            foreach ($operands as $operand) {
                $value = !empty($operand['path']) ? PathResolver::getValueByPath($data, $operand['path']) : $operand;
                if (!empty($value)) {
                    $operandValues[] = $value;
                }
            }

            // multiplication
            if ($operator == 'multiply') {
                $product = 1;
                foreach ($operandValues as $value) {
                    $product *= $value;
                }
                return $product;
            }
            // division
            if ($operator == 'divide') {

                $dividend = floatval(self::recursiveDivide($operandValues));

                if ($moduloHandler == 'ceil') {
                    return ceil($dividend);
                } elseif ($moduloHandler == 'floor') {
                    return floor($dividend);
                } else {
                    return round($dividend, $decimalPlaces);
                }
            }
            // addition
            if ($operator == 'add') {
                $sum = 0;
                foreach ($operandValues as $value) {
                    $sum += floatval($value);
                }
                return $sum;
            }
            // subtraction
            if ($operator == 'subtract') {

                $difference = floatval($operandValues[0]);

                array_shift($operandValues);

                foreach ($operandValues as $value) {
                    $difference -= floatval($value);
                }
                return $difference;
            }
            // similarity score
            if ($operator == 'similarity_score') {

                $fuzz = new Fuzz();

                return $fuzz->tokenSetRatio($operandValues[0], $operandValues[1]);
            }

        } else {

            if (!empty($defaultValue)) {
                return !empty($defaultValue['path']) ? PathResolver::getValueByPath($data, $defaultValue['path']) : $defaultValue;
            }
        }
        return $data;
    }

    /**
     * @param $data
     * @return int
     */
    public static function length($data) {
        if (empty($data)) {
            return 0;
        }
        if (is_string($data)) {
            return strlen($data);
        }
        if (is_array($data)) {
            return sizeof($data);
        }
        return 0;
    }

    /**
     * @param $data
     * @return array|float|int|mixed|null
     */
    public static function absolute_value($data) {

        if (empty($data)) {
            return null;
        }
        if (!is_array($data)) {
            return is_numeric($data) ? abs($data) : $data;
        }

        foreach ($data as &$d) {
            $d = self::absolute_value($d);
        }

        return $data;
    }

    private static function recursiveDivide($numbers) {

        // Check if the array is empty or has only one element
        if (count($numbers) <= 1) {
            return count($numbers) === 1 ? $numbers[0] : 1;
        }

        // Take the first element
        $firstElement = array_shift($numbers);

        // Recursively call the function with the remaining elements
        return $firstElement / self::recursiveDivide($numbers);
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
     * @param $data
     * @param $keyMap
     * @return mixed
     */
    public static function rename_object_keys(&$data, $keyMap) {

        if (is_array($data)) {
            if (self::isObject($data)) {
                foreach ($keyMap as $oldKey => $newKey) {
                    if ($oldKey != $newKey && array_key_exists($oldKey, $data)) {
                        $data[$newKey] = $data[$oldKey];
                        unset($data[$oldKey]);
                    }
                }
            } else {

                $size =  sizeof($data);
                for($index = 0; $index < $size; $index++) {
                    foreach ($keyMap as $oldKey => $newKey) {
                        if ($oldKey != $newKey && array_key_exists($oldKey, $data[$index])) {
                            $data[$index][$newKey] = $data[$index][$oldKey];
                            unset($data[$index][$oldKey]);
                        }
                    }
                }

            }
        }
        return $data;
    }

    /**
     * @param $text
     * @return string
     */
    public static function cleanText($text)
    {
        // Convert text to lowercase
        $text = strtolower(trim($text));

        // Remove URLs
        //$text = preg_replace('/https?:\/\/\S+/', '', $text);
        // Remove or replace special characters (excluding spaces)
        //$text = preg_replace('/[^a-z0-9\s]/', '', $text);

        // Remove extra spaces
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    /**
     * @param $query
     * @param $choices
     * @param $minScore
     * @param $defaultChoice
     * @param $fuzzyMethod
     * @return array|mixed|null
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
        return $choices;
    }

    /**
     * @param $data
     * @param $query
     * @param $choices
     * @param $searchKey
     * @param $n
     * @param $minScore
     * @param $order
     * @param $fuzzyMethod
     * @param $stopWords
     * @return mixed|null
     */
    public static function fuzzy_extract_n($data, $query, $choices, $searchKey, $n='', $minScore='', $order='desc', $fuzzyMethod = 'tokenSetRatio', $stopWords = null) {

        // For strings, we split into an array
        if (is_string($stopWords)) {
            $stopWords = explode(",", $stopWords);
        }

        $fuzz = new Fuzz();

        if (!empty($query) && !empty($choices)) {

            // We get stop-words from the query
            $query = FuzzySearch::cleanText($query, $stopWords);

            // We add similarity score
            foreach ($choices as $key => &$choice) {

                // We remove stop words from choice
                $choiceSearch = FuzzySearch::cleanText($choice[$searchKey], $stopWords);

                if (!empty($query) && !empty($choiceSearch)) {
                    $choice['similarity'] = $fuzz->$fuzzyMethod($query, $choiceSearch);
                } else {
                    $choice['similarity'] = 0;
                }
            }

            // We sort in descending order
            $sortedData = self::sortMultiAssocArrayByKey($choices, 'similarity', $order);

            // no n - we get the top matching item(s)
            if (empty($n)) {
                // We get only those with the top match
                $topMatch = $sortedData[0]['similarity'];
                $sortedData = array_filter($sortedData, function ($data) use($topMatch) {
                    return $data['similarity'] == $topMatch;
                });
            } else {
                //$sortedData = array_slice($sortedData, 0, $n, true);
                $sortedData = self::get_top_n_ranked_items_by_key($sortedData, 'similarity', $n);
            }

            // min score given. We filter further to ensure none of the top n are below the min score
            if (!empty($minScore)) {
                $sortedData = array_filter($sortedData, function ($data) use($minScore) {
                    return $data['similarity'] >= $minScore;
                });
            }

            return array_values($sortedData);
        }
        return $choices;
    }

    public static function full_unescape($string) {
        return html_entity_decode(htmlspecialchars_decode($string, ENT_QUOTES), ENT_QUOTES);
    }

    public static function get_top_n_ranked_items_by_key($data, $key, $n) {

        // Step 1: Sort the data by the specified key in descending order
        usort($data, function($a, $b) use ($key) {
            return $b[$key] <=> $a[$key];
        });

        // Step 2: Group the items by the key to ensure all items with the same value share the same rank
        $groupedData = [];
        foreach ($data as $item) {
            $groupedData[$item[$key]][] = $item;
        }

        // Step 3: Collect the top N ranked items
        $result = [];
        $rankCount = 0;

        foreach ($groupedData as $group) {
            if ($rankCount >= $n) break;
            $result = array_merge($result, $group);
            $rankCount++;
        }

        return array_values($result);
    }

    /**
     * @param $string
     * @param $onlyInclude
     * @param $exclude
     * @param $additionalUoMs
     * @param $priority
     * @return string|null
     */
    public static function extract_unit($string, $onlyInclude = [], $exclude=[], $additionalUoMs = [], $priority = []) {

        // Define a mapping for units and their common abbreviations
        $unitMappings = [
            'G'         => ['G', 'GM', 'GMS', 'GRM', 'GRMS', 'GRAM', 'GRAMS', 'GRAMMES'],
            'KG'        => ['KG', 'KGS', 'K', 'KILOGRAM', 'KILOGRAMS'],
            'L'         => ['L', 'LTR', 'LTRS', 'LT', 'LTS', 'LITRE', 'LITRES', 'LITER', 'LITERS'],
            'ML'        => ['ML'],
            'PIECES'    => ['PCS', 'PC', 'PIECES', 'PIECE', 'EACH'],
            'PACKETS'   => ['PKT', 'PKTS', 'PACKET', 'PACKETS'],
            'BAGS'      => ['BG', 'BAG', 'BAGS'],
            'BALES'     => ['BL', 'BLS', 'BALE', 'BALES'],
            'BUNDLES'   => ['BDL', 'BDLS', 'BUNDLE', 'BUNDLES'],
            'CARTONS'   => ['CTN', 'CTNS', 'CARTON', 'CARTONS'],
            'PACKS'     => ['PACK', 'PACKS', 'PK'],
            'SETS'      => ['SET'],
            'BOXES'     => ['BOX', 'BOXES', 'BX'],
            'SACKS'     => ['SACK', 'SACKS'],
            'DOZENS'    => ['DOZEN', 'DOZENS', 'DZN', 'DZ', 'DZS'],
            'PAGES'     => ['PAGE', 'PAGES', 'PG', 'PGS'],
            'ROLLS'     => ['ROLL', 'ROLLS', 'RLLS'],
            'SHEETS'    => ['SHEET', 'SHEETS', 'SHT', 'SHTS'],
            "MM"        => ['MM', 'MILLIMETRE', 'MILLIMETER'],
            'M'         => ['M', 'MT', 'MTS', 'METER', 'METERS', 'METRE', 'METRES'],
            'CM'        => ['CM', 'CMS', 'CENTIMETER', 'CENTIMETERS', 'CENTIMETRE', 'CENTIMETRES'],
            'KM'        => ['KM', 'KMS', 'KILOMETER', 'KILOMETERS', 'KILOMETRE', 'KILOMETRES']
        ];

        // We add additional UoMs
        if (!empty($additionalUoMs)) {
            foreach ($additionalUoMs as $additionalUoM) {
                if (!empty($additionalUoM['uom']) && !empty($additionalUoM['variants'])) {
                    $uom = strtoupper($additionalUoM['uom']);
                    $variants = $additionalUoM['variants'];
                    $variants[] = $uom;

                    if (array_key_exists($uom, $unitMappings)) {
                        $unitMappings[$uom] = array_values(array_unique(array_merge($unitMappings[$uom], $variants)));
                    } else {
                        $unitMappings[$uom] = array_values(array_unique($variants));
                    }
                }
            }
        }

        // if only include is specified:
        if (!empty($onlyInclude)) {
            array_walk($onlyInclude, function (&$value, $key) {
               $value = strtoupper($value);
            });
            foreach ($unitMappings as $key => $value) {
                if (!in_array($key, $onlyInclude)) {
                    unset($unitMappings[$key]);
                }
            }
        }
        // if exclude is specified:
        if (!empty($exclude)) {
            array_walk($exclude, function (&$value, $key) {
                $value = strtoupper($value);
            });
            foreach ($exclude as $ex) {
                if (isset($unitMappings[$ex])) {
                    unset($unitMappings[$ex]);
                }
            }
        }

        // We check if dimension exists and remove it
        $string = preg_replace("/[\sx*](\d+(\.\d+)?\s?(MM|CM|Mts?|M)\s*x\s*\d+(\.\d+)?\s?(MM|CM|Mts?|M))/i", "", $string);

        // Flatten the unit mappings to a regex pattern for matching
        $unitPattern = [];
        foreach ($unitMappings as $standardUnit => $abbreviations) {
            foreach ($abbreviations as $abbreviation) {
                $unitPattern[] = preg_quote($abbreviation, '/');
            }
        }
        $unitPattern = implode('|', $unitPattern);

        // Define the regex to extract the quantity and unit from the string
        $pattern = '/[\s\wx\*](\d+(?:\.\d+)?)\s*(' . $unitPattern . ')/i';

        // Collect all matches in case we have multiple units in the string
        preg_match_all($pattern, $string, $matches, PREG_SET_ORDER);

        // If units are found
        if ($matches) {
            $foundUnits = [];

            // Normalize each found unit and store it
            foreach ($matches as $match) {
                $quantity = $match[1]; // The number part (e.g., "20")
                $unit = strtoupper($match[2]); // The extracted unit (e.g., "G")

                // Normalize the unit based on the mappings
                foreach ($unitMappings as $standardUnit => $abbreviations) {
                    if (in_array(strtoupper($unit), $abbreviations)) {
                        $foundUnits[] = [
                            'unit' => $standardUnit,
                            'quantity' => $quantity
                        ];
                        break;
                    }
                }
            }

            // Sort found units based on priority
            if (!empty($priority)) {
                usort($foundUnits, function ($a, $b) use ($priority) {
                    $priorityA = array_search($a['unit'], $priority);
                    $priorityB = array_search($b['unit'], $priority);

                    // If both have priorities, compare them
                    if ($priorityA !== false && $priorityB !== false) {
                        return $priorityA <=> $priorityB;
                    }
                    // If one has priority, it comes first
                    if ($priorityA !== false) return -1;
                    if ($priorityB !== false) return 1;

                    // Otherwise, keep the original order
                    return 0;
                });
            }

            // Return the highest priority unit with its quantity
            if (!empty($foundUnits)) {
                return $foundUnits[0]['quantity'] . $foundUnits[0]['unit'];
            }
        }
        // Return null if no unit found
        return null;
    }

    /**
     * @param $text
     * @return array|string|string[]|null
     */
    public static function removeExtraSpaces($text) {
        if (!is_null($text)) {
            // remove extra spaces
            $text = preg_replace('/\s+/', ' ', trim($text));
            // remove spaces around words inside enclosers
            return preg_replace('/([\(\[\{])\s*(.*?)\s*([\)\]\}])/i', '$1$2$3', $text);
        }
        return $text;
    }

    public static function remove_repeated_words($data) {

        if (is_array($data)) {
            foreach ($data as &$value) {
                $value = self::remove_repeated_words($value);
            }
        } else {
            $words = explode(' ', $data); // Split the string into words
            $seen = array();                // Array to track seen words in lowercase
            $result = array();              // Array to hold the final words

            foreach ($words as $word) {
                $lowercaseWord = strtolower($word); // Convert the word to lowercase for comparison
                if (!isset($seen[$lowercaseWord])) {
                    $seen[$lowercaseWord] = true;    // Mark the lowercase word as seen
                    $result[] = $word;               // Add the original word to the result
                }
            }

            return self::removeExtraSpaces(implode(' ', $result));   // Join the words back into a string
        }
        return $data;
    }

    public static function  custom_preg_escape($input) {

        // Define characters to escape
        $charsToEscape = ['/',"'", '"'];  // Add any other characters you'd like to escape

        // Escape each character
        foreach ($charsToEscape as $char) {
            $input = str_replace($char, '\\' . $char, $input);
        }

        return $input;
    }

    /**
     * @param $error
     * @return string
     */
    public static function getPregError($error) {
        return match ($error) {
            PREG_INTERNAL_ERROR => 'There was an internal error!',
            PREG_BACKTRACK_LIMIT_ERROR => 'Backtrack limit was exhausted!',
            PREG_RECURSION_LIMIT_ERROR => 'Recursion limit was exhausted!',
            PREG_BAD_UTF8_ERROR => 'Bad UTF-8 error!',
            PREG_BAD_UTF8_OFFSET_ERROR => 'Bad UTF-8 offset error!',
            PREG_JIT_STACKLIMIT_ERROR => 'JIT stack limit error!',
            default => $error. 'Unknown error!',
        };
    }

    /**
     * @param $data
     * @param $transformFunction
     * @param $args
     * @param $targetKeys
     * @param $condition
     * @return mixed
     * @throws UnknownOperatorException
     */
    public static function transform_data($data, $transformFunction, $args = [], $targetKeys=[], $condition=[]) {

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
            'preg_replace' => function($subject, $pattern, $replacement, $add_spacer=true, $is_case_sensitive=false) {

                $pattern = "/".self::custom_preg_escape($pattern)."/";
                if (!$is_case_sensitive) {
                    $pattern .= "i";
                }

                if (!preg_match($pattern, $subject)) {
                    return $subject;
                }

                // Add a spacer to replacement
                if ($add_spacer) {
                    $replacement = " $replacement ";
                }

                return self::removeExtraSpaces(preg_replace($pattern, $replacement, $subject));
            },
            'explode' => function($string, $separator,) {
                return explode($separator, $string);
            },
            'string_to_date_time' => function($data, $format="Y-m-d H:i:s", $pre_modifier="", $post_modifier="") {


                $getCurrentTime = function() {
                    return (new \DateTime("now"))->format("H:i:s"); // Returns the current time
                };

                $convertToDate = function($dateString, $format, $getCurrentTime) {
                    // Check if time part is present by looking for a colon, which appears in time strings
                    if (!str_contains($dateString, ':')) {
                        // If no time part is present, append the current time
                        $dateString .= ' ' . $getCurrentTime();
                    }
                    return !empty($dateString) ? (new \DateTime($dateString))->format($format) : "";
                };

                $date = null;
                if (is_array($data)) {
                    foreach ($data as $datum) {
                        $dateString = self::removeExtraSpaces("$pre_modifier $datum $post_modifier");
                        $date[] = $convertToDate($dateString, $format, $getCurrentTime);
                    }
                } else {
                    $dateString = self::removeExtraSpaces("$pre_modifier $data $post_modifier");
                    $date = $convertToDate($dateString, $format, $getCurrentTime);
                }
                return $date;
            },
            'dictionary_mapper' => function($value, $mappings) {
                // Set keys to lower case
                $mappings = array_change_key_case($mappings, CASE_LOWER);
                return $mappings[strtolower($value)] ?? $value;
            },
            'regex_mapper' => function($value, $mappings, $is_case_sensitive = false) {

                $modifier = !$is_case_sensitive ? 'i' : '';

                foreach ($mappings as $key => $mapping) {

                    if (is_array($mapping) && isset($mapping['pattern']) && $mapping['replacement']) {
                        $search = $mapping['pattern'];
                        $replace = $mapping['replacement'];
                    } else {
                        $search = $key;
                        $replace = $mapping;
                    }

                    $pattern = '/' . self::custom_preg_escape(self::full_unescape($search)) . '/'.$modifier;
                    $replace = str_ireplace("[space]", " ", $replace);

                    if (!empty($value) && is_string($value)) {
                        $value = preg_replace($pattern, $replace, $value);
                    }

                    if (preg_last_error() !== PREG_NO_ERROR) {
                        //throw new \Exception("Preg Error: ".self::getPregError(preg_last_error()));
                    }
                }
                return self::removeExtraSpaces($value);
            }
        ];

        // Extract options

        if (is_array($data)) {
            foreach ($data as $key => $value) {

                if (empty($condition) || Workflow::evaluateCondition($value, $condition, true)) {

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
            }
        } elseif (empty($targetKeys)) {

            if (empty($condition) || Workflow::evaluateCondition($data, $condition, true)) {
                return isset($specialFunctions[$transformFunction])
                    ? $specialFunctions[$transformFunction]($data, ...$args)
                    : (!empty($args) ? $transformFunction($data, ...$args) : $transformFunction($data));
            }
        }

        return $data;
    }

    public static function removeKeysFromAssocArray($data, $keysToRemove)
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

    public static function isList($value) {
        return (is_array($value) && array_keys($value) == range(0, count($value) - 1));
    }

    /**
     * Flattens an object. Child objects are removed and their values set on the parent using parent.child path notation
     * @param $obj
     * @param $prefix
     * @return array
     */
    public static function flattenObject($obj, $prefix = '') {

        $flattened = [];

        foreach ($obj as $key => $value) {
            $newKey = $prefix . $key;
            if (is_array($value) && array_values($value) !== $value) { // Associative array (dict in python)
                $nested = self::flattenObject($value, $newKey . '.');
                $flattened = array_merge($flattened, $nested);
            } elseif (is_array($value) && array_values($value) === $value) { // Indexed array (list in python)
                foreach ($value as $index => $item) {
                    $value[$index] = is_array($item) && array_values($item) !== $item ? self::flattenObject($item) : $item;
                }
                $flattened[$newKey] = $value;
            } else {
                $flattened[$newKey] = $value;
            }
        }

        return $flattened;
    }

    /**
     * Flattens complex nested array into a simple one-level array of objects. Nested objects are brought to the parent level. Nested arrays are expanded and split eg ["preferences" => ["colors" => ["blue", "green"]]] becomes [["preferences.colors" => "blue"],["preferences.colors" => "green"]]
     * @param $data
     * @param $prefix
     * @param $siblings
     * @return array
     */
    public static function expandList($data, $prefix = null, $siblings = []) {
        $expanded = [];

        if (is_array($data) && array_values($data) === $data) { // Indexed array
            foreach ($data as $item) {
                $expanded = array_merge($expanded, self::expandList($item, $prefix, $siblings));
            }
        } elseif (is_array($data)) { // Associative array
            $nonListValues = [];
            $listValues = [];
            foreach ($data as $key => $value) {
                $newKey = $prefix ? $prefix . '.' . $key : $key;
                if (is_array($value) && array_values($value) === $value) {
                    $listValues[$key] = $value;
                } else {
                    $nonListValues[$newKey] = $value;
                }
            }

            if (!empty($listValues)) {
                foreach ($listValues as $key => $value) {
                    $newKey = $prefix ? $prefix . '.' . $key : $key;
                    $expanded = array_merge($expanded, self::expandList($value, $newKey, array_merge($siblings, $nonListValues)));
                }
            } else {
                $expanded[] = array_merge($siblings, $nonListValues);
            }
        } else {
            $expanded[] = [$prefix => $data];
        }

        return $expanded;
    }

    /**
     * @param $data
     * @return array
     */
    public static function flattenAndExpand($data) {
        $flattenedData = self::flattenObject($data);
        return self::expandList($flattenedData);
    }

    /**
     * @param array $data
     * @return array
     */
    public static function collapseData($data): array
    {
        if (!is_array($data)) {
            return $data;
        }

        $collapsed = [];

        if (self::isList($data)) {
            foreach ($data as $d) {
                $collapsed[] = self::collapseData($d);
            }
        } else {
            // First, we flatten the data
            $flattenedData = self::flattenAndExpand($data);

            // We then loop through the data and build the new collapsed data
            foreach ($flattenedData[0] as $path => $flattenedDatum) {
                PathResolver::setValueByPath($collapsed, $path, $flattenedDatum);
            }
        }

        return $collapsed;
    }

    /**
     * @param $data
     * @param $leftData
     * @param $rightData
     * @param $join
     * @param $fields
     * @param $groupBy
     * @return array|null
     */
    public static function join($data, $leftData, $rightData, $join, $fields, $groupBy = null): ?array
    {

        if (isset($leftData['path'])) {
            $leftData = PathResolver::getValueByPath($data, $leftData['path']);
        }
        if (isset($rightData['path'])) {
            $rightData = PathResolver::getValueByPath($data, $rightData['path']);
        }

        $response = null;

        try {
            $instance = Builder::newInstance()
                ->select(...$fields)
                ->from($leftData, "left");

            if ($join['type'] == 'inner') {
                $instance->innerJoin($rightData, "right", new On($join['on']));
            } elseif ($join['type'] == 'left') {
                $instance->leftJoin($rightData, "right", new On($join['on']));
            } elseif ($join['type'] == 'right') {
                $instance->rightJoin($rightData, "right", new On($join['on']));
            }

            if (!empty($groupBy)) {
                $groupByFields = !is_array($groupBy)? [$groupBy] : $groupBy;
                $instance->groupBy(...$groupByFields);
            }

            $instance->setFetchType(Builder::FETCH_TYPE_ARRAY);

            $response = $instance->execute();

        } catch (\Exception $e) {
            print "Exception: ".$e->getMessage();
        }

        return $response;
    }

    private static function searchMultiArrayByKey($arrayData, $searchKey, $searchValue) {
        $foundData = array();
        $size = sizeof($arrayData);
        for ($i = 0; $i < $size; $i++) {
            if ($arrayData[$i][$searchKey] == $searchValue) {
                $foundData[] = $arrayData[$i];
            }
        }
        return $foundData;
    }

    public static function searchMultiArrayByKeyReturnKeys($arrayData, $searchKey, $searchValue) {
        $size = is_array($arrayData) ? sizeof($arrayData) : 0;
        for ($i = 0; $i < $size; $i++) {
            if (strtolower($arrayData[$i][$searchKey]) == strtolower($searchValue)) {
                return $arrayData[$i];
            }
        }
        return false;
    }

    /**
     * @param $searchList
     * @param $searchKey
     * @param $corpusList
     * @param $corpusKey
     * @param $searchStemmingPatterns
     * @param $corpusStemmingPatterns
     * @param $similarityThreshold
     * @param $stopWords
     * @return mixed
     */
    public static function pattern_based_stem_spell_corrections($searchList, $searchKey, $corpusList, $corpusKey, $searchStemmingPatterns, $corpusStemmingPatterns, $similarityThreshold=90, $stopWords = null) {

        // We stem the corpus list
        array_walk($corpusList, function (&$value, $key) use($corpusKey, $corpusStemmingPatterns) {
            if (!empty($value[$corpusKey])) {

                $stemKey = $corpusKey."_stem";
                $value[$stemKey] = $value[$corpusKey];

                foreach ($corpusStemmingPatterns as $stemmingPattern) {
                    // pattern
                    $pattern = '/' . self::custom_preg_escape(self::full_unescape($stemmingPattern)) . '/i';
                    // set the stem key
                    $value[$stemKey] = !empty($value[$stemKey]) && is_string($value[$stemKey]) ? self::removeExtraSpaces(preg_replace($pattern, "", $value[$stemKey])) : $value[$stemKey];

                    if (preg_last_error() !== PREG_NO_ERROR) {
                        //throw new \Exception("Preg Error: ".self::getPregError(preg_last_error()));
                    }
                }
            }
        });

        // We stem the search list
        array_walk($searchList, function (&$value, $key) use($searchKey, $searchStemmingPatterns, $corpusList, $corpusKey ,$similarityThreshold, $stopWords) {

            if (!empty($value[$searchKey])) {

                $stemKey = $searchKey."_stem";
                $value['spell_check_meta_data'][$stemKey] = $value[$searchKey];

                foreach ($searchStemmingPatterns as $stemmingPattern) {
                    // pattern
                    $pattern = '/' . self::custom_preg_escape(self::full_unescape($stemmingPattern)) . '/i';
                    // set the stem key
                    $value['spell_check_meta_data'][$stemKey] = is_string($value['spell_check_meta_data'][$stemKey]) ? self::removeExtraSpaces(preg_replace($pattern, "", $value['spell_check_meta_data'][$stemKey])) : $value['spell_check_meta_data'][$stemKey];

                    if (preg_last_error() !== PREG_NO_ERROR) {
                        //throw new \Exception("Preg Error: ".self::getPregError(preg_last_error()));
                    }
                }

                // We get the corpus stem key
                $corpusStemKey = $corpusKey."_stem";

                // We get the top match
                $topMatch = self::fuzzy_extract_n(null, $value['spell_check_meta_data'][$stemKey], $corpusList, $corpusStemKey, 1, null,'desc', 'tokenSetRatio', $stopWords);

                // Set the nearest stem
                $value['spell_check_meta_data']['nearest_stem'] = "";
                $value['spell_check_meta_data']['nearest_stem_similarity'] = !empty($topMatch) ? $topMatch[0]['similarity'] : 0;
                if (!empty($topMatch) && $topMatch[0]['similarity'] >= $similarityThreshold) {
                    $value['spell_check_meta_data']['nearest_stem'] = $topMatch[0][$corpusStemKey];
                }

                // We perform spell correction
                if (!empty($value['spell_check_meta_data']['nearest_stem'])) {
                    $value[$searchKey] = is_string($value[$searchKey]) ? self::removeExtraSpaces(preg_replace("/{$value['spell_check_meta_data'][$stemKey]}/i", $value['spell_check_meta_data']['nearest_stem'], $value[$searchKey])) : $value[$searchKey];
                }

            }
        });

        return $searchList;

    }

    /**
     * @param $date
     * @return mixed|string
     */
    public static function correct_date($date) {

        if (is_array($date)) {
            foreach ($date as &$d) {
                $d = self::correct_date($d);
            }
            return $date;
        }

        // Get the current date
        $currentDate = new \DateTime();

        // Try to parse the original date
        $originalDateObj = self::parseDate($date);

        // If original date is invalid, return the original date (uncorrectable)
        if (!$originalDateObj) {
            return $date;
        }

        // Extract day, month, and year components
        $originalDay = $originalDateObj->format('d');
        $originalMonth = $originalDateObj->format('m');
        $originalYear = $originalDateObj->format('Y');

        // Swap day and month
        $swappedDateStr = $originalYear . '-' . $originalDay . '-' . $originalMonth;
        $swappedDateObj = self::parseDate($swappedDateStr);

        // If both dates are valid, choose the one closest to the current date
        if ($swappedDateObj) {
            $originalDiff = abs($currentDate->getTimestamp() - $originalDateObj->getTimestamp());
            $swappedDiff = abs($currentDate->getTimestamp() - $swappedDateObj->getTimestamp());

            // Return the date that is closest to the current date
            $correctedDateObj = ($originalDiff <= $swappedDiff) ? $originalDateObj : $swappedDateObj;
        } else {
            // If only the original date is valid, use it
            $correctedDateObj = $originalDateObj;
        }

        return $correctedDateObj->format('Y-m-d');
    }

    private static function parseDate($date) {

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }
        return (new \DateTime())->setTimestamp($timestamp);
    }

}
