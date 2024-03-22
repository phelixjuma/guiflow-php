<?php

namespace PhelixJuma\DataTransformer\Utils;


class UnitConverter
{
    public function __construct()
    {
    }

    /**
     * @param $conversionTable
     * @param $quantity
     * @param $from_unit
     * @param $to_unit
     * @param $invertFactor
     * @return mixed
     */
    public static function convert($conversionTable, $quantity, $from_unit, $to_unit, $invertFactor=false): mixed
    {

        $from_unit = strtolower($from_unit);
        $to_unit = strtolower($to_unit);

        $response = [
            "original_value"    => $quantity,
            "original_unit"     => $from_unit,
            "converted_value"   => $quantity,
            "converted_unit"    => $from_unit
        ];

        if (empty($quantity)) {
            return $response;
        }

        // Direct conversion
        foreach ($conversionTable as $conversion) {

            if (strtolower($conversion['from']) == $from_unit && strtolower($conversion['to']) == $to_unit) {

                $factor = 1;
                if (!empty($conversion['factor']) && $conversion['factor'] > 0) {
                    $factor = floatval($conversion['factor']);
                    if ($invertFactor) {
                        $factor = 1/$factor;
                    }
                }

                $response['converted_value'] = ceil($quantity * $factor);
                $response['converted_unit'] = $to_unit;
            }
        }

        // Inverse conversion
        foreach ($conversionTable as $conversion) {

            if (strtolower($conversion['from']) == $to_unit && strtolower($conversion['to']) == $from_unit) {

                $factor = 1;
                if (!empty($conversion['factor']) && $conversion['factor'] > 0) {
                    $factor = floatval($conversion['factor']);
                    if ($invertFactor) {
                        $factor = 1/$factor;
                    }
                }

                $response['converted_value'] = ceil($quantity / $factor);
                $response['converted_unit'] = $to_unit;
            }
        }
        return $response;
    }

    /**
     * @param $data
     * @param $items
     * @param $conversionTable
     * @param $quantity
     * @param $fromUnit
     * @param $toUnit
     * @param $outputPath
     * @return array
     */
    public static function convert_multiple($data, $items, $conversionTable, $quantity, $fromUnit, $toUnit, $invertFactor, $outputPath): array
    {
        if (isset($conversionTable['path'])) {
            $conversionTable = PathResolver::getValueByPath($data, $conversionTable['path']);
        }

        $items = isset($items['path']) ? PathResolver::getValueByPath($data, $items['path']) : $items;

        array_walk($items, function (&$item, $key) use($conversionTable, $quantity, $fromUnit, $toUnit, $invertFactor, $outputPath) {

            $conversionTable = isset($conversionTable['in_item_path']) ? PathResolver::getValueByPath($item, $conversionTable['in_item_path']) : $conversionTable;
            $quantity = isset($quantity['in_item_path']) ? PathResolver::getValueByPath($item, $quantity['in_item_path']) : $quantity;
            $fromUnit = isset($fromUnit['in_item_path']) ? PathResolver::getValueByPath($item, $fromUnit['in_item_path']) : $fromUnit;
            $toUnit = isset($toUnit['in_item_path']) ? PathResolver::getValueByPath($item, $toUnit['in_item_path']) : $toUnit;

            $conversionResponse = self::convert($conversionTable, $quantity, $fromUnit, $toUnit, $invertFactor);

            PathResolver::setValueByPath($item,  $outputPath, $conversionResponse);

        });

        return $items;
    }

    /**
     * @return array[]
     */
    public static function get_metric_conversion_table(): array
    {
        return [
            // length
            ["from" => "mm", "to" => "m", "factor" => 0.001],
            ["from" => "cm", "to" => "m", "factor" => 0.01],
            ["from" => "dm", "to" => "m", "factor" => 0.1],
            ["from" => "m", "to" => "m", "factor" => 1],
            ["from" => "km", "to" => "m", "factor" => 1000],

            // mass
            ["from" => "mg", "to" => "g", "factor" => 0.001],
            ["from" => "g", "to" => "g", "factor" => 1],
            ["from" => "kg", "to" => "g", "factor" => 1000],

            // Volume
            ["from" => "ml", "to" => "l", "factor" => 0.001],
            ["from" => "cl", "to" => "l", "factor" => 0.01],
            ["from" => "l", "to" => "l", "factor" => 1],

        ];
    }
}


