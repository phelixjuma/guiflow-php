<?php

namespace PhelixJuma\DataTransformer\Utils;

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
     * @return string
     */
    public static function custom_preg_replace($data, $pattern, $replacement): string
    {
        return preg_replace($pattern, $replacement, $data);
    }
}
