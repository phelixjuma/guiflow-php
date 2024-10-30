<?php

namespace PhelixJuma\GUIFlow\Utils\AttributeSearch;

use Fhaculty\Graph\Graph;
use Fhaculty\Graph\Vertex;

/**
 * Class Search
 *
 * Handles the extraction of entities from product names using a graph-based approach.
 */
class Search
{

    /**
     * @param AttributeGraphBuilder $builder
     * @param string $search_phrase
     * @param callable $search_function
     * @return array
     */
    public static function extract_entities(AttributeGraphBuilder $builder, string $search_phrase, callable $search_function): array
    {

        $graph = $builder->get_graph();
        $hierarchy_order = $builder->get_hierarchy_order();

            // Initialize extracted entities with default values
        $extracted_entities = [];
        foreach ($hierarchy_order as $attribute) {
            $extracted_entities[$attribute] = [
                'value' => '',
                'confidence' => 0
            ];
        }

        $best_paths = []; // To store all paths with the highest matched length
        $max_matched_length = -1;
        $best_matched_levels = []; // To store levels at which matches occur in the best paths

        // Get root vertices (vertices with no incoming edges)
        $root_vertices = [];
        foreach ($graph->getVertices() as $vertex) {
            if ($vertex->getEdgesIn()->count() === 0) {
                $root_vertices[] = $vertex;
            }
        }

        // Initialize queue for traversal
        $queue = new \SplQueue();

        // Enqueue each root vertex separately to maintain separate paths
        foreach ($root_vertices as $root_vertex) {
            $queue->enqueue([
                'vertex' => $root_vertex,
                'path' => [$root_vertex],
                'matched_length' => 0,
                'matched_levels' => [],
                'level' => 0,
            ]);
        }

        while (!$queue->isEmpty()) {
            $current = $queue->dequeue();

            /** @var Vertex $vertex */
            $vertex = $current['vertex'];
            $path = $current['path'];
            $matched_length = $current['matched_length'];
            $matched_levels = $current['matched_levels'];
            $level = $current['level'];

            // Get the children of the current vertex
            $children = [];
            foreach ($vertex->getEdgesOut() as $edge) {
                $child_vertex = $edge->getVertexEnd();
                $children[] = $child_vertex;
            }

            if (!empty($children)) {
                // Collect values and associated vertices
                $values = [];
                $vertex_map = []; // Map value to vertices
                foreach ($children as $child_vertex) {
                    $value = $child_vertex->getAttribute('attribute_value');
                    $attribute_name = $child_vertex->getAttribute('attribute_name') ?? '';

                    // Get the branch path from the vertex ID
                    $vertex_id = $vertex->getId();
                    $id_parts = explode('|', $vertex_id);

                    $branch_path_parts = [];
                    foreach ($id_parts as $part) {
                        $branch_path_parts[] = str_replace(':', ': ', $part);
                    }
                    $new_branch_path = implode(' -> ', $branch_path_parts);

                    $valueData = [
                        'name' => $attribute_name,
                        'value' => $value,
                        'description' => $child_vertex->getAttribute('description') ?? "",
                        'type' => $child_vertex->getAttribute('type') ?? "",
                        'category' => $child_vertex->getAttribute('category') ?? "",
                        'level' => $level + 1,
                        'path'  => $new_branch_path
                    ];

                    // For passing to the search function, we can skip empty values
                    if (!empty($value) && $value !== 'root') {
                        $values[] = $valueData;
                        $vertex_map[$value][] = $child_vertex;
                    }
                }

                // Apply the search function to the array of values
                if (!empty($values)) {
                    $matched_value = $search_function($values, $search_phrase);
                } else {
                    $matched_value = null;
                }

                if ($matched_value !== null && !empty($matched_value)) {
                    // Match found in this path at this level
                    if (isset($vertex_map[$matched_value])) {
                        foreach ($vertex_map[$matched_value] as $matched_vertex) {
                            $new_matched_length = $matched_length + 1;
                            $new_matched_levels = array_merge($matched_levels, [$level + 1]);
                            $new_path = array_merge($path, [$matched_vertex]);

                            // Update best paths
                            if ($new_matched_length > $max_matched_length) {
                                // Found a better path
                                $max_matched_length = $new_matched_length;
                                $best_paths = [$new_path];
                                $best_matched_levels = [$new_matched_levels];
                            } elseif ($new_matched_length == $max_matched_length) {
                                // Tie, keep multiple best paths
                                $best_paths[] = $new_path;
                                $best_matched_levels[] = $new_matched_levels;
                            }

                            // Enqueue the matched child vertex for further traversal
                            $queue->enqueue([
                                'vertex' => $matched_vertex,
                                'path' => $new_path,
                                'matched_length' => $new_matched_length,
                                'matched_levels' => $new_matched_levels,
                                'level' => $level + 1,
                            ]);
                        }
                    }
                } else {
                    // No match found at this level in this path
                    // Prune paths that have fewer matches than the current maximum
                    if ($max_matched_length == -1 || $matched_length == $max_matched_length) {
                        // Enqueue all child vertices for further traversal
                        foreach ($children as $child_vertex) {
                            $queue->enqueue([
                                'vertex' => $child_vertex,
                                'path' => array_merge($path, [$child_vertex]),
                                'matched_length' => $matched_length, // Matched length remains the same
                                'matched_levels' => $matched_levels,
                                'level' => $level + 1,
                            ]);
                        }
                    } else {
                        //print "\nskipping this path of length $matched_length/$max_matched_length\n";
                        // Do not enqueue the children, effectively pruning this path
                        // Optionally, you can log or keep track of pruned paths here
                    }
                }
            }
        }

        // After traversal, select the best path using tie-breakers
        if (!empty($best_paths)) {
            // If there's only one best path, use it
            if (count($best_paths) == 1) {
                $best_path = $best_paths[0];
                $best_matched_levels = $best_matched_levels[0];
            } else {
                // Tie-breaker: prefer paths with matches at deeper levels
                $best_index = 0;
                $best_levels = $best_matched_levels[0];
                for ($i = 1; $i < count($best_paths); $i++) {
                    $current_levels = $best_matched_levels[$i];
                    $comparison = self::compare_levels($best_levels, $current_levels);
                    if ($comparison < 0) {
                        // Current path is better (matches at deeper levels)
                        $best_index = $i;
                        $best_levels = $current_levels;
                    }
                }
                $best_path = $best_paths[$best_index];
            }

            // Extract entities from the best path
            // Calculate total possible weight based on the matched length
            $total_possible_weight = array_sum(array_map(function($index) {
                return $index + 1;
            }, array_keys($best_path)));

            foreach ($best_path as $index => $vertex) {
                $attr = $vertex->getAttribute('attribute_name');
                $value = $vertex->getAttribute('attribute_value');
                $order = $index + 1;
                $confidence = $order / $total_possible_weight; // Normalized confidence

                if ($attr !== 'root') {
                    $extracted_entities[$attr] = [
                        'value' => ucwords(strtolower($value)),
                        'confidence' => round($confidence, 2)
                    ];
                }
            }
        }

        return $extracted_entities;
    }

    private static function compare_levels(array $levels1, array $levels2): int
    {
        // Compare the levels arrays position by position from the end
        // Since we want to favor matches at deeper levels, we start comparison from the last matched level
        $len1 = count($levels1);
        $len2 = count($levels2);
        $min_len = min($len1, $len2);

        for ($i = $min_len - 1; $i >= 0; $i--) {
            if ($levels1[$i] > $levels2[$i]) {
                return 1; // levels1 has match at a deeper level
            } elseif ($levels1[$i] < $levels2[$i]) {
                return -1; // levels2 is better
            }
        }

        // If all compared levels are equal, longer array is better (more matches)
        if ($len1 > $len2) {
            return 1; // levels1 is better
        } elseif ($len1 < $len2) {
            return -1; // levels2 is better
        } else {
            return 0; // Equal
        }
    }

    /**
     * @param string $searchItem
     * @param array $attributes
     * @param $attribute_tree
     * @param $corpus_with_attributes
     * @param callable $searchFunction
     * @return array
     */
    public static function search(string $searchItem, array $attributes, $attribute_tree, $corpus_with_attributes, callable $searchFunction): array
    {

        // Instantiate the AttributeGraphBuilder
        $builder = new AttributeGraphBuilder($attributes);

        // Build the nested tree from the corpus data, if no attribute data is given
        if (empty($attribute_tree)) {
            $attribute_tree = $builder->build_nested_tree($corpus_with_attributes);
        }

        //print(json_encode($attribute_tree));

        // Build the graph from the nested tree
        $builder->build_graph_from_tree($attribute_tree);

        // Optional: Display the graph in ASCII format for debugging
        //$builder->display_graph_ascii();

        // Extract entities using the function
        return self::extract_entities($builder, $searchItem, $searchFunction);
    }
}
