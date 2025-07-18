<?php

namespace PhelixJuma\GUIFlow\Actions;

use PhelixJuma\GUIFlow\Utils\AttributeSearch\TreeSearch;
use PhelixJuma\GUIFlow\Utils\DataSplitter;
use PhelixJuma\GUIFlow\Utils\DataValidator;
use PhelixJuma\GUIFlow\Utils\FuzzySearch;
use PhelixJuma\GUIFlow\Exceptions\UnknownOperatorException;
use PhelixJuma\GUIFlow\Utils\DataJoiner;
use PhelixJuma\GUIFlow\Utils\DataReducer;
use PhelixJuma\GUIFlow\Utils\Filter;
use PhelixJuma\GUIFlow\Utils\ModelMapper;
use PhelixJuma\GUIFlow\Utils\Randomiser;
use PhelixJuma\GUIFlow\Utils\TemplateParserService;
use PhelixJuma\GUIFlow\Utils\UnitConverter;
use PhelixJuma\GUIFlow\Utils\Utils;
use PhelixJuma\GUIFlow\Utils\PathResolver;

use PhelixJuma\GUIFlow\Workflow;



class FunctionAction implements ActionInterface
{
    const SUPPORTED_FUNCTIONS = [
        // php functions
        'strtolower', 'strtoupper', 'trim', 'ucwords','strlen', 'explode', 'implode', 'nl2br', 'number_format',
        'levenshtein', 'similar_text', 'soundex','str_contains', 'str_ireplace', 'str_replace', 'substr', 'strtr',
        'strtotime',
        'sort',
        'round', 'floor', 'ceil', 'abs', 'exp', 'max', 'min', 'pow', 'sqrt', 'array_sum','count', 'sizeof',
        'json_encode', 'json_decode',
        'intval', 'floatval',
        'preg_match', 'preg_replace', 'date_default_timezone_set',
        // custom functions
        'dictionary_mapper', 'regex_mapper', 'string_to_date_time'
    ];

    private $path;
    private $function;
    private $args;
    private $newField;
    private $strict;
    private $targetPath;
    private $condition;

    /**
     * @param string $path
     * @param $function
     * @param array|null $args
     * @param null $newField
     * @param int $strict
     * @param null $condition
     */
    public function __construct($path, $function, $args, $newField = null, $strict = 0, $condition=null)
    {
        $this->path = $path;
        $this->function = $function;
        $this->args = $args;
        $this->newField = $newField;
        $this->strict = $strict != 0;
        $this->condition = $condition;
        $this->targetPath = !empty($this->newField) ? $this->newField : $this->path;

    }

    /**
     * @param $data
     * @return void
     * @throws UnknownOperatorException
     * @throws \Exception
     */
    public function execute(&$data)
    {

        // Get all values from the path.
        if (empty($this->path)) {
            $currentValues = $data;
        } elseif(!str_contains($this->path, "|")) {
            $currentValues = PathResolver::getValueByPath($data, $this->path);
        } else {

            $paths = array_map('trim', explode('|', $this->path));

            $foundPath = false;

            $currentValues = null;

            foreach ($paths as $path) {
                $pathValue = PathResolver::getValueByPath($data, $path);
                if ($pathValue !== null) {
                    $currentValues = $pathValue;
                    $this->path = $path;
                    $foundPath = true;
                    break;
                }
            }
            if (!$foundPath) {
                // We pick the last path
                $this->path = $paths[sizeof($paths)-1];
            }
            $this->targetPath = !empty($this->newField) ? $this->newField : $this->path;
        }

        // Prepare function parameters:  We set the current values data as the first param
        $paramValues = [$currentValues];

        if (!empty($this->args) && !is_string($this->args)) {

            foreach ($this->args as $param) {

                if (!in_array($this->function['1'], ['map', 'map_parallel'])) {
                    $paramValues[] = self::resolveParam($data, $param);
                } else {
                    $paramValues[] = $param;
                }
            }
        }

        if (!empty($this->condition)) {
            $paramValues[] = $this->condition;
        }

        if (empty($this->condition) || in_array($this->function['1'], ["map_parallel", "map"])  || Workflow::evaluateCondition($currentValues, $this->condition)) {

            if (isset($this->function[1]) && $this->function['1'] == 'filter') {
                $newValue = Filter::filterArray(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'window_conditional_filter') {
                $newValue = Filter::window_conditional_filter(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'split') {
                $newValue = DataSplitter::split(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'merge') {
                $newValue = Utils::join(...$paramValues);
            } elseif (isset($this->function[1]) && ($this->function['1'] == 'map' || $this->function['1'] == 'map_parallel')) {

                if (empty($currentValues)) {
                    $newValue = $currentValues;
                } elseif (sizeof($currentValues) == 0) {
                    $newValue = $currentValues;
                } else {

                    list($path, $function, $args, $newField, $strict, $condition) = array_values($this->args);

                    // We resolve parent paths in args, if not nested map function
                    if (!in_array($function, ["map", "map_parallel"])) {
                        $args = self::resolveParentParamInMap($data, $args);
                    }

                    foreach ($currentValues as &$value) {

                        if (empty($this->condition) || Workflow::evaluateCondition($value, $this->condition)) {
                            try {
                                (new FunctionAction($path, [$this->function[0], $function], $args, $newField, $strict, $condition))->execute($value);
                            } catch (\Exception|\Throwable $e) {
                                print "\nError in map function: ".$e->getMessage()." on line {$e->getLine()} of file {$e->getFile()}: path: {$path} function {$function} args ".json_encode($args)." new_field: {$newField}\n";
                            }
                        }
                    }

                    $newValue = $currentValues;
                }

            }
            elseif (isset($this->function[1]) && $this->function['1'] == 'set') {

                list($currentData, $path, $value, $valueFromField, $valueMapping, $conditionalValue, $newField) = $paramValues;

                (new SetValueAction($path, $value, $valueFromField, $valueMapping, $conditionalValue, $newField))->execute($currentData);

                $newValue = $currentData;

            } elseif (isset($this->function[1]) && $this->function['1'] == 'join') {
                $newValue = (new DataJoiner(...$paramValues))->mergeData();
            } elseif (isset($this->function[1]) && $this->function['1'] == 'combine_lists') {
                $newValue = Utils::combineLists($paramValues[1]);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'reducer') {
                $newValue = (new DataReducer(...$paramValues))->reduce();
            } elseif (isset($this->function[1]) && $this->function['1'] == 'sort_multi_by_key') {
                $newValue = Utils::sortMultiAssocArrayByKey(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'format_date') {
                $newValue = Utils::format_date(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'fuzzy_extract_one') {
                $newValue = Utils::fuzzy_extract_one(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'fuzzy_extract_n') {
                $newValue = Utils::fuzzy_extract_n(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'fuzzy_search') {
                $newValue = (new FuzzySearch())->fuzzySearch(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'fuzzy_match') {
                $newValue = (new FuzzySearch())->fuzzyMatch(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'regex_extract') {
                $newValue = Utils::regex_extract(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'extract_unit') {
                $newValue = Utils::extract_unit(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'extract_packaging_details') {
                $newValue = Utils::extract_packaging_details(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'get_top_n_ranked_items_by_key') {
                $newValue = Utils::get_top_n_ranked_items_by_key(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'transform') {
                $newValue = Utils::transform_data(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'append') {
                $newValue = Utils::append(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'prepend') {
                $newValue = Utils::prepend(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'concat') {
                $newValue = Utils::concat(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'concat_multi_array_assoc') {
                $newValue = Utils::concat_multi_array_assoc(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'custom_preg_replace') {
                $newValue = Utils::custom_preg_replace(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'string_diff') {
                $newValue = Utils::string_diff(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'regex_mapper_multiple') {
                $newValue = Utils::regex_mapper_multiple(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'remove_repeated_words') {
                $newValue = Utils::remove_repeated_words(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'assoc_array_sum_if') {
                $newValue = Utils::assoc_array_sum_if(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'assoc_array_find') {
                // removes data from param values
                array_shift($paramValues);
                $newValue = Utils::assoc_array_find(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'get_object_from_list') {
                // removes data from param values
                array_shift($paramValues);
                $newValue = Utils::assoc_array_find(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'get_from_object') {
                $newValue = Utils::get_from_object(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'extract_attributes_from_corpus_fields') {
                $newValue = TreeSearch::getAttributesFromCorpusFields(...$paramValues);
            }  elseif (isset($this->function[1]) && $this->function['1'] == 'flatten_objects') {
                $newValue = Utils::flattenObject(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'expand_lists') {
                $newValue = Utils::expandList(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'flatten_and_expand') {
                $newValue = Utils::flattenAndExpand(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'collapse') {
                $newValue = Utils::collapseData(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'rename_object_keys') {
                $newValue = Utils::rename_object_keys(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'date_add_subtract_days') {
                $newValue = Utils::date_add_subtract_days(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'date_format') {
                $newValue = Utils::date_format(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'date_diff') {
                $newValue = Utils::date_diff(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'convert_unit') {
                $newValue = UnitConverter::convert(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'convert_unit_multi') {
                $newValue = UnitConverter::convert_multiple(...$paramValues);
            }  elseif (isset($this->function[1]) && $this->function['1'] == 'convert_units_v2') {
                $newValue = UnitConverter::convert_units_v2(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'round_to_multiple') {
                $newValue = Utils::roundToMultiple(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'get_metric_conversion_table') {
                $newValue = UnitConverter::get_metric_conversion_table();
            } elseif (isset($this->function[1]) && $this->function['1'] == 'model_mapping') {
                $newValue = ModelMapper::transform(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'make_object_list_unique') {
                $newValue = Utils::make_object_list_unique(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'make_list_unique') {
                $newValue = Utils::make_list_unique(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'duplicate_list_item') {
                $newValue = Utils::duplicate_list_item(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'replicate_list_item_with_replacement') {
                $newValue = Utils::replicate_list_item_with_replacement(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'regex_lookup_replace') {
                $newValue = Utils::regex_lookup_replace(...array_slice($paramValues, 1));
            } elseif (isset($this->function[1]) && $this->function['1'] == 'get_random_string') {
                // removes data from param values
                array_shift($paramValues);
                $newValue = Randomiser::getRandomString(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'basic_arithmetic') {
                $newValue = Utils::basic_arithmetic(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'absolute_value') {
                $newValue = Utils::absolute_value(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'length') {
                $newValue = Utils::length($paramValues[0]);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'parse_template') {
                $newValue = TemplateParserService::parseMessageFromTemplate(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'pattern_based_stem_spell_corrections') {
                $newValue = Utils::pattern_based_stem_spell_corrections(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'validate_and_correct_quantity_and_prices') {
                $newValue = DataValidator::validateAndCorrectQuantityUsingPrice(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'validate_data_structure') {
                $newValue = DataValidator::validateDataStructure(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'correct_date') {
                $newValue = Utils::correct_date(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'set_attribute_filters') {
                $newValue = Utils::setAttributeFilters(...$paramValues);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'user_defined_function') {
                // function name is at index 1 (index 0 is the data).
                $functionName = $paramValues[1];
                // We get the UDF's params which should exclude index 1 - function name).
                $functionParams = array_merge([$paramValues[0]], self::resolveParam($paramValues[0], array_slice($paramValues, 2)));

                // We call the user defined function
                $newValue = call_user_func_array([$this->function[0],$functionName], $functionParams);
            } elseif (isset($this->function[1]) && $this->function['1'] == 'system_defined_function') {
                // function name is at index 1 (index 0 is the data).
                $functionName = $paramValues[1];
                // We get the function params
                $functionParams = self::resolveParam($paramValues[0], $paramValues[2]) ?? [];
                // We call the user defined function
                $newValue = call_user_func_array([$this->function[0],$functionName], $functionParams);
            }
            elseif (isset($this->function[1]) &&  function_exists($this->function['1'])) {
                if (in_array($this->function['1'], self::SUPPORTED_FUNCTIONS)) {
                    $newValue = $this->function['1'](...$paramValues);
                } else {
                    $newValue = "";
                }
            } else {
                $newValue = call_user_func_array($this->function, $paramValues);
            }

            // If strict is set, we only set data when new value is not empty
            if (!$this->strict || (!empty($newValue))) {

                if (empty($this->targetPath)) {
                    // If target path is not set, it means the whole data is to be updated
                    $data = $newValue;
                } else {
                    // Otherwise, we only update the relevant parts
                    PathResolver::setValueByPath($data, $this->targetPath, $newValue);
                }
            }
        }
    }

    /**
     * Sometimes, a filter function criteria might have its search term as a path
     * In such cases, we need to get the actual search term from the data path
     *
     * @param $data
     * @param $criteria
     * @return mixed
     */
    private static function getFilterCriteria($data, $criteria): mixed
    {

        if (isset($criteria['term'])) {
            if (is_array($criteria['term']) && isset($criteria['term']['path'])) {
                $criteria['term'] = PathResolver::getValueByPath($data, $criteria['term']['path']);
            }
            return $criteria;
        }
        if (!empty($criteria['conditions'])) {
            foreach ($criteria['conditions'] as &$condition) {
                $condition = self::getFilterCriteria($data, $condition);
            }
        }
        return $criteria;
    }

    protected function resolveParam($data, $param) {
        if (is_array($param)) {
            // Check if the array contains only the 'path' key
            if (count($param) === 1 && isset($param['path'])) {
                $paramPath = $param['path'];
                return PathResolver::getValueByPath($data, $paramPath);
            } else {
                // Recursively resolve each element in the array
                $resolvedArray = [];
                foreach ($param as $key => $value) {
                    $resolvedArray[$key] = $this->resolveParam($data, $value);
                }
                return $resolvedArray;
            }
        }
        // If param is not an array, return it as-is
        return $param;
    }

    /**
     * @param $data
     * @param $param
     * @return array|mixed|null
     */
    protected function resolveParentParamInMap($data, $param) {

        if (is_array($param)) {
            if (isset($param['path']) && str_contains($param['path'], "parent.")) {
                $paramPath = str_replace("parent.", "",$param['path']);
                return PathResolver::getValueByPath($data, $paramPath);
            } else {
                // Recursively resolve each element in the array
                $resolvedArray = [];
                foreach ($param as $key => $value) {
                    $resolvedArray[$key] = $this->resolveParentParamInMap($data, $value);
                }
                return $resolvedArray;
            }
        }
        return $param;
    }
}
