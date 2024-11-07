<?php

namespace PhelixJuma\GUIFlow\Utils\AttributeSearch;

use Fhaculty\Graph\Vertex;

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
    private static function computePathCumulativeConfidence_(&$node, $numberOfLevels, $depth = 0, $cumulativeConfidence = 1)
    {

        // Handle the root node separately if the value is a string (e.g., "root").
        if (is_string($node["value"])) {
            foreach ($node["children"] as &$child) {
                self::computePathCumulativeConfidence_($child, $numberOfLevels, $depth + 1, $cumulativeConfidence);
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
            self::computePathCumulativeConfidence_($child, $numberOfLevels, $depth, $cumulativeConfidence);
        }
    }

    /**
     * @param $node
     * @param $numberOfLevels
     * @param $depth
     * @param $cumulativeConfidence
     * @return void
     */
    private static function computePathCumulativeConfidence(&$node, $numberOfLevels, $depth = 0, $cumulativeConfidence = 1)
    {
        // Handle root node separately if the value is a string (e.g., "root").
        if (is_string($node["value"])) {
            foreach ($node["children"] as &$child) {
                self::computePathCumulativeConfidence($child, $numberOfLevels, $depth + 1, $cumulativeConfidence);
            }
            return;
        }

        // Calculate depth weight and initial weighted confidence for the current node.
        $node["value"]["scores"]["depth"] = $depth;
        $node["value"]["scores"]["depth_weight"] = 1 + ($numberOfLevels - $depth + 1) / $numberOfLevels;
        $node["value"]["scores"]["weighted_confidence"] = pow($node["value"]["scores"]["confidence"], $node["value"]["scores"]["depth_weight"]);

        // Initialize penalty tracking for the current node.
        $penalty = 0;

        // Check each child node for consistency with the current node.
        foreach ($node["children"] as &$child) {
            // Recursively calculate confidence for child nodes.
            self::computePathCumulativeConfidence($child, $numberOfLevels, $depth + 1, $cumulativeConfidence);

            // Check if the child value is consistent with the current node.
            if (!self::isConsistentWithBranch($node, $child["value"]["value"])) {
                // Calculate penalty based on child's depth and confidence.
                $childConfidence = $child["value"]["scores"]["confidence"];
                $childDepth = $child["value"]["scores"]["depth"];
                // Penalize based on mismatch with lower level attribute's depth and confidence.
                $penaltyAdjustment = (1 - $childConfidence) * ($numberOfLevels - $childDepth) / $numberOfLevels;
                $penalty += $penaltyAdjustment;
            }
        }

        // Apply the penalty to the current node’s confidence score.
        $node["value"]["scores"]["penalty"] = $penalty; // Track the total penalty for this node
        $node["value"]["scores"]["penalized_confidence"] = max(0, $node["value"]["scores"]["weighted_confidence"] - $penalty);

        // Update cumulative confidence with penalized confidence.
        $cumulativeConfidence = $cumulativeConfidence + $node["value"]["scores"]["penalized_confidence"];
        $node["value"]["scores"]["cumulative_weighted_confidence"] = $cumulativeConfidence;
    }

    /**
     * Function to check if a child value is valid within the current node's branch
     * @param $node
     * @param $childValue
     * @return bool
     */
    private static function isConsistentWithBranch(&$node, $childValue)
    {
        // Recursive function to collect all valid descendant values of a node.
        $validValues = self::collectBranchValues($node);
        return in_array($childValue, $validValues);
    }

    /**
     * Recursive function to collect all descendant values from a node's branch
     * @param $node
     * @return array
     */
    private static function collectBranchValues(&$node)
    {
        $values = [];
        foreach ($node["children"] as $child) {
            $values[] = $child["value"]["value"];
            // Recursively collect values from deeper levels in the tree
            $values = array_merge($values, self::collectBranchValues($child));
        }
        return $values;
    }

    private static function getTreeDepth($node, $depth = 0)
    {
        if (empty($node["children"])) {
            return $depth;
        }

        $depths = array_map(function ($child) use ($depth) {
            return self::getTreeDepth($child, $depth + 1);
        }, $node["children"]);

        return max($depths);
    }

    /**
     * @param $treeData
     * @return void
     */
    private static function computeCumulativeConfidenceScores(&$treeData)
    {

        $numberOfLevels = self::getTreeDepth($treeData);

        self::computePathCumulativeConfidence($treeData, $numberOfLevels, 0, 1);

        //return $treeData;
    }

    /**
     * @param $node
     * @param $maxPath
     * @param $maxCumulativeContent
     * @param $currentPath
     * @return void
     */
    private static function findMostProbablePath($node, &$maxPath, &$maxCumulativeContent, $currentPath = [])
    {

        // Check if the node is a leaf node (has no children)
        if (empty($node["children"])) {

            $cumulativeContent = $node["value"]["scores"]["cumulative_weighted_confidence"] ?? 0;

            // Update maxPath if the current path has a higher cumulative content
            if ($cumulativeContent > $maxCumulativeContent) {
                $maxCumulativeContent = $cumulativeContent;
                $maxPath = $currentPath;
            }
            return;
        }

        // Traverse children, appending the current node's details to the path
        foreach ($node["children"] as $child) {
            $attributeName = $child["value"]["attribute"]["name"] ?? null;
            if ($attributeName) {
                $currentPath[$attributeName] = [
                    "value" => $child["value"]["value"],
                    "scores" => $child["value"]["scores"]
                ];
                // Recurse on the child node
                self::findMostProbablePath($child, $maxPath, $maxCumulativeContent, $currentPath);
                // Remove the last item to backtrack correctly
                unset($currentPath[$attributeName]);
            }
        }
    }

    /**
     * @param $treeData
     * @param $attributeNames
     * @return array
     */
    private static function getBestPath($treeData, $attributeNames)
    {
        $maxPath = [];
        $maxCumulativeContent = 0;

        // Start traversal from the root node
        self::findMostProbablePath($treeData, $maxPath, $maxCumulativeContent);

        foreach ($attributeNames as $attribute) {
            if (!isset($maxPath[$attribute])) {
                $maxPath[$attribute] = [
                    "value" => "",
                    "scores" => [
                        "confidence" => 0
                    ]
                ];
            }
        }

        return $maxPath;
    }

    private static function findHighestConfidenceNodesAtEachLevel($node, &$highestConfidenceNodes, $level = 0)
    {
        // Calculate the confidence of the current node
        $confidenceScore = $node["value"]["scores"]["confidence"] ?? 0;
        $attributeName = $node["value"]["attribute"]["name"] ?? null;

        // Ensure we are only adding nodes with valid attribute names
        if ($attributeName) {
            // If we don't have any node for this attribute at this level, initialize it
            if (!isset($highestConfidenceNodes[$attributeName]) || $confidenceScore > $highestConfidenceNodes[$attributeName]["scores"]["confidence"]) {
                // Update the node with the highest confidence score for this attribute
                $highestConfidenceNodes[$attributeName] = [
                    "value" => $node["value"]["value"] ?? "",
                    "scores" => $node["value"]["scores"]
                ];
            }
        }

        // Traverse children to process the next level
        foreach ($node["children"] as $child) {
            self::findHighestConfidenceNodesAtEachLevel($child, $highestConfidenceNodes, $level + 1);
        }
    }

    /**
     * @param $treeData
     * @return array
     */
    private static function getHighestConfidenceNodesByLevel($treeData)
    {
        $highestConfidenceNodes = [];

        // Start traversal from the root node
        self::findHighestConfidenceNodesAtEachLevel($treeData, $highestConfidenceNodes);

        return $highestConfidenceNodes;
    }

    /**
     * @param string $searchItem
     * @param array $attributes
     * @param $attribute_tree
     * @param $corpus_with_attributes
     * @param callable $nodePathConfidenceCalculatorFunction
     * @return array
     */
    public static function extractMatchingAttributes(string $searchItem, array $attributes, $attribute_tree, $corpus_with_attributes, callable $nodePathConfidenceCalculatorFunction): array
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

        // We add "scores" to tree data
        $tree_with_confidence_scores = AttributeGraphBuilder::addConfidenceScoresToTree($attribute_tree, $nodePathConfidences);

        // Calculate tree information content
        self::computeCumulativeConfidenceScores($tree_with_confidence_scores);

        //print("Tree data for $searchItem is: ".json_encode($tree_with_confidence_scores));

        // We get the best path - this is the matching attributes for the search item
        return self::getBestPath($tree_with_confidence_scores, $builder->get_hierarchy_order());
        //return self::getHighestConfidenceNodesByLevel($tree_with_confidence_scores);
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
