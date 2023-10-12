<?php

namespace PhelixJuma\DataTransformer\Utils;

use PhelixJuma\DataTransformer\Conditions\SimpleCondition;
use PhelixJuma\DataTransformer\Exceptions\UnknownOperatorException;


class DataJoiner
{
    private array $data;
    private array $joinPaths;
    private array $condition;

    protected PathResolver $pathResolver;

    public function __construct(array $data, array $joinPaths, array $condition)
    {
        $this->data = $data;
        $this->joinPaths = $joinPaths;
        $this->condition = $condition;

        $this->pathResolver = new PathResolver();
    }

    /**
     * @return array
     * @throws UnknownOperatorException
     */
    public function mergeData(): array
    {
        $mergedData = $this->data;

        for ($i = 0; $i < count($mergedData); $i++) {
            for ($j = $i + 1; $j < count($mergedData);) {
                if ($this->evaluateCondition($mergedData[$i], $mergedData[$j])) {
                    $mergedData[$i] = $this->mergeDataOnJoinPaths($mergedData[$i], $mergedData[$j]);
                    array_splice($mergedData, $j, 1);  // Removes $mergedData[$j]
                } else {
                    $j++;  // Only increment $j when no merge occurs
                }
            }
        }

        return $mergedData;
    }

    /**
     * @param array $datum1
     * @param array $datum2
     * @return array
     */
    private function mergeDataOnJoinPaths(array $datum1, array $datum2): array
    {
        foreach ($this->joinPaths as $path) {

            $value1 = $this->pathResolver->getValueByPath($datum1, $path);
            $value2 = $this->pathResolver->getValueByPath($datum2, $path);

            // Merging logic: handle both array and scalar values.
            if (is_array($value1) && is_array($value2)) {
                // Merge arrays
                $mergedValue = array_merge_recursive($value1, $value2);
            } elseif (is_array($value1)) {
                // Add scalar value to array
                $mergedValue = array_merge($value1, [$value2]);
            } elseif (is_array($value2)) {
                // Add scalar value to array
                $mergedValue = array_merge([$value1], $value2);
            } else {
                // Create array from scalar values
                $mergedValue = [$value1, $value2];
            }

            $this->pathResolver->setValueByPath($datum1, $path, $mergedValue);
        }
        return $datum1;
    }


    /**
     * @param array $datum1
     * @param array $datum2
     * @param array|null $condition
     * @return bool
     * @throws UnknownOperatorException
     */
    private function evaluateCondition(array $datum1, array $datum2, array $condition = null): bool
    {
        if ($condition === null) {
            $condition = $this->condition;
        }

        $operator = $condition['operator'] ?? null;
        $conditions = $condition['conditions'] ?? null;

        // Handling simple condition
        if (empty($operator) || empty($conditions)) {

            $path = $condition['path'];
            $compareOperator = $condition['operator'];

            $value1 = $this->pathResolver->getValueByPath($datum1, $path);
            $value2 = $this->pathResolver->getValueByPath($datum2, $path);

            // Check for empty values and ignore them
            if (empty($value1) || empty($value2)) {
                return false;
            }

            return SimpleCondition::compare($value1, $compareOperator, $value2);
        }

        // Handling composite condition
        if (!empty($operator) && !empty($conditions)) {
            $results = [];
            foreach ($conditions as $cond) {
                $results[] = $this->evaluateCondition($datum1, $datum2, $cond);
            }
            return $operator === 'and' ? self::all($results) : self::any($results);
        }

        throw new UnknownOperatorException('Invalid condition format');
    }

    /**
     * @param array $input
     * @return bool
     */
    private function all(array $input): bool
    {
        return array_reduce($input, function ($carry, $item) {
            return $carry && $item;
        }, true);
    }

    /**
     * @param array $input
     * @return bool
     */
    private function any(array $input): bool
    {
        return array_reduce($input, function ($carry, $item) {
            return $carry || $item;
        }, false);
    }
}
