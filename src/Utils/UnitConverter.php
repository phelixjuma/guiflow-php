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
     * @return float|int
     */
    public static function convert($conversionTable, $quantity, $from_unit, $to_unit): float|int
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
     * @param $conversionTable
     * @param $items
     * @return array
     */
    public static function convert_multiple($conversionTable, $items): array
    {
        $convertedItems = [];

        foreach ($items as $item) {
            $convertedQuantity = self::convert($conversionTable, $item['quantity'], $item['from_unit'], $item['to_unit']);
            $item['quantity'] = [
                "original_value" => $item['quantity'],
                "converted_value" => $convertedQuantity
            ];
            $convertedItems[] = $item;
        }

        return $convertedItems;
    }
}


