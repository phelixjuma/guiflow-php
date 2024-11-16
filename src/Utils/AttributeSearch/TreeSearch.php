<?php

namespace PhelixJuma\GUIFlow\Utils\AttributeSearch;

use PhelixJuma\GUIFlow\Utils\Utils;

/**
 * Class Search
 *
 * Handles the extraction of entities from product names using a graph-based approach.
 */
class TreeSearch
{

    /**
     * @param $node
     * @param $numberOfLevels
     * @param $depth
     * @param $cumulativeConfidence
     * @return void
     */
    private static function computePathCumulativeConfidence(&$node, $numberOfLevels, $depth = 0, $cumulativeConfidence = 1)
    {

        // Handle the root node separately if the value is a string (e.g., "root").
        if (is_string($node["value"])) {
            foreach ($node["children"] as &$child) {
                self::computePathCumulativeConfidence($child, $numberOfLevels, $depth + 1, $cumulativeConfidence);
            }
            return;
        }

        // Process nodes with associative array values.
        $node["value"]["scores"]["depth"] = $depth;
        $node["value"]["scores"]["depth_weight"] = 1 + ($numberOfLevels-$depth+1)/$numberOfLevels;

        // We add weighted confidence
        $node["value"]["scores"]["weighted_confidence"] = pow($node["value"]["scores"]["confidence"], $node["value"]["scores"]["depth_weight"]);

        $cumulativeConfidence = $cumulativeConfidence + $node["value"]["scores"]["weighted_confidence"];
        $node["value"]["scores"]["cumulative_weighted_confidence"] = $cumulativeConfidence;

        $depth += 1;

        foreach ($node["children"] as &$child) {
            self::computePathCumulativeConfidence($child, $numberOfLevels, $depth, $cumulativeConfidence);
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
        $newEditsCount = $editsCount;
        //print_r($node);
//        if ($node["value"]["matched"]["value"] !== null) {
//            $isMatch = $node["value"]["value"] === $node["value"]["matched"]["value"];
//            $newEditsCount = $isMatch ? $editsCount : $editsCount + 1;
//        }
        if (!empty($node["value"]["value"])) {
            $isMatch = $node["value"]["value"] === $node["value"]["matched"]["value"];
            $newEditsCount = $isMatch ? $editsCount : $editsCount + 1;
        }

        // Add current node to the path.
        $attributeName = $node["value"]["attribute"]["name"] ?? null;
        if ($attributeName) {
            $currentPath[$attributeName] = [
                "value" => $node["value"]["value"],
                "scores" => $node["value"]["scores"],
                "matched" => $node["value"]["matched"],
            ];
        }

        // If this is a leaf node, save the current path with edit count and cumulative confidence.
        if (empty($node["children"])) {
            // Use cumulative_weighted_confidence if available, else fallback to confidence
            $cumulativeConfidence = array_sum(array_map(function($entry) {
                return $entry['scores']['weighted_confidence'] ?? $entry['scores']['confidence'];
            }, $currentPath));

            $allPaths[] = [
                "path" => $currentPath,
                "edit_count" => $newEditsCount,
                "cumulative_weighted_confidence" => $cumulativeConfidence
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
                            "cumulative_weighted_confidence" => 0
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
            return $a['edit_count'] <=> $b['edit_count'] ?: $b['cumulative_weighted_confidence'] <=> $a['cumulative_weighted_confidence'];
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

        //print("Tree data for $searchItem is: ".json_encode($tree_with_confidence_scores));

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
