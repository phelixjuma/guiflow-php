<?php

namespace PhelixJuma\GUIFlow\Utils\AttributeSearch;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;
use PhelixJuma\GUIFlow\Utils\Utils;

/**
 * Class AttributeGraphBuilder
 *
 * Builds a hierarchical graph from product data.
 */
class AttributeGraphBuilder
{
    private $graph;
    private $hierarchy_order;
    private $attributes_map;

    /**
     * @param array $attributes
     */
    public function __construct(array $attributes)
    {

        // Sort attributes by 'order'
        usort($attributes, function($a, $b) {
            return $a['order'] - $b['order'];
        });

        // Extract sorted attribute names for hierarchy
        $hierarchy_order = array_map(function($attr) {
            return $attr['name'];
        }, $attributes);

        // Create a map for attribute definitions for easy access
        $attributes_map = [];
        foreach ($attributes as $attr) {
            $attributes_map[$attr['name']] = $attr;
        }

        $this->graph = new Graph();
        $this->hierarchy_order = $hierarchy_order;
        $this->attributes_map = $attributes_map;
    }


    /**
     * Build a nested tree from the corpus based on the hierarchical order.
     *
     * @param array $corpus The list of product entries.
     * @return array The nested tree structure.
     */
    public function build_nested_tree(array $corpus): array
    {
        $tree = [];
        $this->build_tree_recursive($corpus, 0, $tree);

        // We add root tree, if not there.
        if (Utils::isList($tree)) {
            $tree = [
                "value" => "root",
                "children" => $tree
            ];
        }

        return $tree;
    }

    private function build_tree_recursive(array $corpus, int $level, array &$current_branch): void
    {
        if ($level >= count($this->hierarchy_order)) {
            return;
        }

        $current_attribute = $this->hierarchy_order[$level];

        // Group the corpus by the current attribute, including empty values
        $grouped = [];
        foreach ($corpus as $item) {
            $value = trim($item[$current_attribute] ?? '');
            $grouped[$value][] = $item;
        }

        // Iterate through each group and build the tree
        foreach ($grouped as $value => $items) {
            $node = [
                'value' => [
                    'value' => $value,
                    'attribute' => $this->attributes_map[$current_attribute]
                ],
                'children' => []
            ];

            // Recursively build the children for this node
            $this->build_tree_recursive($items, $level + 1, $node['children']);

            // Append the node to the current branch
            $current_branch[] = $node;
        }
    }

    /**
     * @param array $queue
     * @param array $output
     * @return array
     */
    private static function levelOrderTraversal(array $queue, array $output = [])
    {
        // If the queue is empty, return the output.
        if (count($queue) === 0) {
            return $output;
        }

        // Take the first item from the queue and visit it.
        $node = array_shift($queue);
        $output[] = $node['value'];

        // Add any children to the queue.
        foreach ($node['children'] ?? [] as $child) {
            $queue[] = $child;
        }

        // Repeat the algorithm with the rest of the queue.
        return self::levelOrderTraversal($queue, $output);
    }

    /**
     * @param $treeData
     * @return array
     */
    public static function extractTreeNodesAndBranchingOptions($treeData) {

        $queue = [$treeData];

        $allNodesData = self::levelOrderTraversal($queue);

        $combinedAttributes = [];

        foreach ($allNodesData as $node) {
            if ($node !== "root") {

                if (!array_key_exists($node['attribute']['name'], $combinedAttributes)) {
                    $combinedAttributes[$node['attribute']['name']] = [
                        "attribute"    => $node['attribute']['name'],
                        "options"       => []
                    ];
                }
                if (!empty($node['value']) && !in_array($node['value'], $combinedAttributes[$node['attribute']['name']]['options'])) {
                    $combinedAttributes[$node['attribute']['name']]['options'][] = $node['value'];
                }
            }
        }
        return array_values($combinedAttributes);
    }

    /**
     * @param $nodeValue
     * @param $treeWeights
     * @return array|mixed
     */
    public static function addNodeWeight($nodeValue, $treeWeights) {

        if ($nodeValue == "root") {
            return $nodeValue;
        }

        if (empty($nodeValue['value'])) {

            $nodeValue['scores']['confidence'] = 0;

            return $nodeValue;
        }

        // We get the weights for this node's label type (attribute)
        $attributeWeights = Utils::searchMultiArrayByKeyReturnKeys($treeWeights, "label_type", $nodeValue['attribute']['name']);

        $labelScore = Utils::searchMultiArrayByKeyReturnKeys($attributeWeights['classification'], "label", $nodeValue['value']);

        $nodeValue['scores']['confidence'] = $labelScore['confidence'] ?? 0;

        return $nodeValue;
    }

    /**
     * @param array $node
     * @param $nodePathConfidences
     * @return array
     */
    public static function addConfidenceScoresToTree(array $node, $nodePathConfidences) {

        // Apply the weighting function to the current node's value.
        $modifiedNode = [
            'value' => self::addNodeWeight($node['value'], $nodePathConfidences),
            'children' => []
        ];

        // Recursively process each child if children exist.
        if (!empty($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                $modifiedNode['children'][] = self::addConfidenceScoresToTree($child, $nodePathConfidences);
            }
        }

        return $modifiedNode;

    }


    /**
     * Build the graph from the nested tree.
     *
     * @param array $nested_tree The nested tree structure.
     * @return void
     */
    public function build_graph_from_tree(array $nested_tree): void
    {
        $this->add_nodes_edges($nested_tree, null, '');
    }

    /**
     * Recursively adds nodes and edges to the graph from the nested tree.
     *
     * @param array $node The current node in the tree.
     * @param Vertex|null $parent_vertex The parent vertex in the graph.
     * @param string $path The traversal path to ensure unique vertex IDs.
     * @return void
     */
    private function add_nodes_edges(array $node, ?Vertex $parent_vertex, string $path): void
    {
        $value = $node['value']['value'] ?? "root";
        $attribute = $node['value']['attribute'] ?? "root";
        $attribute_name = $attribute['name'] ?? "root";

        // Update the path for the current node
        $current_path = $path === '' ? "{$attribute_name}:{$value}" : "{$path}|{$attribute_name}:{$value}";

        // Create a unique vertex_id using the current path
        $vertex_id = $current_path;

        // Create or retrieve the vertex
        if (!$this->graph->hasVertex($vertex_id)) {
            $vertex = $this->graph->createVertex($vertex_id, true);
            $vertex->setAttribute('attribute_name', $attribute_name);
            $vertex->setAttribute('attribute_value', $value);
            $vertex->setAttribute('description', $attribute['description'] ?? '');
            $vertex->setAttribute('type', $attribute['type'] ?? '');
            $vertex->setAttribute('category', $attribute['category'] ?? '');
            $vertex->setAttribute('order', $attribute['order'] ?? 0);
        } else {
            $vertex = $this->graph->getVertex($vertex_id);
        }

        // If there is a parent, create a directed edge
        if ($parent_vertex) {
            if (!$parent_vertex->hasEdgeTo($vertex)) {
                $parent_vertex->createEdgeTo($vertex);
            }
        }

        // Recursively add children
        if (!empty($node['children'])) {
            foreach ($node['children'] as $child_node) {
                $this->add_nodes_edges($child_node, $vertex, $current_path);
            }
        }
    }

    /**
     * Get the graph object.
     *
     * @return Graph
     */
    public function get_graph(): Graph
    {
        return $this->graph;
    }

    /**
     * @return array
     */
    public function get_hierarchy_order() {
        return $this->hierarchy_order;
    }

    /**
     * Display the graph in ASCII format.
     *
     * @return void
     */
    public function display_graph_ascii(): void
    {
        // Find root vertices (vertices with no incoming edges)
        $root_vertices = [];
        foreach ($this->graph->getVertices() as $vertex) {
            if ($vertex->getEdgesIn()->count() === 0) {
                $root_vertices[] = $vertex;
            }
        }

        // Define a recursive function to print each vertex
        $print_vertex = function($vertex, $prefix = '', $isLast = true) use (&$print_vertex) {
            // Print the current vertex with appropriate prefix
            echo $prefix;
            echo $isLast ? '└── ' : '├── ';
            echo strtolower($vertex->getAttribute('attribute_name')) . ': ' . $vertex->getAttribute('attribute_value') . "\n";

            // Get all outgoing edges (children)
            $children = $vertex->getEdgesOut()->getEdges();
            $child_count = count($children);

            foreach ($children as $index => $edge) {
                $child_vertex = $edge->getVertexEnd();
                $is_last_child = ($index === $child_count - 1);
                // Determine the new prefix for child vertices
                $new_prefix = $prefix . ($isLast ? '    ' : '│   ');
                // Recursive call to print the child vertex
                $print_vertex($child_vertex, $new_prefix, $is_last_child);
            }
        };

        // Traverse and print each root vertex
        print "\n";
        foreach ($root_vertices as $index => $root) {
            $is_last_root = ($index === count($root_vertices) - 1);
            $print_vertex($root, '', $is_last_root);
        }
    }
}
