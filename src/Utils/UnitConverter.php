<?php

namespace PhelixJuma\GUIFlow\Utils;


use Exception;
use PhelixJuma\GUIFlow\Conditions\SimpleCondition;

class UnitConverter
{
    public function __construct()
    {
    }

    /**
     * @param $data
     * @param $itemQuantity
     * @param $itemUnit
     * @param $convertToUnit
     * @param $numberOfPiecesPerBundle
     * @param $additionalPiecesUoMs
     * @param $decimalHandler
     * @param $numberOfDecimalPlaces
     * @return array
     * @throws Exception
     */
    public static function convert_units_v2($data, $itemQuantity, $itemUnit, $convertToUnit, $numberOfPiecesPerBundle, $additionalPiecesUoMs = [], $decimalHandler="up", $numberOfDecimalPlaces=0) {

        // Special conversions
        if (preg_match("/(dozen|doz|daz)/i", $itemUnit)) {
            // We convert dozens to pieces
            $itemQuantity = 12 * $itemQuantity;
            $itemUnit = 'Pieces';
        }

        $response = [
            "original_value"    => $itemQuantity,
            "original_unit"     => $itemUnit,
            "converted_unit"    => $convertToUnit
        ];

        // We sent pieces units
        $piecesUoMs = [
            "PCS", "PC", "Piece", "Each", "Packet", "PKT", "KG", "KGS",
            "G", "GM", "GMS"
        ];
        if (!empty($additionalPiecesUoMs)) {
            // remove empty values in additional uoms
            $additionalPiecesUoMs = array_filter($additionalPiecesUoMs);
            $piecesUoMs = array_values(array_unique(array_merge($piecesUoMs, $additionalPiecesUoMs)));
        }

        $isItemUnitInPieces = SimpleCondition::compare($itemUnit, "in list any", $piecesUoMs);
        $isConvertToUnitInPieces = SimpleCondition::compare($convertToUnit, "in list any", $piecesUoMs);

        // Handle conversion to Pieces
        if ($isConvertToUnitInPieces) {
            if ($isItemUnitInPieces) {
                // no conversion needed
                $response['converted_value'] = $itemQuantity;
            } else {
                // Unit is in bundles; we convert to pieces
                $convertedValue = $numberOfPiecesPerBundle * $itemQuantity;

                $response['converted_value'] = match($decimalHandler) {
                    "up" =>   ceil($convertedValue),
                    "down" =>   floor($convertedValue),
                    "off" =>   round($convertedValue, intval($numberOfDecimalPlaces)),
                    default => $convertedValue
                };
            }
        }
        // Handle conversion to bundles
        else {
            if (!$isItemUnitInPieces) {
                // no conversion needed since item unit is in bundles
                $response['converted_value'] = $itemQuantity;
            } else {
                // Unit is not in bundles; we convert to bundles
                $convertedValue = $itemQuantity / $numberOfPiecesPerBundle;

                $response['converted_value'] = match($decimalHandler) {
                    "up" =>   ceil($convertedValue),
                    "down" =>   floor($convertedValue),
                    "off" =>   round($convertedValue, intval($numberOfDecimalPlaces)),
                    default => $convertedValue
                };
            }
        }

        return $response;
    }

    /**
     * @param $conversionTable
     * @param $quantity
     * @param $from_unit
     * @param $to_unit
     * @param $invertFactor
     * @param $decimalHandler
     * @param $numberOfDecimalPlaces
     * @return mixed
     */
    public static function convert($conversionTable, $quantity, $from_unit, $to_unit, $invertFactor=false, $decimalHandler="up", $numberOfDecimalPlaces=0): mixed
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

                $convertedValue = $quantity * $factor;

                $response['converted_value'] = match($decimalHandler) {
                   "up" =>   ceil($convertedValue),
                   "down" =>   floor($convertedValue),
                   "off" =>   round($convertedValue, intval($numberOfDecimalPlaces)),
                    default => $convertedValue
                };
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

                $convertedValue = $quantity / $factor;

                $response['converted_value'] = match($decimalHandler) {
                    "up" =>   ceil($convertedValue),
                    "down" =>   floor($convertedValue),
                    "off" =>   round($convertedValue, intval($numberOfDecimalPlaces)),
                    default => $convertedValue
                };
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
     * @param $invertFactor
     * @param $decimalHandler
     * @param $numberOfDecimalPlaces
     * @param $outputPath
     * @return array
     */
    public static function convert_multiple($data, $items, $conversionTable, $quantity, $fromUnit, $toUnit, $invertFactor,$decimalHandler, $numberOfDecimalPlaces, $outputPath): array
    {
        if (isset($conversionTable['path'])) {
            $conversionTable = PathResolver::getValueByPath($data, $conversionTable['path']);
        }

        $items = isset($items['path']) ? PathResolver::getValueByPath($data, $items['path']) : $items;

        array_walk($items, function (&$item, $key) use($conversionTable, $quantity, $fromUnit, $toUnit, $invertFactor, $decimalHandler, $numberOfDecimalPlaces, $outputPath) {

            $conversionTable = isset($conversionTable['in_item_path']) ? PathResolver::getValueByPath($item, $conversionTable['in_item_path']) : $conversionTable;
            $quantity = isset($quantity['in_item_path']) ? PathResolver::getValueByPath($item, $quantity['in_item_path']) : $quantity;
            $fromUnit = isset($fromUnit['in_item_path']) ? PathResolver::getValueByPath($item, $fromUnit['in_item_path']) : $fromUnit;
            $toUnit = isset($toUnit['in_item_path']) ? PathResolver::getValueByPath($item, $toUnit['in_item_path']) : $toUnit;

            $conversionResponse = self::convert($conversionTable, $quantity, $fromUnit, $toUnit, $invertFactor, $decimalHandler, $numberOfDecimalPlaces);

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


