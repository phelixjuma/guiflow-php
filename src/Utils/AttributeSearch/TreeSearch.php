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
     * @param $node
     * @param $depth
     * @return int|mixed
     */
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
    private static function findMostProbablePath_($node, &$maxPath, &$maxCumulativeContent, $currentPath = [])
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
                self::findMostProbablePath_($child, $maxPath, $maxCumulativeContent, $currentPath);
                // Remove the last item to backtrack correctly
                unset($currentPath[$attributeName]);
            }
        }
    }

    /**
     * @param $node
     * @param $tree
     * @param $allPaths
     * @param $currentPath
     * @param $editsCount
     * @param $level
     * @param $minConfidenceThreshold
     * @return void
     */
    private static function findAllProbablePaths(&$node, &$tree, &$allPaths, $currentPath = [], $editsCount = 0, $level = 0, $minConfidenceThreshold = 0.001)
    {
        // Skip root node if it has "value" set to "root" and process children directly.
        if ($node["value"] === "root") {
            foreach ($node["children"] as &$child) {
                self::findAllProbablePaths($child, $tree, $allPaths, $currentPath, $editsCount, $level, $minConfidenceThreshold);
            }
            return;
        }

        // Identify the selected node at this level (highest confidence node meeting the threshold).
        $selectedNode = self::getSelectedNodeAtLevel($tree, $level, 0, $minConfidenceThreshold);
        $selectedNodeValue = $selectedNode["value"]["value"] ?? null;

        // Add selected_node_value to current node's scores for comparison purposes
        $node["value"]["scores"]["selected_node_value"] = $selectedNodeValue;

        // Check if this node matches the selected node for continuity, only if selected_node_value is not null.
        $newEditsCount = $editsCount;
        if ($selectedNodeValue !== null) {
            $isMatch = $node["value"]["value"] === $selectedNodeValue;
            $newEditsCount = $isMatch ? $editsCount : $editsCount + 1;
        }

        // Add current node to the path.
        $attributeName = $node["value"]["attribute"]["name"] ?? null;
        if ($attributeName) {
            $currentPath[$attributeName] = [
                "value" => $node["value"]["value"],
                "scores" => $node["value"]["scores"]
            ];
        }

        // If this is a leaf node, save the current path with edit count and cumulative confidence.
        if (empty($node["children"])) {
            // Use cumulative_weighted_confidence if available, else fallback to confidence
            $cumulativeConfidence = array_sum(array_map(function($entry) {
                return $entry['scores']['cumulative_weighted_confidence'] ?? $entry['scores']['confidence'];
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
            self::findAllProbablePaths($child, $tree, $allPaths, $currentPath, $newEditsCount, $level + 1, $minConfidenceThreshold);
        }

        // Backtrack to explore alternate paths.
        if ($attributeName) {
            unset($currentPath[$attributeName]);
        }
    }

    /**
     * Entry function to initiate path-finding, ranking paths by minimum edit count and then by cumulative weighted confidence
     *
     * @param $node
     * @param $targetLevel
     * @param $currentLevel
     * @param $minConfidenceThreshold
     * @return mixed|null
     */
    private static function getSelectedNodeAtLevel(&$node, $targetLevel, $currentLevel = 0, $minConfidenceThreshold = 0.001)
    {
        // Skip root node if it has "value" set to "root"
        if ($node["value"] === "root") {
            $selectedNode = null;
            foreach ($node["children"] as &$child) {
                $candidateNode = self::getSelectedNodeAtLevel($child, $targetLevel, $currentLevel, $minConfidenceThreshold);
                if ($candidateNode !== null &&
                    ($selectedNode === null || $candidateNode["value"]["scores"]["confidence"] > $selectedNode["value"]["scores"]["confidence"])) {
                    $selectedNode = $candidateNode;
                }
            }
            return $selectedNode;
        }

        // Base case: if the current level matches the target level, check this node's confidence
        if ($currentLevel === $targetLevel) {
            $confidence = $node["value"]["scores"]["confidence"] ?? 0;
            return ($confidence >= $minConfidenceThreshold) ? $node : null;
        }

        // Recursive case: traverse children to find the selected node at the target level
        $selectedNode = null;
        foreach ($node["children"] as &$child) {
            $candidateNode = self::getSelectedNodeAtLevel($child, $targetLevel, $currentLevel + 1, $minConfidenceThreshold);

            // Update selectedNode if candidateNode has higher confidence and meets threshold
            if ($candidateNode !== null &&
                ($selectedNode === null || $candidateNode["value"]["scores"]["confidence"] > $selectedNode["value"]["scores"]["confidence"])) {
                $selectedNode = $candidateNode;
            }
        }

        return $selectedNode;
    }

    /**
     * Entry function to initiate path-finding, ranking paths by minimum edit count and then by cumulative weighted confidence
     *
     * @param $treeData
     * @param $attributeNames
     * @param $minConfidenceThreshold
     * @return array
     */
    public static function getAllPaths($treeData, $attributeNames, $minConfidenceThreshold = 0.001)
    {
        $allPaths = [];

        // Start traversal from the root node to gather all probable paths
        self::findAllProbablePaths($treeData, $treeData, $allPaths, [], 0, 0, $minConfidenceThreshold);

        // Ensure all attributes are included in each path (default to empty if missing)
        foreach ($allPaths as &$pathInfo) {
            foreach ($attributeNames as $attribute) {
                if (!isset($pathInfo["path"][$attribute])) {
                    $pathInfo["path"][$attribute] = [
                        "value" => "",
                        "scores" => [
                            "confidence" => 0,
                            "cumulative_weighted_confidence" => 0,
                            "selected_node_value" => null
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
     * @param $treeData
     * @param $attributeNames
     * @param $minConfidence
     * @return array
     */
    public static function getBestPath($treeData, $attributeNames, $minConfidence = 0.1)
    {
        $minEditsPath = [];
        $minEditsCount = PHP_INT_MAX; // Start with a high value to minimize
        $currentPath = [];

        // Start traversal from the root node with minimum edits count
        self::findMostProbablePath($treeData, $treeData, $minEditsPath, $minEditsCount, $currentPath, 0, 0, $minConfidence);

        // Ensure all attributes are included in the final path (default to empty if missing)
        foreach ($attributeNames as $attribute) {
            if (!isset($minEditsPath[$attribute])) {
                $minEditsPath[$attribute] = [
                    "value" => "",
                    "scores" => [
                        "confidence" => 0,
                        "penalty" => 0 // Optionally add penalty if relevant for missing attributes
                    ]
                ];
            }
        }

        return $minEditsPath;
    }

    /**
     * @param $treeData
     * @param $attributeNames
     * @return array
     */
    private static function getBestPath_($treeData, $attributeNames)
    {
        $maxPath = [];
        $maxCumulativeContent = 0;

        // Start traversal from the root node
        self::findMostProbablePath_($treeData, $maxPath, $maxCumulativeContent);

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
     * @param $min_confidence
     * @return mixed|null
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

        // We add "scores" to tree data
        $tree_with_confidence_scores = AttributeGraphBuilder::addConfidenceScoresToTree($attribute_tree, $nodePathConfidences);

        // Calculate tree information content
        self::computeCumulativeConfidenceScores($tree_with_confidence_scores);

        //print("Tree data for $searchItem is: ".json_encode($tree_with_confidence_scores));

        // We get the best path - this is the matching attributes for the search item
        //return self::getBestPath($tree_with_confidence_scores, $builder->get_hierarchy_order());
        $allProbablePaths = self::getAllPaths($tree_with_confidence_scores, $builder->get_hierarchy_order(), $min_confidence);

        print("All probable paths for $searchItem are: ".json_encode(array_slice($allProbablePaths, 0,10)));

        return !empty($allProbablePaths) ? $allProbablePaths[0]['path'] : null;
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
