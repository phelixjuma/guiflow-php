<?php

namespace PhelixJuma\DataTransformer\Utils;

use FuzzyWuzzy\Fuzz;

class Filter
{

    // operators
    const EQUAL = 'equal';
    const NOT_EQUAL = 'not equal';
    const GREATER = 'greater';
    const GREATER_OR_EQUAL = 'greater or equal';
    const LESS = 'less';
    const LESS_OR_EQUAL = 'less or equal';
    const IN = 'in';
    const NOT_IN = 'not in';
    const REGEX = 'regex';
    const TRUE = 'true';
    const FALSE = 'false';
    const EMPTY = 'empty';
    const NOT_EMPTY = 'not empty';
    const STARTS_WITH = 'startswith';
    const ENDS_WITH = 'endswith';
    const SIMILAR_TO = 'similar_to';
    const CONTAINS  = 'contains';

    const DEFAULT_THRESHOLD = 80;

    public function __construct() {
    }

    /**
     * @param $value
     * @param $term
     * @param $mode
     * @param int $similarityThreshold
     * @return bool|int
     */
    private static function matchValueAgainstFilter($value, $term, $mode, int $similarityThreshold=self::DEFAULT_THRESHOLD): bool|int
    {

        $term = is_array($term) ? array_map('strtolower', $term) : strtolower($term);
        $value = strtolower($value);
        $fuzz = new Fuzz();

        return match ($mode) {
            self::EQUAL => $term == $value,
            self::NOT_EQUAL => $term != $value,
            self::GREATER => is_numeric($value) && $value > $term,
            self::GREATER_OR_EQUAL => is_numeric($value) && $value >= $term,
            self::LESS => is_numeric($value) && $value < $term,
            self::LESS_OR_EQUAL => is_numeric($value) && $value <= $term,
            self::IN => in_array($value, $term),
            self::NOT_IN => !in_array($value, $term),
            self::REGEX => preg_match($term, $value),
            self::TRUE => $value == 1,
            self::FALSE => $value == 0,
            self::EMPTY => empty($value),
            self::NOT_EMPTY => !empty($value),
            self::STARTS_WITH => str_starts_with($value, $term),
            self::ENDS_WITH => str_ends_with($value, $term),
            self::SIMILAR_TO => $fuzz->ratio($value, $term) >= $similarityThreshold,
            default => str_contains($value, $term),
        };
    }

    /**
     * @param $value
     * @param $filters
     * @return bool|int
     */
    private static function matchesCriteria($value, $filters): bool|int
    {

        // simple condition
        if (!isset($filters['operator'])) {

            // For an associative array, we get value based on key
            if (is_array($value) && array_keys($value) !== range(0, count($value) - 1)) {
                $value = !empty($filters['key']) ? $value[$filters['key']] : "";
            }

            return self::matchValueAgainstFilter($value,
                $filters['term'],
                $filters['mode'] ?? self::CONTAINS,
                $filters['threshold'] ?? self::DEFAULT_THRESHOLD);
        }

        // composite conditions
        if (strtolower($filters['operator']) == 'and') {

            foreach ($filters['conditions'] as $filter) {

                if (!self::matchesCriteria($value, $filter)) {
                    return false;
                }
            }
            return true;
        }
        elseif (strtolower($filters['operator']) == 'or') {
            foreach ($filters['conditions'] as $filter) {
                if (self::matchesCriteria($value, $filter)) {
                    return true;
                }
            }
            return false;
        } else {
            throw new \InvalidArgumentException('Invalid composite condition');
        }
    }

    /**
     * @param array $array
     * @param $filters
     * @return array
     */
    public static function filterArray(array $array, $filters): array
    {

        foreach ($array as $key => $value) {

            if (is_array($value)) {
                // Check if it's an associative array (object)
                if (array_keys($value) !== range(0, count($value) - 1)) {

                    if (!self::matchesCriteria($value, $filters)) {
                        unset($array[$key]);
                    }

                } else {

                    $array[$key] = self::filterArray($value, $filters);
                    if (empty($array[$key])) {
                        unset($array[$key]);
                    }
                }
            } else {
                if (!self::matchesCriteria($value, $filters)) {
                    unset($array[$key]);
                }
            }
        }
        return array_values($array);  // Resetting the keys
    }
}
