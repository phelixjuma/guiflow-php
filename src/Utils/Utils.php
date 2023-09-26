<?php

namespace PhelixJuma\DataTransformer\Utils;

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
     * @param $returnKey
     * @return mixed
     * @throws UnknownOperatorException
     */
    public static function assoc_array_find($data, $conditionField, $conditionOperator, $conditionValue, $conditionSimilarityThreshold = 80, $returnKey = null): mixed
    {

        $response = null;

        if (!empty($data) && is_array($data)) {

            if (array_key_exists($conditionField, $data[0])) {
                foreach ($data as $d) {

                    if (SimpleCondition::compare($d[$conditionField], $conditionOperator, $conditionValue, $conditionSimilarityThreshold)) {
                        if (!empty($returnKey)) {
                            return $d[$returnKey];
                        }
                        return $d;
                    }
                }
            } else {
                foreach ($data as $d) {
                    $response[] = self::assoc_array_find($d, $conditionField, $conditionOperator, $conditionValue, $conditionSimilarityThreshold, $returnKey);
                }
            }
        }
        return $response;
    }
}
