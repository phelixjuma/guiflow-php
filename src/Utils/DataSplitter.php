<?php

namespace PhelixJuma\GUIFlow\Utils;

class DataSplitter
{

    /**
     * @param $data
     * @param $method
     * @param $splitPath
     * @param $criteriaPath
     * @param $limit
     * @return array|mixed
     */
    public static function split($data, $method, $splitPath, $criteriaPath, $limit=null) {

        return match ($method) {
            'vertical_split'    => self::verticalSplit($data, $splitPath, $criteriaPath, $limit),
            'horizontal_split'  => self::horizontalSplit($data, $splitPath, $criteriaPath, $limit),
            default             => self::splitByPath($data, $splitPath, $criteriaPath)
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

        $index = 0;
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

            // Mark as split data
            $dataCopy['has_been_split'] = 1;
            $dataCopy['workflow_list_position'] = $index;


            $results[] = $dataCopy;

            $index++;
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
    private static function horizontalSplit($data, $splitPath, $criteriaPath, $limit) {

        // Get the items to split
        $items = PathResolver::getValueByPath($data, $splitPath);

        if (empty($items) || !is_array($items)) {
            return [$data]; // Return the original data if there are no items to split
        }

        // Step 1: Replicate items that exceed the limit
        $wasSplit = false;
        $newItems = [];
        foreach ($items as $item) {
            $quantity = PathResolver::getValueByPath($item, $criteriaPath);

            if ($quantity > $limit) {
                $wasSplit = true;
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
        foreach ($newItems as $key => &$newItem) {
            $newItem['rank'] = $key;
        }

        // Step 2: Sort items in descending order by criteria path value
        usort($newItems, function($a, $b) use ($criteriaPath) {
            return PathResolver::getValueByPath($b, $criteriaPath) <=> PathResolver::getValueByPath($a, $criteriaPath);
        });

        // Step 3: Group items
        $results = [];
        $usedItems = [];
        $itemsCount = count($newItems);

        $index = 0;

        while (count($usedItems) < $itemsCount) {
            $currentGroup = [];
            $currentTotal = 0;

            foreach ($newItems as $index => $item) {
                if (in_array($index, $usedItems)) {
                    continue; // Skip already used items
                }

                $splitValue = PathResolver::getValueByPath($item, $criteriaPath);

                if ($currentTotal + $splitValue <= $limit) {
                    $currentTotal += $splitValue;
                    $currentGroup[] = $item;
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
                $groupRunningTotal = 0;
                foreach ($currentGroup as &$groupItem) {
                    unset($groupItem['rank']);
                    $itemSplitValue = PathResolver::getValueByPath($groupItem, $criteriaPath);
                    $groupRunningTotal += $itemSplitValue;
                    $groupItem['running_total'] = $groupRunningTotal;
                }

                PathResolver::setValueByPath($dataCopy, $splitPath, $currentGroup);

                // Mark as split data
                if ($wasSplit) {
                    $dataCopy['has_been_split'] = 1;
                    $dataCopy['workflow_list_position'] = $index;
                }


                $results[] = $dataCopy;

                $index++;
            }
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
    private static function verticalSplit($data, $splitPath, $criteriaPath, $limit) {

        // Get the items to split
        $items = PathResolver::getValueByPath($data, $splitPath);

        if (empty($items) || !is_array($items)) {
            return [$data]; // Return the original data if there are no items to split
        }

        // Step 1: Calculate the total quantity to split
        $totalQuantity = 0;
        foreach ($items as $item) {
            // Accumulate the total quantity based on the criteria path
            $totalQuantity += PathResolver::getValueByPath($item, $criteriaPath);
        }

        // Step 2: Determine if splitting is necessary
        if ($totalQuantity <= $limit) {
            return [$data]; // No split needed if total quantity is within the limit
        }

        // Determine the number of splits required
        $numSplits = ceil($totalQuantity / $limit); // Calculate how many groups are needed to keep each group within the limit

        // Step 3: Create an array to hold the results, initializing empty sets
        $results = array_fill(0, $numSplits, $data);
        foreach ($results as &$result) {
            // Clear the items list in each result set to prepare for splitting
            PathResolver::setValueByPath($result, $splitPath, []);
        }

        // Step 4: Distribute quantities across each group
        foreach ($items as $item) {

            $originalQuantity = PathResolver::getValueByPath($item, $criteriaPath); // Get the original quantity to be split
            $baseQuantity = floor($originalQuantity / $numSplits); // Base quantity for each group
            $remainder = $originalQuantity % $numSplits; // Calculate the remainder to distribute among the first few groups

            // Add the split items to each result set
            for ($i = 0; $i < $numSplits; $i++) {
                $splitItem = $item; // Create a copy of the item to modify

                // We set the original value
                PathResolver::setValueByPath($splitItem, "{$criteriaPath}_original", $originalQuantity);

                // Set the split quantity for the item
                $splitQuantity = ($i < $remainder) ? ($baseQuantity + 1) : $baseQuantity;

                // Skip adding the item if the split quantity is zero
                if ($splitQuantity > 0) {
                    PathResolver::setValueByPath($splitItem, $criteriaPath, $splitQuantity);

                    // Get the current list of items in the group
                    $currentItems = PathResolver::getValueByPath($results[$i], $splitPath);
                    $currentItems[] = $splitItem; // Add the split item to the list

                    // Update the group with the new list of items
                    PathResolver::setValueByPath($results[$i], $splitPath, $currentItems);

                    // Mark as split data
                    $results[$i]['has_been_split'] = 1;
                    $results[$i]['workflow_list_position'] = $i;
                }
            }
        }

        // Step 5: Remove empty groups
        $results = array_filter($results, function($result) use ($splitPath) {
            $items = PathResolver::getValueByPath($result, $splitPath);
            return !empty($items); // Keep only groups that have items
        });

        return array_values($results); // Return the array of split results, re-indexed
    }

}
