<?php

namespace PhelixJuma\GUIFlow\Utils;

use InvalidArgumentException;

class DataReducer
{
    private $data;
    private $reducer;
    private $reducerArgs;

    protected PathResolver $pathResolver;

    public function __construct($data, $reducer, ...$reducerArgs)
    {
        $this->data = $data;
        $this->reducer = $reducer;
        $this->reducerArgs = $reducerArgs;

        $this->pathResolver = new PathResolver();
    }

    /**
     * @return mixed
     */
    public function reduce(): mixed
    {
        if (empty($this->data)) {
            return $this->data;
        }
        return call_user_func([$this, $this->reducer], ...$this->reducerArgs);
    }

    private function min() {
        return min($this->data);
    }

    private function max() {
        return max($this->data);
    }

    private function get_item_at_index($index) {
        if (!is_array($this->data) || !is_numeric($index)) {
            return $this->data;
        }
        return $this->data[intval($index)] ?? $this->data;
    }

    /**
     * @param $priority
     * @param $default
     * @return float|mixed|string
     */
    private function modal_value($priority = [], $default = "") {

        // get array values
        $data = array_values($this->data);

        // We get the values from the data and their count
        $values = (array_count_values($data));
        // We sort the values in desc order
        arsort($values);

        // We get the first value
        $firstVal = $values;
        $firstVal = array_values(array_splice($firstVal, 0, 1))[0];

        // We get all the items in the array that have the same value as the first (modal values can be multiple)
        $values = array_filter($values, function($v) use($firstVal) {
            return $v == $firstVal;
        });

        if (sizeof($values) ==1 ) {
            // Only one modal value, we return it
            return array_values(array_flip($values))[0];
        }

        // if priority is set, we use it for next ranking.
        if (!empty($priority)) {

            // Get the values with their priority
            $priorityList = [];
            foreach($values as $key => $val) {
                $priorityList[$key] = $priority[$key] ?? INF;
            }
            // We sort from the first priority
            asort($priorityList);

            // We return the first
            return array_values(array_flip($priorityList))[0];
        }

        // If default is set, we return it
        return $default;
    }

    /**
     * @param $priorityList
     * @param $default
     * @return int|mixed|string
     */
    private function priority_reducer($priorityList, $default = "") {

        // Flip the priority list to map priorities to kinds
        $flippedPriorityList = array_flip($priorityList);

        // Sort the flipped list so lower priorities come first
        ksort($flippedPriorityList);

        foreach ($flippedPriorityList as $kind) {
            if (in_array($kind, $this->data)) {
                return $kind;  // Return the first (highest priority) kind found
            }
        }
        return $default;  // or some default value, if necessary
    }

    /**
     * @param string $key
     * @param array $groupingPhrases
     * @param array $excludePhrases
     * @return array
     */
    private function match_and_exclude_reducer(string $key, array $groupingPhrases, array $excludePhrases): array {

        $groupedItems = [];
        $reducedList = [];

        // Step 1: Group items by their base text (excluding grouping phrases)
        foreach ($this->data as $item) {
            if (!isset($item[$key])) {
                throw new InvalidArgumentException("Key '{$key}' not found in one of the items.");
            }

            $baseText = $item[$key];
            foreach ($groupingPhrases as $phrase) {
                $baseText = preg_replace("/\b$phrase\b/i", '', $baseText);
            }
            $baseText = trim(preg_replace('/\s+/', ' ', $baseText)); // Normalize spaces
            $groupedItems[$baseText][] = $item;
        }

        // Step 2: Reduce the groups
        foreach ($groupedItems as $baseText => $items) {
            if (count($items) > 1) {
                // Check if there's a match to exclude based on multiple exclusion phrases
                $keepItems = array_filter($items, function ($item) use ($excludePhrases, $key) {
                    foreach ($excludePhrases as $excludePhrase) {
                        if (preg_match("/\b$excludePhrase\b/i", $item[$key])) {
                            return false; // Exclude this item
                        }
                    }
                    return true; // Keep this item
                });

                // If keepItems exist, include only them; otherwise include all
                $reducedList = array_merge($reducedList, $keepItems ?: $items);
            } else {
                // If there's only one item, keep it
                $reducedList[] = $items[0];
            }
        }

        return $reducedList;
    }


}
