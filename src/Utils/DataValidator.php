<?php

namespace PhelixJuma\GUIFlow\Utils;

use ArrayJoin\Builder;
use ArrayJoin\On;
use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Workflow;
use PhelixJuma\GUIFlow\Exceptions\UnknownOperatorException;

class DataValidator
{

    /**
     * @param $data
     * @param $quantityPath
     * @param $unitPricePath
     * @param $totalPricePath
     * @return mixed
     */
    public static function validateAndCorrectQuantityUsingPrice($data, $quantityPath, $unitPricePath, $totalPricePath)
    {
        // We first check where quantity is empty
        foreach ($data as &$d) {

            $quantity = PathResolver::getValueByPath($d, $quantityPath);
            $unitPrice = PathResolver::getValueByPath($d, $unitPricePath);
            $totalPrice = PathResolver::getValueByPath($d, $totalPricePath);

            $quantityValid = true; // assume quantity is valid, by default
            $d['quantity_validation'] = [
                'is_corrected' => false,
                'corrections'   => []
            ];

            if ($quantity == 0 || empty($quantity) || !is_numeric($quantity)) {
                // Quantity is either 0, empty or not a number. We need to validate and fix
                $quantityValid = false;

                // We attempt to correct
                if (!empty($unitPrice) && !empty($totalPrice) && $unitPrice > 0) {
                    $quantity = $totalPrice / $unitPrice;
                    PathResolver::setValueByPath($d, $quantityPath, $quantity);
                    $d['quantity_validation']['is_corrected'] = true;

                    $d['quantity_validation']['corrections'] = [
                        'quantity'      => $quantity,
                        'unit_price'    => null,
                        'total_price'   =>null
                    ];

                }

            } else {
                // quantity is a valid number. We validate against unit price and total price, if given
                if (!empty($unitPrice) && !empty($totalPrice)) {

                    $quantityValid = $quantity * $unitPrice == $totalPrice;

                    if (!$quantityValid) {

                        if ($unitPrice > $totalPrice) {
                            // We know unit price is wrong. So we correct it
                            $corrections = [$quantity, ($totalPrice/$quantity), $totalPrice];
                        } else {
                            // We need to know which of the three has an error.
                            $corrections = self::checkCorrections($quantity, $unitPrice, $totalPrice);
                        }

                        if (!isset($corrections['error'])) {

                            PathResolver::setValueByPath($d, $quantityPath, $corrections[0]);
                            PathResolver::setValueByPath($d, $unitPricePath, $corrections[1]);
                            PathResolver::setValueByPath($d, $totalPricePath, $corrections[2]);

                            $d['quantity_validation']['is_corrected'] = true;

                            $d['quantity_validation']['corrections'] = [
                                'quantity'      => $corrections[0],
                                'unit_price'    => $corrections[1],
                                'total_price'   => $corrections[2]
                            ];
                        }

                    }

                }
            }
            // We set the validity status
            $d['quantity_validation']['is_valid'] = $quantityValid;

        }
        return $data;
    }

    private static function isCorrect($quantity, $unitPrice, $totalPrice): bool
    {
        return $totalPrice == $unitPrice * $quantity && $unitPrice <= $totalPrice;
    }

    private static function checkCorrections($quantity, $unitPrice, $totalPrice): array
    {
        // Check if the initial values are correct
        if (self::isCorrect($quantity, $unitPrice, $totalPrice)) {
            return array($quantity, $unitPrice, $totalPrice);
        }

        $values = array('quantity' => $quantity, 'unitPrice' => $unitPrice, 'totalPrice' => $totalPrice);
        $digits = str_split('0123456789');

        foreach ($values as $key => $value) {
            $strVal = strval($value);

            // Try removing each digit
            for ($i = 0; $i < strlen($strVal); $i++) {
                $newVal = intval(substr_replace($strVal, '', $i, 1));
                $newValues = array_merge($values, array($key => $newVal));
                if (self::isCorrect($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice'])) {
                    return array($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice']);
                }
            }

            // Try changing each digit to each possible digit
            for ($i = 0; $i < strlen($strVal); $i++) {
                foreach ($digits as $digit) {
                    if ($digit != $strVal[$i]) {
                        $newVal = intval(substr_replace($strVal, $digit, $i, 1));
                        $newValues = array_merge($values, array($key => $newVal));
                        if (self::isCorrect($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice'])) {
                            return array($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice']);
                        }
                    }
                }
            }

            // Try adding each digit at each position
            for ($i = 0; $i <= strlen($strVal); $i++) {
                foreach ($digits as $digit) {
                    $newVal = intval(substr($strVal, 0, $i) . $digit . substr($strVal, $i));
                    $newValues = array_merge($values, array($key => $newVal));
                    if (self::isCorrect($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice'])) {
                        return array($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice']);
                    }
                }
            }
        }

        // If no correction is found, return the original values with a flag indicating an error
        return array('error' => 'No valid correction found', 'quantity' => $quantity, 'unitPrice' => $unitPrice, 'totalPrice' => $totalPrice);
    }


}
