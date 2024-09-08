<?php

namespace PhelixJuma\GUIFlow\Utils;

class DataSplitter
{

    /**
     * @param $data
     * @param $method
     * @param $splitPath
     * @param $criteriaPath
     * @param $criteria
     * @param $running_total_limit
     * @return array|array[]
     */
    public static function split($data, $method, $splitPath, $criteriaPath, $criteria = null, $running_total_limit=null) {

        return match ($method) {
            'running_total' => self::splitByRunningTotal($data, $splitPath, $criteriaPath, $running_total_limit),
            default => self::splitByPath($data, $splitPath, $criteriaPath)
        };
    }

    /**
     * Split the data array based on a given path and criteria path.
     *
     * @param $data
     * @param $splitPath
     * @param $criteriaPath
     */
    private static function splitByPath($data, $splitPath, $criteriaPath) {

        // Fetch all the unique criteria values for splitting
        $valuesForPath = PathResolver::getValueByPath($data, $criteriaPath, true);
        $uniqueValues = array_unique($valuesForPath);

        if (sizeof($uniqueValues) == 1) {
            return $data;
        }

        $results = [];

        foreach ($uniqueValues as $value) {

            $dataCopy = $data;

            // Filtering based on the criteria
            $items = PathResolver::getValueByPath($data, $splitPath);
            $filteredItems = null;

            if (!empty($items) && is_array($items)) {

                foreach ($items as $item) {

                    // We get the new path by removing the parent array path
                    $newPath = str_replace("{$splitPath}.*.","","$criteriaPath");

                    $filterData = PathResolver::getValueByPath($item, $newPath);

                    if ($filterData == $value) {
                        $filteredItems[] = $item;
                    }
                }
            }

            PathResolver::setValueByPath($dataCopy, $splitPath, $filteredItems);

            $results[] = $dataCopy;
        }

        return $results;
    }

    /**
     * @param $data
     * @param $splitPath
     * @param $criteriaPath
     * @param $limit
     * @return array
     */
    private static function splitByRunningTotal($data, $splitPath, $criteriaPath, $limit) {

        // Get the items to split
        $items = PathResolver::getValueByPath($data, $splitPath);

        if (empty($items) || !is_array($items)) {
            return [$data]; // Return the original data if there are no items to split
        }

        // Step 1: Replicate items that exceed the limit
        $newItems = [];
        foreach ($items as $item) {
            $quantity = PathResolver::getValueByPath($item, $criteriaPath);

            if ($quantity > $limit) {
                while ($quantity > $limit) {
                    $splitItem = $item;
                    PathResolver::setValueByPath($splitItem, $criteriaPath, $limit);
                    $newItems[] = $splitItem;
                    $quantity -= $limit;
                }

                if ($quantity > 0) {
                    $remainingItem = $item;
                    PathResolver::setValueByPath($remainingItem, $criteriaPath, $quantity);
                    $newItems[] = $remainingItem;
                }
            } else {
                $newItems[] = $item;
            }
        }

        // We add rank key for later sorting
        foreach ($newItems as $key => &$item) {
            $item['rank'] = $key;
        }

        // Step 2: Sort items in descending order by criteria path value
        usort($newItems, function($a, $b) use ($criteriaPath) {
            return PathResolver::getValueByPath($b, $criteriaPath) <=> PathResolver::getValueByPath($a, $criteriaPath);
        });

        // Step 3: Group items
        $results = [];
        $usedItems = [];
        $itemsCount = count($newItems);

        while (count($usedItems) < $itemsCount) {
            $currentGroup = [];
            $currentTotal = 0;

            foreach ($newItems as $index => $item) {
                if (in_array($index, $usedItems)) {
                    continue; // Skip already used items
                }

                $splitValue = PathResolver::getValueByPath($item, $criteriaPath);

                if ($currentTotal + $splitValue <= $limit) {
                    $currentGroup[] = $item;
                    $currentTotal += $splitValue;
                    $usedItems[] = $index;
                }
            }

            // Save the current group if it's not empty
            if (!empty($currentGroup)) {
                $dataCopy = $data;

                // first, we sort the current group
                usort($currentGroup, function($a, $b) {
                    return $a['rank'] <=> $b['rank'];
                });

                // Remove the rank key
                foreach ($currentGroup as $key => &$item) {
                    unset($item['rank']);
                }

                PathResolver::setValueByPath($dataCopy, $splitPath, $currentGroup);
                $results[] = $dataCopy;
            }
        }

        return $results;
    }


}
