<?php

namespace PhelixJuma\GUIFlow\Utils\AttributeSearch;

use InvalidArgumentException;
use PhelixJuma\GUIFlow\Utils\Utils;

/**
 * Class Search
 *
 * Handles the extraction of entities from product names using a graph-based approach.
 */
class TreeSearch
{

    /**
     * @param $totalItems
     * @param $missingItems
     * @return float
     */
    private static function calculateNormalizedEntropy($totalItems, $missingItems)
    {
        // Validate input: totalItems must be non-negative, missingItems must be non-negative, and missingItems <= totalItems
        if ($totalItems < 0 || $missingItems < 0 || $missingItems > $totalItems) {
            throw new InvalidArgumentException('Invalid input: Ensure totalItems and missingItems are non-negative, and missingItems does not exceed totalItems.');
        }

        // Prevent division by zero
        if ($totalItems === 0) {
            return 0.0; // No entropy if no items exist
        }

        // Calculate the probabilities
        $presentItems = $totalItems - $missingItems;
        $pPresent = $presentItems / $totalItems; // Probability of present items
        $pMissing = $missingItems / $totalItems; // Probability of missing items

        // Calculate entropy components
        $entropy = 0.0;
        if ($pPresent > 0) {
            $entropy -= $pPresent * log($pPresent, 2);
        }
        if ($pMissing > 0) {
            $entropy -= $pMissing * log($pMissing, 2);
        }

        // Normalize the entropy by the maximum possible entropy (log2(total outcomes))
        // For binary outcomes (present/missing), maxEntropy is log2(2) = 1
        $maxEntropy = 1.0;

        return $entropy / $maxEntropy;
    }

    /**
     * @param $node
     * @param $numberOfLevels
     * @param $depth
     * @param $cumulativeLog
     * @return void
     */
    private static function computePathCumulativeConfidence(&$node, $numberOfLevels, $depth = 0, $cumulativeLog = 0)
    {

        // Handle the root node separately if the value is a string (e.g., "root").
        if (is_string($node["value"])) {
            foreach ($node["children"] as &$child) {
                self::computePathCumulativeConfidence($child, $numberOfLevels, $depth + 1, $cumulativeLog);
            }
            return;
        }


        // Process nodes with associative array values.
        $node["value"]["scores"]["depth"] = $depth;
        $node["value"]["scores"]["depth_weight"] = 1 + ($numberOfLevels-$depth+1)/$numberOfLevels;
        $node["value"]["scores"]["missing_values_penalty"] = self::calculateNormalizedEntropy($node["value"]["counts"]['total'], $node["value"]["counts"]['missing']);

        // We add weighted confidence
        $node["value"]["scores"]["weighted_confidence"] = pow($node["value"]["scores"]["confidence"], $node["value"]["scores"]["depth_weight"]);

        // We get the penalized weighted confidence
        $node["value"]["scores"]["penalized_weighted_confidence"] = $node["value"]["scores"]["weighted_confidence"] * (1 - $node["value"]["scores"]["missing_values_penalty"]);

        // We get the log of the penalized weighted confidence
        $epsilon = 1e-10; // A small positive constant to prevent log(0)
        $node["value"]["scores"]["log_penalized_weighted_confidence"] = $node["value"]["scores"]["penalized_weighted_confidence"] > 0
            ? log($node["value"]["scores"]["penalized_weighted_confidence"], 2)
            : log($epsilon, 2);

        $cumulativeLog = $cumulativeLog + $node["value"]["scores"]["log_penalized_weighted_confidence"];
        $node["value"]["scores"]["cumulative_log_confidence"] = $cumulativeLog;

        $depth += 1;

        foreach ($node["children"] as &$child) {
            self::computePathCumulativeConfidence($child, $numberOfLevels, $depth, $cumulativeLog);
        }
    }

    /**
     * @param $treeData
     * @param $numberOfLevels
     * @return void
     */
    private static function computeCumulativeConfidenceScores(&$treeData, $numberOfLevels)
    {
        self::computePathCumulativeConfidence($treeData, $numberOfLevels);
    }

    /**
     * @param $node
     * @param $tree
     * @param $allPaths
     * @param $currentPath
     * @param $editsCount
     * @return void
     */
    private static function findAllProbablePaths(&$node, &$tree, &$allPaths, $currentPath = [], $editsCount = 0)
    {
        // Skip root node if it has "value" set to "root" and process children directly.
        if ($node["value"] === "root") {
            foreach ($node["children"] as &$child) {
                self::findAllProbablePaths($child, $tree, $allPaths, $currentPath, $editsCount);
            }
            return;
        }

        // Check if this node matches the selected node for continuity, only if matched[value] is not null.
        $isMatch = empty($node["value"]["value"]) && is_null($node["value"]["matched"]["value"]) || ($node["value"]["value"] === $node["value"]["matched"]["value"]);
        $newEditsCount = $isMatch ? $editsCount : $editsCount + 1;


        // Add current node to the path.
        $attributeName = $node["value"]["attribute"]["name"] ?? null;
        if ($attributeName) {
            $currentPath[$attributeName] = [
                "value" => $node["value"]["value"],
                "scores" => $node["value"]["scores"],
                "counts" => $node["value"]["counts"],
                "matched" => $node["value"]["matched"],
            ];
        }

        // If this is a leaf node, save the current path with edit count and cumulative confidence.
        if (empty($node["children"])) {
            // Use cumulative_log_confidence if available, else fallback to confidence
            $cumulativeLog = array_sum(array_map(function($entry) {
                return $entry['scores']['log_penalized_weighted_confidence'] ?? 0;
            }, $currentPath));

            $allPaths[] = [
                "path" => $currentPath,
                "edit_count" => $newEditsCount,
                "cumulative_log_confidence" => $cumulativeLog
            ];
            return;
        }

        // Recurse on each child to build all paths
        foreach ($node["children"] as &$child) {
            self::findAllProbablePaths($child, $tree, $allPaths, $currentPath, $newEditsCount);
        }

        // Backtrack to explore alternate paths.
        if ($attributeName) {
            unset($currentPath[$attributeName]);
        }
    }

    /**
     * Entry function to initiate path-finding, ranking paths by minimum edit count and then by cumulative weighted confidence
     *
     * @param $treeData
     * @param $attributeNames
     * @return array
     */
    public static function getAllPaths($treeData, $attributeNames)
    {
        $allPaths = [];

        // Start traversal from the root node to gather all probable paths
        self::findAllProbablePaths($treeData, $treeData, $allPaths, [], 0);

        // Ensure all attributes are included in each path (default to empty if missing)
        foreach ($allPaths as &$pathInfo) {
            foreach ($attributeNames as $attribute) {
                if (!isset($pathInfo["path"][$attribute])) {
                    $pathInfo["path"][$attribute] = [
                        "value" => "",
                        "scores" => [
                            "confidence" => 0,
                            "cumulative_log_confidence" => 0
                        ],
                        "counts"    => [
                            "total"     => 0,
                            "missing"   => 0
                        ],
                        "matched" => [
                            "value"         => null,
                            "confidence"    => null
                        ]
                    ];
                }
            }
        }

        // Sort paths first by edit count (ascending), then by cumulative weighted confidence (descending)
        usort($allPaths, function ($a, $b) {
            return $a['edit_count'] <=> $b['edit_count'] ?: $b['cumulative_log_confidence'] <=> $a['cumulative_log_confidence'];
        });

        return $allPaths;
    }

    /**
     * @param string $searchItem
     * @param array $attributes
     * @param $attribute_tree
     * @param $corpus_with_attributes
     * @param callable $nodePathConfidenceCalculatorFunction
     * @param $min_confidence
     * @return array
     */
    public static function extractMatchingAttributes(string $searchItem, array $attributes, $attribute_tree, $corpus_with_attributes, callable $nodePathConfidenceCalculatorFunction, $min_confidence = 0.1)
    {

        // Instantiate the AttributeGraphBuilder
        $builder = new AttributeGraphBuilder($attributes);

        // Build the nested tree from the corpus data, if no attribute data is given
        if (empty($attribute_tree)) {
            $attribute_tree = $builder->build_nested_tree($corpus_with_attributes);
        }

        // We get nodes and branching options
        $nodesAndBranchingOptions = AttributeGraphBuilder::extractTreeNodesAndBranchingOptions($attribute_tree);

        // We compute tree "scores" using a user defined function
        $nodePathConfidences = $nodePathConfidenceCalculatorFunction($searchItem, $nodesAndBranchingOptions);

        // Sort classifications from the highest confidence
        array_walk($nodePathConfidences, function (&$value, $key) {
            $value['classification'] = Utils::sortMultiAssocArrayByKey($value['classification'], 'confidence', 'desc');
        });

        // We add "scores" to tree data
        $tree_with_confidence_scores = AttributeGraphBuilder::addConfidenceScoresToTree($attribute_tree, $nodePathConfidences, $min_confidence);

        // We get the number of levels in the tree - the tree depth
        $numberOfLevels = sizeof($builder->get_hierarchy_order());

        // Calculate tree information content
        self::computeCumulativeConfidenceScores($tree_with_confidence_scores, $numberOfLevels);

        // We get the best path - this is the matching attributes for the search item
        return self::getAllPaths($tree_with_confidence_scores, $builder->get_hierarchy_order());
    }

    /**
     * @param $corpus
     * @param array $attributes
     * @return array
     */
    public static function getAttributesFromCorpusFields($corpus, array $attributes): array
    {

        // Sort attributes by 'order'
        usort($attributes, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        // Extract sorted attribute names for hierarchy
        $orderedAttributeNames = array_map(function($attr) {
            return $attr['name'];
        }, $attributes);

        foreach ($corpus as &$corpusItem) {
            $attributes = [];
            foreach ($orderedAttributeNames as $attributeName) {
                if (array_key_exists($attributeName, $corpusItem)) {
                    $attributes[$attributeName] = [
                        "value" => $corpusItem[$attributeName],
                        "scores"    => [
                            "confidence"    => 1
                        ]
                    ];
                }
            }
            $corpusItem['extracted_attributes'] = $attributes;
        }

        return $corpus;
    }
}
