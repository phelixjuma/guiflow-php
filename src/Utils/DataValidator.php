<?php

namespace PhelixJuma\GUIFlow\Utils;

use PhelixJuma\GUIFlow\Workflow;

class DataValidator
{

    const VALIDATION_RULE_PATH_EXISTS = 'path exists';
    const VALIDATION_RULE_IS_NOT_EMPTY = 'is not empty';
    const VALIDATION_RULE_IS_NUMERIC = 'is numeric';
    const VALIDATION_RULE_IS_NON_ZERO_NUMBER = 'is non zero number';
    const VALIDATION_RULE_IS_DATE = 'is date';
    const VALIDATION_RULE_IS_EMAIL = 'is email';
    const VALIDATION_RULE_IS_LIST = 'is list';
    const VALIDATION_RULE_IS_DICTIONARY = 'is dictionary';
    const VALIDATION_RULE_IS_UPC_BAR_CODE = 'is upc barcode';
    const VALIDATION_RULE_IS_EAN_13_BAR_CODE = 'is ean13 barcode';
    const VALIDATION_RULE_IS_ISBN = 'is isbn10';

    private static function isCorrect($quantity, $unitPrice, $totalPrice) {

        if ($totalPrice < $unitPrice) {
            return false;
        }

        $totalPriceCheck = round($totalPrice,2) == round($unitPrice * $quantity,2);
        $unitPriceCheck = round($unitPrice,2) == round($totalPrice/$quantity,2);
        $quantityCheck = round($quantity,2) == round($totalPrice/$unitPrice,2);

        return $totalPriceCheck || $unitPriceCheck || $quantityCheck;

    }

    private static function correctCommonMistakes($value) {
        // Mapping common OCR misreadings and potential decimal issues
        $mappings = [
            '0' => ['8', '6'],   // 0 might be misread as 8 or 6
            '1' => ['7'],        // 1 might be misread as 7
            '3' => ['8', '7'],        // 3 might be misread as 8, 7
            '6' => ['8', '0'],   // 6 might be misread as 8 or 0
            '7' => ['1', '3'], // 8 might be misread as 0, 3, or 6
            '8' => ['0', '3', '6'], // 8 might be misread as 0, 3, or 6
        ];

        $possibleValues = [$value];
        $strVal = strval($value);

        // Handle 1 at the end of a number
        if ($strVal != '1' && substr($strVal, -1) === '1') {
            $coreValue = intval(substr($strVal, 0, -1));
            $possibleValues[] = $coreValue; // Example: 300 to 3.00
        }

        // Handling potential decimal misinterpretation
        if (substr($strVal, -2) === '00') {

            $coreValue = intval(substr($strVal, 0, -2));
            $possibleValues[] = $coreValue; // Example: 11 to 1

            // We get more possible values for the core value
            $possibleValues = array_merge($possibleValues,self::getPossibleValues($coreValue));

            // we add handling of a stray 1 after the removal
            $coreValueStr = (string)$coreValue;
            if ($coreValueStr != '1' && substr($coreValueStr, -1) === '1') {
                $coreValue = intval(substr($coreValueStr, 0, -1));
                $possibleValues[] = $coreValue; // Example: 1100 to 1
            }

        }

        // Apply common OCR misreadings and decimal errors
        for ($i = 0; $i < strlen($strVal); $i++) {
            $digit = $strVal[$i];
            if (array_key_exists($digit, $mappings)) {
                foreach ($mappings[$digit] as $replacement) {
                    $newStrVal = substr_replace($strVal, $replacement, $i, 1);
                    $possibleValues[] = intval($newStrVal);

                    // We get more possible values for the core value
                    $possibleValues = array_merge($possibleValues,self::getPossibleValues($newStrVal));

                    // Additionally check for decimal placement errors with the new digit
                    if (substr($newStrVal, -3) === '000') {
                        $coreValue = intval(substr($newStrVal, 0, -3));
                        $possibleValues[] = $coreValue;

                        // We get more possible values for the core value
                        $possibleValues = array_merge($possibleValues,self::getPossibleValues($coreValue));

                    }
                    if (substr($newStrVal, -2) === '00') {
                        $coreValue = intval(substr($newStrVal, 0, -2));
                        $possibleValues[] = $coreValue;

                        // We get more possible values for the core value
                        $possibleValues = array_merge($possibleValues,self::getPossibleValues($coreValue));

                    }
                    if (substr($newStrVal, -1) === '0') {
                        $coreValue = intval(substr($newStrVal, 0, -1));
                        $possibleValues[] = $coreValue;

                        // We get more possible values for the core value
                        $possibleValues = array_merge($possibleValues,self::getPossibleValues($coreValue));

                    }
                }
            }
        }
        return $possibleValues;
    }

    private static function removeTrailingZeros($strVal, &$possibleValues = []) {
        $possibleValues[] = intval($strVal);

        // Remove trailing zeros and add possible values recursively
        if (substr($strVal, -1) === '0') {
            $strVal = substr($strVal, 0, -1);
            self::removeTrailingZeros($strVal, $possibleValues);
        }

        // Check for stray '1' at the end after zeros are removed
        if (substr($strVal, -1) === '1' && $strVal !== '1') {
            $strVal = substr($strVal, 0, -1);
            $possibleValues[] = intval($strVal);
        }

        return $possibleValues;
    }

    /**
     * @param $value
     * @return array
     */
    private static function getPossibleValues($value) {

        /**
         * Part 2: Check for digit variations
         */
        $strVal = strval($value);
        $digits = str_split('0123456789');

        $possibleValues = [];

        // Handle 1 at the end of a number
        if ($strVal != '1' && substr($strVal, -1) === '1') {
            $coreValue = intval(substr($strVal, 0, -1));
            $possibleValues[] = $coreValue; // Example: 31 to 3
        }

        // Handling trailing zeros
        $possibleValues = self::removeTrailingZeros($strVal);

        // Try removing each digit
        for ($i = 0; $i < strlen($strVal); $i++) {
            $val = intval(substr_replace($strVal, '', $i, 1));
            if ($val > 0) {
                $possibleValues[] = $val;
            }
        }

        // Try changing each digit to each possible digit
        for ($i = 0; $i < strlen($strVal); $i++) {
            foreach ($digits as $digit) {
                if ($digit != $strVal[$i]) {
                    $val = intval(substr_replace($strVal, $digit, $i, 1));
                    if ($val > 0) {
                        $possibleValues[] = $val;
                    }
                }
            }
        }

        // Try adding each digit at each position
        for ($i = 0; $i <= strlen($strVal); $i++) {
            foreach ($digits as $digit) {
                $val = intval(substr($strVal, 0, $i) . $digit . substr($strVal, $i));
                if ($val > 0) {
                    $possibleValues[] = $val;
                }
            }
        }

        return $possibleValues;
    }

    private static function checkCorrections($quantity, $unitPrice, $totalPrice) {

        if (self::isCorrect($quantity, $unitPrice, $totalPrice)) {
            return array($quantity, $unitPrice, $totalPrice);
        }

        $values = array('quantity' => $quantity, 'unitPrice' => $unitPrice, 'totalPrice' => $totalPrice);

        foreach ($values as $key => $value) {

            if (!str_contains(strval($value), '.')) {
                /**
                 * Part 1: Correct common mistakes
                 */
                $correctedValues = self::correctCommonMistakes($value);

                foreach ($correctedValues as $correctedValue) {
                    $newValues = array_merge($values, array($key => $correctedValue));
                    if (self::isCorrect($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice'])) {
                        return self::formatResponse(array($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice']));
                    }
                }

                /**
                 * Part 2: Check for digit variations
                 */

                $possibleValues = self::getPossibleValues($value);

                foreach ($possibleValues as $possibleValue) {
                    $newValues = array_merge($values, array($key => $possibleValue));
                    if (self::isCorrect($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice'])) {
                        return self::formatResponse(array($newValues['quantity'], $newValues['unitPrice'], $newValues['totalPrice']));
                    }
                }
            }
        }

        return array('error' => 'No valid correction found', 'quantity' => $quantity, 'unitPrice' => $unitPrice, 'totalPrice' => $totalPrice);
    }

    /**
     * @param $response
     * @return mixed
     */
    private static function formatResponse($response) {
        array_walk($response, function (&$v, $k) {
            $v = round($v, 1);
        });
        return $response;
    }

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

            if ($quantity == 0 || empty($quantity) || !is_numeric($quantity)) {
                // Quantity is either 0, empty or not a number. We need to validate and fix

                // We attempt to correct
                if (!empty($unitPrice) && !empty($totalPrice) && $unitPrice > 0) {
                    $quantity = round($totalPrice / $unitPrice, 1);
                    PathResolver::setValueByPath($d, $quantityPath, $quantity);
                }

            } else {
                // quantity is a valid number. We validate against unit price and total price, if given
                if (!empty($unitPrice) && !empty($totalPrice)) {

                    $quantityValid = $quantity * $unitPrice == $totalPrice;

                    if (!$quantityValid) {

                        if ($unitPrice > $totalPrice) {
                            // We know unit price is wrong. So we correct it
                            $corrections = [$quantity, round($totalPrice/$quantity, 1), $totalPrice];
                        } elseif ($unitPrice == $totalPrice) {
                            // We know quantity is wrong since it's supposed to be 1
                            $corrections = [1, $unitPrice, $totalPrice];
                        } else {
                            // We need to know which of the three has an error.
                            $corrections = self::checkCorrections($quantity, $unitPrice, $totalPrice);
                        }

                        if (!isset($corrections['error'])) {

                            PathResolver::setValueByPath($d, $quantityPath, $corrections[0]);
                            PathResolver::setValueByPath($d, $unitPricePath, $corrections[1]);
                            PathResolver::setValueByPath($d, $totalPricePath, $corrections[2]);
                        }

                    }

                }
            }
        }
        return $data;
    }

    private static function isValidDate($date) {

        $timestamp = strtotime($date);
        if ($timestamp === false) {
            return false;
        }

        $dateObj = date_create($date);
        if ($dateObj === false) {
            return false;
        }

        // Check if the date is valid by ensuring the formatted output matches the input
        $formattedDate = $dateObj->format('Y-m-d H:i:s');
        return strtotime($formattedDate) === $timestamp;
    }

    public static function validateDataStructure($data, $validations, $verbose = true): bool|array
    {
        $validationResponse = [];

        foreach ($validations as $validation) {

            $path = !empty($validation['path']) ? $validation['path'] : (!empty($validation['data_path']) ? $validation['data_path'] : "");
            $rules = $validation['rules'];

            $pathData = PathResolver::getValueByPath($data, $path);
            $validationResponse[$path]['value'] = $pathData;
            $validationResponse[$path]['description'] = $validation['description'] ?? "";

            $validationStatus = [];

            foreach ($rules as $rule) {

                $key = is_string($rule) ? $rule : json_encode($rule);

                if ($rule == self::VALIDATION_RULE_IS_LIST || $rule == self::VALIDATION_RULE_IS_DICTIONARY) {
                    $validationStatus[$key] = ['rule' => $rule, 'status' => self::validateComplexStructure($pathData, $rule)];
                } else {
                    $validationStatus[$key] = ['rule' => $rule, 'status' => self::applyRule($pathData, $rule)];
                }
            }

            $validationResponse[$path]['validations'] = array_values($validationStatus);
        }

        if ($verbose) {
            return $validationResponse;
        }

        foreach ($validationResponse as $response) {
            foreach ($response['validations'] as $validation) {
                if (!$validation['status']) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function applyRule($data, $rule): bool
    {
        if (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item)) {
                    if (!self::applyRule($item, $rule)) {
                        return false;
                    }
                } else {
                    if (!self::validateValue($item, $rule)) {
                        return false;
                    }
                }
            }
            return true;
        }
        return self::validateValue($data, $rule);
    }

    private static function validateValue($value, $rule): bool
    {
        switch ($rule) {
            case self::VALIDATION_RULE_PATH_EXISTS:
                return !is_null($value);

            case self::VALIDATION_RULE_IS_NOT_EMPTY:
                $value = is_string($value) ? trim($value) : $value;
                return !empty($value) || $value === 0;

            case self::VALIDATION_RULE_IS_NUMERIC:
                return is_numeric($value);

            case self::VALIDATION_RULE_IS_NON_ZERO_NUMBER:
                return is_numeric($value) && $value != 0;

            case self::VALIDATION_RULE_IS_EMAIL:
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

            case self::VALIDATION_RULE_IS_DATE:
                return self::isValidDate($value);

            case self::VALIDATION_RULE_IS_LIST:
                return Utils::isList($value);

            case self::VALIDATION_RULE_IS_DICTIONARY:
                return Utils::isObject($value);

            case self::VALIDATION_RULE_IS_UPC_BAR_CODE:
                return self::validateUPCBarCode($value);

            case self::VALIDATION_RULE_IS_EAN_13_BAR_CODE:
                return self::validateEAN13BarCode($value);

            case self::VALIDATION_RULE_IS_ISBN:
                return self::validateISBN10($value);

            default:
                return Workflow::evaluateCondition($value, $rule, true);
        }
    }

    /**
     * @param $data
     * @param $rule
     * @return bool
     */
    private static function validateComplexStructure($data, $rule): bool
    {
        if (!is_array($data)) {
            return false;
        }

        if ($rule == self::VALIDATION_RULE_IS_LIST && !Utils::isList($data)) {
            return false;
        }

        if ($rule == self::VALIDATION_RULE_IS_DICTIONARY && !Utils::isObject($data)) {
            return false;
        }

        return true;
    }

    /**
     * @param $barcode
     * @return bool
     */
    private static function validateUPCBarCode($barcode) {

        if (strlen($barcode) != 12 || !ctype_digit($barcode)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 11; $i++) {
            $digit = intval($barcode[$i]);
            if ($i % 2 == 0) {
                $sum += $digit * 3;
            } else {
                $sum += $digit;
            }
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum == intval($barcode[11]);
    }

    /**
     * @param $barcode
     * @return bool
     */
    private static function validateEAN13BarCode($barcode) {

        if (strlen($barcode) != 13 || !ctype_digit($barcode)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $digit = intval($barcode[$i]);
            if ($i % 2 == 0) {
                $sum += $digit;
            } else {
                $sum += $digit * 3;
            }
        }

        $checksum = (10 - ($sum % 10)) % 10;
        return $checksum == intval($barcode[12]);
    }

    /**
     * @param $isbn
     * @return bool
     */
    private static function validateISBN10($isbn) {

        // Remove any hyphens
        $isbn = str_replace('-', '', $isbn);

        // Check if the length is exactly 10 and the characters are valid
        if (strlen($isbn) != 10 || !preg_match('/^\d{9}[\dX]$/', $isbn)) {
            return false;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += intval($isbn[$i]) * ($i + 1);
        }

        $checksum = $isbn[9];
        if ($checksum == 'X') {
            $checksum = 10;
        }

        $sum += intval($checksum) * 10;

        // The sum should be divisible by 11
        return $sum % 11 == 0;
    }

}
