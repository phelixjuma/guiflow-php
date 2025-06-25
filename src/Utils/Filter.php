<?php

namespace PhelixJuma\GUIFlow\Utils;

use FuzzyWuzzy\Fuzz;
use Kuza\Krypton\Framework\Services\DocuFlow\DataTransformerService;
use PhelixJuma\GUIFlow\Conditions\SimpleCondition;
use PhelixJuma\GUIFlow\Workflow;

class Filter
{

    // operators
    const EQUAL = '==';
    const NOT_EQUAL = '!=';
    const GREATER = '>';
    const GREATER_OR_EQUAL = '>=';
    const LESS = '<';
    const LESS_OR_EQUAL = '<=';
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
     * @param $data
     * @param $pattern
     * @return void
     */
    private static function excludePatternFromData(&$data, $pattern) {

        if (!is_null($data)) {
            if (!is_array($data)) {
                $data = preg_replace("/$pattern/i", '', $data);
    
                // Trim and remove multiple spaces to clean up the result
                $data = preg_replace('/\s+/', ' ', trim($data));
    
            } else {
                array_walk($data, function (&$v, $k) use($pattern) {
                    self::excludePatternFromData($v, $pattern);
                }) ;
            }
        }
    }

    /**
     * @param $value
     * @param $term
     * @param $mode
     * @param $similarityThreshold
     * @param $termExclusionPattern
     * @param $valueExclusionPattern
     * @return bool|int
     * @throws \Exception
     */
    private static function matchValueAgainstFilter($value, $term, $mode, $similarityThreshold=self::DEFAULT_THRESHOLD, $termExclusionPattern = null, $valueExclusionPattern=null): bool|int
    {
        
        $term = is_array($term) ? array_map(function($item) {
            return is_string($item) ? strtolower(trim($item)) : $item;
        }, $term) : strtolower(trim($term));

        $value = is_string($value) ? strtolower(trim($value)) : $value;

        // if exclusion pattern is set, we clean the term and value using the pattern.
        if (!empty($termExclusionPattern)) {
            self::excludePatternFromData($term, $termExclusionPattern);
        }
        if (!empty($valueExclusionPattern)) {
            self::excludePatternFromData($value, $valueExclusionPattern);
        }

        $fuzz = new Fuzz();


        return match ($mode) {
            self::EQUAL => $term == $value,
            self::NOT_EQUAL => $term != $value,
            self::GREATER => $value > $term,
            self::GREATER_OR_EQUAL => $value >= $term,
            self::LESS => $value < $term,
            self::LESS_OR_EQUAL => $value <= $term,
            self::IN => is_array($term) && in_array($value, $term),
            self::NOT_IN => !is_array($term) || !in_array($value, $term),
            self::REGEX => preg_match($term, $value),
            self::TRUE => $value == true,
            self::FALSE => $value == false,
            self::EMPTY => empty($value),
            self::NOT_EMPTY => !empty($value),
            self::STARTS_WITH => str_starts_with($value, $term),
            self::ENDS_WITH => str_ends_with($value, $term),
            self::SIMILAR_TO => $fuzz->tokenSetRatio($value, $term) >= $similarityThreshold,
            default => SimpleCondition::compare($value, $mode, $term, $similarityThreshold)
        };
    }

    /**
     * @param $value
     * @param $filters
     * @return bool|int
     */
    private static function matchesCriteria($data, $filters): bool|int
    {

        
        // simple condition
        if (!isset($filters['operator'])) {

            $value = PathResolver::getValueByPath($data, $filters['key']);

            if (isset($filters['term']['in_item_path'])) {
                $filters['term'] = PathResolver::getValueByPath($data, $filters['term']['in_item_path']);
            }

            return self::matchValueAgainstFilter($value,
                $filters['term'],
                $filters['mode'] ?? self::CONTAINS,
                $filters['threshold'] ?? self::DEFAULT_THRESHOLD,
            $filters['term_exclusion_pattern'] ?? null,
            $filters['value_exclusion_pattern'] ?? null);
        }

        // composite conditions
        if (strtolower($filters['operator']) == 'and') {

            foreach ($filters['conditions'] as $filter) {

                if (!self::matchesCriteria($data, $filter)) {
                    return false;
                }
            }
            return true;
        }
        elseif (strtolower($filters['operator']) == 'or') {
            foreach ($filters['conditions'] as $filter) {
                if (self::matchesCriteria($data, $filter)) {
                    return true;
                }
            }
            return false;
        } else {
            throw new \InvalidArgumentException('Invalid composite condition');
        }
    }

    /**
     * @param $array
     * @param $filters
     * @return array|mixed
     */
    public static function filterArray($array, $filters)
    {


        if (empty($array)) {
            return $array;
        }

        foreach ($array as $key => $datum) {

            if (is_array($datum)) {

                // Check if it's an associative array (object)
                if (array_keys($datum) !== range(0, count($datum) - 1)) {

                    if (!self::matchesCriteria($datum, $filters)) {
                        unset($array[$key]);
                    }

                } else {

                    $array[$key] = self::filterArray($datum, $filters);
                    if (empty($array[$key])) {
                        unset($array[$key]);
                    }
                }
            } else {
                if (!self::matchesCriteria($datum, $filters)) {
                    unset($array[$key]);
                }
            }
        }
        return array_values($array);  // Resetting the keys
    }

    /**
     * Performs conditional filtering on grouped data using a window-based approach.
     * 
     * This method first groups the input data by a specified grouping key, then applies
     * conditional filtering within each group (window). The filtering behavior depends on
     * whether a window condition is met:
     * 
     * - If the window condition is NOT met for a group: the entire group is kept unchanged
     * - If the window condition IS met for a group: individual items are filtered using the provided filters
     * - If filtering results in an empty group and empty windows are not allowed: the original group is preserved
     * 
     * This is particularly useful for scenarios where you want to apply different filtering
     * logic based on the characteristics of the entire group/window of data.
     * 
     * Example use case: Filter transactions within account groups, but only apply strict
     * filtering rules to accounts that meet certain criteria (e.g., high-value accounts).
     * 
     * @param array $data The input data array to be filtered. Each item should be an associative array.
     * @param string $grouping_key The key/field name used to group the data items. Items with the same
     *                            value for this key will be grouped together.
     * @param array $window_condition The condition array used to evaluate each group/window. This follows
     *                               the same format as Workflow::evaluateCondition() conditions.
     * @param array $filters The filter criteria applied to individual items within groups where the
     *                      window condition is met. This follows the same format as other filtering
     *                      methods in this class.
     * @param string $allow_empty_window Whether to allow empty groups after filtering. "0" (default) means
     *                                  empty groups are not allowed and the original group will be preserved.
     *                                  "1" means empty groups are allowed.
     * 
     * @return array The filtered data array with items that meet the criteria. Array keys are reset
     *               to maintain sequential indexing.
     * 
     * @throws \Exception May throw exceptions from Workflow::evaluateCondition() if condition evaluation fails.
     * 
     * @example
     * ```php
     * $data = [
     *     ['account_id' => 'A1', 'amount' => 100, 'type' => 'deposit'],
     *     ['account_id' => 'A1', 'amount' => 50, 'type' => 'withdrawal'],
     *     ['account_id' => 'A2', 'amount' => 1000, 'type' => 'deposit'],
     *     ['account_id' => 'A2', 'amount' => 500, 'type' => 'withdrawal'],
     * ];
     * 
     * // Only filter high-value accounts (groups with total > 800)
     * $window_condition = ['key' => 'amount', 'mode' => '>', 'term' => 800];
     * $filters = ['key' => 'type', 'mode' => '==', 'term' => 'deposit'];
     * 
     * $result = Filter::window_conditional_filter($data, 'account_id', $window_condition, $filters);
     * ```
     */
    public static function window_conditional_filter($data, $grouping_key, $window_condition, $filters, $allow_empty_window="0") {

        // 1. we group the data based on the grouping key
        $groups = [];
        foreach ($data as $datum) {
            $key = $datum[$grouping_key] ?? null;
            if ($key !== null) {
                $groups[$key][] = $datum;
            }
        }

        // 2. We filter the data within its own window
        $filtered = [];
        foreach ($groups as $group) {
            // The callback decides which items to keep based on the whole window/group.
            if (!Workflow::evaluateCondition($group, $window_condition)) {
                $filtered = array_merge($filtered, $group);
            } else {
                // we filter the group
                $filteredGroup = [];
                foreach ($group as $item) {
                    if (Workflow::evaluateCondition($item, $filters)) {
                        $filteredGroup[] = $item;
                    }
                }
                // if group is filtered to emptiness but empty group is not allowed, we return everything
                if (empty($filteredGroup) && $allow_empty_window == "0") {
                    $filteredGroup = $group;
                }
                // Merge the filtered group back into the final result.
                $filtered = array_merge($filtered, $filteredGroup);
            }
        }
        return $filtered;

    }

}
