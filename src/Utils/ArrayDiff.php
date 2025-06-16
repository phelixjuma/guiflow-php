<?php

namespace PhelixJuma\GUIFlow\Utils;

class ArrayDiff
{
    private $primaryKey;
    private $searchKey;
    private $similarityThreshold;

    public function __construct($primaryKey, $searchKey, $similarityThreshold = 0.6)
    {
        $this->primaryKey = $primaryKey;
        $this->searchKey = $searchKey;
        $this->similarityThreshold = $similarityThreshold;
    }

    public function compareArrays($originalArray, $modifiedArray)
    {
        // Step 1: Compute similarity LCS
        list($dp, $backtrack) = $this->computeSimilarityLCS($originalArray, $modifiedArray);

        // Step 2: Backtrack to find optimal alignment
        $alignment = $this->backtrackAlignment($backtrack, $originalArray, $modifiedArray);

        // Step 3: Generate differences based on alignment
        $differences = $this->generateDifferences($alignment, $originalArray, $modifiedArray);

        return $differences;
    }

    private function computeItemSimilarity($item1, $item2)
    {
        $primaryKeyMatch = ($item1[$this->primaryKey] === $item2[$this->primaryKey]) ? 1 : 0;

        // Compute search key similarity using advanced measures
        $searchKeySimilarity = $this->advancedStringSimilarity($item1[$this->searchKey], $item2[$this->searchKey]);

        // Weighted similarity score
        $similarity = ($primaryKeyMatch * 0.7) + ($searchKeySimilarity * 0.3);

        return $similarity;
    }

    private function advancedStringSimilarity($str1, $str2)
    {
        // Implement a suitable string similarity measure
        // For demonstration, we'll use the normalized Levenshtein distance
        $distance = levenshtein($str1, $str2);
        $maxLen = max(strlen($str1), strlen($str2));
        if ($maxLen == 0) return 1;
        $similarity = 1 - ($distance / $maxLen);
        return $similarity;
    }

    private function computeSimilarityLCS($originalArray, $modifiedArray)
    {
        $n = count($originalArray);
        $m = count($modifiedArray);
        $dp = array_fill(0, $n + 1, array_fill(0, $m + 1, 0));
        $backtrack = array_fill(0, $n + 1, array_fill(0, $m + 1, null));

        for ($i = $n - 1; $i >= 0; $i--) {
            for ($j = $m - 1; $j >= 0; $j--) {
                $similarity = $this->computeItemSimilarity($originalArray[$i], $modifiedArray[$j]);

                if ($similarity >= $this->similarityThreshold) {
                    $dp[$i][$j] = $dp[$i + 1][$j + 1] + $similarity;
                    $backtrack[$i][$j] = 'match';
                } else {
                    if ($dp[$i + 1][$j] >= $dp[$i][$j + 1]) {
                        $dp[$i][$j] = $dp[$i + 1][$j];
                        $backtrack[$i][$j] = 'down';
                    } else {
                        $dp[$i][$j] = $dp[$i][$j + 1];
                        $backtrack[$i][$j] = 'right';
                    }
                }
            }
        }

        return [$dp, $backtrack];
    }

    private function backtrackAlignment($backtrack, $originalArray, $modifiedArray)
    {
        $i = 0;
        $j = 0;
        $n = count($originalArray);
        $m = count($modifiedArray);
        $alignment = [];

        while ($i < $n || $j < $m) {
            if ($i < $n && $j < $m && $backtrack[$i][$j] === 'match') {
                $alignment[] = ['type' => 'match', 'original_index' => $i, 'modified_index' => $j];
                $i++;
                $j++;
            } elseif ($j < $m && ($i === $n || $backtrack[$i][$j] === 'right')) {
                $alignment[] = ['type' => 'insertion', 'modified_index' => $j];
                $j++;
            } elseif ($i < $n && ($j === $m || $backtrack[$i][$j] === 'down')) {
                $alignment[] = ['type' => 'deletion', 'original_index' => $i];
                $i++;
            } else {
                // No more items
                break;
            }
        }

        return $alignment;
    }

    private function generateDifferences($alignment, $originalArray, $modifiedArray)
    {
        $result = [];

        foreach ($alignment as $entry) {
            if ($entry['type'] === 'match') {
                $originalItem = $originalArray[$entry['original_index']];
                $modifiedItem = $modifiedArray[$entry['modified_index']];
                $changes = $this->compareItems($originalItem, $modifiedItem);
                $result[] = $changes;
            } elseif ($entry['type'] === 'deletion') {
                $originalItem = $originalArray[$entry['original_index']];
                $changes = [];
                foreach ($originalItem as $key => $value) {
                    $changes[$key] = [
                        'original' => $value,
                        'final' => null,
                        'change_type' => 'deletion',
                    ];
                }
                $result[] = $changes;
            } elseif ($entry['type'] === 'insertion') {
                $modifiedItem = $modifiedArray[$entry['modified_index']];
                $changes = [];
                foreach ($modifiedItem as $key => $value) {
                    $changes[$key] = [
                        'original' => null,
                        'final' => $value,
                        'change_type' => 'insertion',
                    ];
                }
                $result[] = $changes;
            }
        }

        return $result;
    }

    private function compareItems($originalItem, $modifiedItem)
    {
        $allKeys = array_unique(array_merge(array_keys($originalItem), array_keys($modifiedItem)));
        $changes = [];
        foreach ($allKeys as $key) {
            $originalValue = isset($originalItem[$key]) ? $originalItem[$key] : null;
            $modifiedValue = isset($modifiedItem[$key]) ? $modifiedItem[$key] : null;
            if ($originalValue !== $modifiedValue) {
                $changeType = 'edit';
            } else {
                $changeType = 'none';
            }
            $changes[$key] = [
                'original' => $originalValue,
                'final' => $modifiedValue,
                'change_type' => $changeType,
            ];
        }
        return $changes;
    }
}
