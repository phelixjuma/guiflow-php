<?php

namespace PhelixJuma\GUIFlow\Utils;

class JsonSchemaResolver
{

    /**
     * @param $schema
     * @return mixed
     */
    public static function resolveSchema($schema) {
        return self::resolve($schema);
    }

    /**
     * @param $schema
     * @param $originalSchema
     * @param $currentPath
     * @param $visitedPaths
     * @return mixed
     */
    private static function resolve($schema, $originalSchema = null, $currentPath = '', $visitedPaths = []) {
        $isTopLevel = $originalSchema === null;
        $originalSchema = $originalSchema ?? $schema;
        
        // Check if we've already visited this path
        if (in_array($currentPath, $visitedPaths)) {
            return $schema; // Break the cycle
        }
        
        // Add current path to visited paths
        $visitedPaths[] = $currentPath;

        $refs = [];
        self::traverseSchema($schema, '', $refs);

        $orderedRefs = self::orderRefs($refs);

        foreach ($orderedRefs as $ref) {
            $refPath = str_replace('.$ref', '', $ref['path']);
            $refValuePath = str_replace("/", ".", str_replace("#/", "", $ref['value']));

            // Check if the reference points to a path we've already visited
            if (in_array($refValuePath, $visitedPaths)) {
                continue; // Skip this reference to break the cycle
            }

            $resolvedSubSchema = PathResolver::getValueByPath($originalSchema, $refValuePath);
            
            $resolvedSubSchema = self::resolve($resolvedSubSchema, $originalSchema, $refValuePath, $visitedPaths);

            if (!is_null($resolvedSubSchema)) {
                // Update the current schema
                PathResolver::setValueByPath($schema, $refPath, $resolvedSubSchema);
                
                // Update the original schema if we're in a sub-schema
                if (!$isTopLevel) {
                    PathResolver::setValueByPath($originalSchema, $currentPath . '.' . $refPath, $resolvedSubSchema);
                }
            }
        }

        return $schema;
    }

    /**
     * @param $node
     * @param string $path
     * @param array $refs
     * @return void
     */
    public static function traverseSchema($node, string $path, array &$refs)
    {
        if (is_array($node)) {
            foreach ($node as $key => $value) {
                $newPath = $path ? "$path.$key" : $key;
                if ($key === '$ref' && is_string($value)) {
                    $refs[] = ['path' => $newPath, 'value' => $value];
                } elseif (is_array($value)) {
                    self::traverseSchema($value, $newPath, $refs);
                }
            }
        }
    }

    /**
     * @param $refs
     * @return array
     */
    private static function orderRefs($refs) {
        $graph = [];
        $inDegree = [];

        // Build the dependency graph
        foreach ($refs as $ref) {
            $value = str_replace("#/", "", $ref['value']);
            if (!isset($graph[$value])) {
                $graph[$value] = [];
                $inDegree[$value] = 0;
            }
        }

        foreach ($refs as $ref) {
            $refValue = str_replace("#/", "", $ref['value']);
            foreach ($refs as $otherRef) {
                $otherValue = str_replace("#/", "", $otherRef['value']);
                if (strpos($otherValue, $refValue) === 0 && $otherValue !== $refValue) {
                    $graph[$refValue][] = $otherValue;
                    $inDegree[$otherValue]++;
                }
            }
        }

        // Topological sort
        $queue = new \SplQueue();
        foreach ($inDegree as $value => $degree) {
            if ($degree === 0) {
                $queue->enqueue($value);
            }
        }

        $orderedValues = [];
        while (!$queue->isEmpty()) {
            $value = $queue->dequeue();
            $orderedValues[] = $value;

            foreach ($graph[$value] as $neighbor) {
                $inDegree[$neighbor]--;
                if ($inDegree[$neighbor] === 0) {
                    $queue->enqueue($neighbor);
                }
            }
        }

        // Map ordered values back to original ref objects
        $orderedRefs = [];
        foreach ($orderedValues as $value) {
            $matchingRefs = array_filter($refs, function($ref) use ($value) {
                return str_replace("#/", "", $ref['value']) === $value;
            });
            $orderedRefs = array_merge($orderedRefs, array_values($matchingRefs));
        }

        return $orderedRefs;
    }
}
