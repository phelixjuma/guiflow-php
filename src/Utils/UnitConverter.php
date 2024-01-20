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
     * @return mixed
     */
    public static function convert($conversionTable, $quantity, $from_unit, $to_unit): mixed
    {
        // Direct conversion
        foreach ($conversionTable as $conversion) {
            if ($conversion['from'] == $from_unit && $conversion['to'] == $to_unit) {
                return $quantity * $conversion['factor'];
            }
        }

        // Inverse conversion
        foreach ($conversionTable as $conversion) {
            if ($conversion['from'] == $to_unit && $conversion['to'] == $from_unit) {
                return $quantity / $conversion['factor'];
            }
        }
        return $quantity;
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
    public static function convert_multiple($data, $items, $conversionTable, $quantity, $fromUnit, $toUnit, $outputPath): array
    {
        $convertedItems = [];

        if (isset($conversionTable['path'])) {
            $conversionTable = PathResolver::getValueByPath($data, $conversionTable['path']);
        }

        $items = isset($items['path']) ? PathResolver::getValueByPath($data, $items['path']) : $items;

        foreach ($items as $item) {

            $conversionTable = isset($conversionTable['in_item_path']) ? PathResolver::getValueByPath($item, $conversionTable['in_item_path']) : $conversionTable;
            $quantity = isset($quantity['in_item_path']) ? PathResolver::getValueByPath($item, $quantity['in_item_path']) : $quantity;
            $fromUnit = isset($fromUnit['in_item_path']) ? PathResolver::getValueByPath($item, $fromUnit['in_item_path']) : $fromUnit;
            $toUnit = isset($toUnit['in_item_path']) ? PathResolver::getValueByPath($item, $toUnit['in_item_path']) : $toUnit;

            $convertedQuantity = self::convert($conversionTable, $quantity, $fromUnit, $toUnit);

            PathResolver::setValueByPath($item,  $outputPath, [
                "original_value" => $quantity,
                "converted_value" => $convertedQuantity
            ]);

            $convertedItems[] = $item;
        }

        return $convertedItems;
    }
}


