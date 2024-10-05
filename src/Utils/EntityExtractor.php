<?php

namespace PhelixJuma\GUIFlow\Utils;

class EntityExtractor
{

    public static function extractEntities($data, $prefix = '') {
        $entities = [];

        foreach ($data as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                if (array_values($value) === $value) { // Indexed array (list in Python)
                    foreach ($value as $index => $item) {
                        $entities = array_merge($entities, self::extractEntities($item, $newKey . '.' . $index));
                    }
                } else { // Associative array (dict in Python)
                    $entities = array_merge($entities, self::extractEntities($value, $newKey));
                }
            } else {
                $entities[] = [
                    'entity_name'               => $newKey,
                    'entity_name_standardized'  => preg_replace("/\.(\d+)\./i",".*.", $newKey),
                    'entity_description'        => self::generateEntityDescription($newKey),
                    'entity_value'              => $value

                ];
            }
        }

        return $entities;
    }

    private static function generateEntityDescription($entityName) {
        $parts = explode('.', $entityName);
        $description = [];

        foreach ($parts as $index => $part) {
            if (is_numeric($part)) {
                // Place the ordinal before the previous description part
                $lastIndex = count($description) - 1;
                if ($lastIndex >= 0) {
                    $description[$lastIndex] = self::ordinal(intval($part) + 1) . ' ' . $description[$lastIndex];
                } else {
                    $description[] = self::ordinal(intval($part) + 1);
                }
            } else {
                $description[] = ucwords(str_replace('_', ' ', $part));
            }
        }

        return implode(' ', $description);
    }

    private static function ordinal($number) {
        $suffixes = ['th', 'st', 'nd', 'rd', 'th', 'th', 'th', 'th', 'th', 'th'];
        $value = $number % 100;

        if ($value >= 11 && $value <= 13) {
            return $number . 'th';
        }

        return $number . $suffixes[$number % 10];
    }
}
