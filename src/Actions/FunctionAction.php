<?php

namespace PhelixJuma\DataTransformer\Actions;

use JumaPhelix\DAG\DAG;
use JumaPhelix\DAG\SharedDataManager;
use JumaPhelix\DAG\Task;
use JumaPhelix\DAG\TaskExecutor;
use  Swoole\Coroutine;
use PhelixJuma\DataTransformer\Exceptions\UnknownOperatorException;
use PhelixJuma\DataTransformer\Utils\DataJoiner;
use PhelixJuma\DataTransformer\Utils\DataReducer;
use PhelixJuma\DataTransformer\Utils\Filter;
use PhelixJuma\DataTransformer\Utils\ModelMapper;
use PhelixJuma\DataTransformer\Utils\Randomiser;
use PhelixJuma\DataTransformer\Utils\TemplateParserService;
use PhelixJuma\DataTransformer\Utils\UnitConverter;
use PhelixJuma\DataTransformer\Utils\Utils;
use PhelixJuma\DataTransformer\Utils\PathResolver;

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
        'preg_match', 'preg_replace',
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
     * @param array $args
     * @param $newField
     * @param $strict
     * @param $condition
     */
    public function __construct(string $path, $function, array|null $args, $newField = null, $strict = 0, $condition=null)
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
     */
    public function execute(&$data)
    {

        // Get all values from the path.
        $currentValues = !empty($this->path) ? PathResolver::getValueByPath($data, $this->path) : $data;

        // Prepare function parameters:  We set the current values data as the first param
        $paramValues = [$currentValues];

        if (!empty($this->args) && !is_string($this->args)) {
            foreach ($this->args as $param) {

                if ($this->function['1'] != 'join' && $this->function['1'] != 'map') {

                    if (is_array($param) && isset($param['path'])) {

                        $paramPath = $param['path'];
                        $paramValue = PathResolver::getValueByPath($data, $paramPath);
                        $paramValues[] = $paramValue;

                    } else {
                        $paramValues[] = self::getFilterCriteria($data, $param);
                    }
                } else {
                    $paramValues[] = $param;
                }
            }
        }

        if (!empty($this->condition)) {
            $paramValues[] = $this->condition;
        }

        if (isset($this->function[1]) && $this->function['1'] == 'filter') {
            $newValue = Filter::filterArray(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'split') {
            $newValue = Filter::splitByPath(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'merge') {
            $newValue = Utils::join(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'map') {

            list($currentData, $path, $function, $args, $newField, $strict, $condition) = $paramValues;

            if (method_exists($this->function[0], $function) ) {
                $function = [$this->function[0], $function];
            } else {
                $function = [$this, $function];
            }

            //Swoole\Runtime::enableCoroutine();

//            array_walk($currentData, function (&$value, $key) use($path, $function, $args, $newField, $strict, $condition) {
//                (new FunctionAction($path, $function, $args, $newField, $strict, $condition))->execute($value);
//            });

//            Coroutine\run(function() use(&$currentData, $path, $function, $args, $newField, $strict, $condition) {
//                array_walk($currentData, function (&$value, $key) use($path, $function, $args, $newField, $strict, $condition) {
//                    Coroutine\go(function () use($path, $function, $args, $newField, $strict, $condition, &$value) {
//                        (new FunctionAction($path, $function, $args, $newField, $strict, $condition))->execute($value);
//                    });
//                });
//            });

            $parallelizer = new DAG();
            $dataManager = new SharedDataManager($currentData);

            $size = sizeof($currentData);

            for ($index = 0; $index < $size; $index++) {

                $task = new Task($index, function() use ($index, $dataManager, $path, $function, $args, $newField, $strict, $condition) {

                    $dataToUse = &$dataManager->getData();

                    (new FunctionAction($path, $function, $args, $newField, $strict, $condition))->execute($dataToUse[$index]);

                    $dataManager->modifyData(function() use(&$dataToUse) {
                        return $dataToUse;
                    });
                    return $dataToUse;
                });
                $parallelizer->addTask($task);
            }
            (new TaskExecutor($parallelizer))->execute();

            $newValue = $currentData;

        } elseif (isset($this->function[1]) && $this->function['1'] == 'set') {

            list($currentData, $path, $value, $valueFromField, $valueMapping, $conditionalValue, $newField) = $paramValues;

            (new SetValueAction($path, $value, $valueFromField, $valueMapping, $conditionalValue, $newField))->execute($currentData);

            $newValue = $currentData;

        } elseif (isset($this->function[1]) && $this->function['1'] == 'join') {
            $newValue = (new DataJoiner(...$paramValues))->mergeData();
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
        } elseif (isset($this->function[1]) && $this->function['1'] == 'regex_extract') {
            $newValue = Utils::regex_extract(...$paramValues);
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
        } elseif (isset($this->function[1]) && $this->function['1'] == 'remove_repeated_words') {
            $newValue = Utils::remove_repeated_words(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'assoc_array_sum_if') {
            $newValue = Utils::assoc_array_sum_if(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'assoc_array_set_if') {
            $newValue = Utils::assoc_array_set_if(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'assoc_array_find') {
            $newValue = Utils::assoc_array_find(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'get_from_object') {
            $newValue = Utils::get_from_object(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'flatten_objects') {
            $newValue = Utils::flattenObject(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'expand_lists') {
            $newValue = Utils::expandList(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'flatten_and_expand') {
            $newValue = Utils::flattenAndExpand(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'rename_object_keys') {
            $newValue = Utils::rename_object_keys(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'date_add_substract_days') {
            $newValue = Utils::date_add_substract_days(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'date_format') {
            $newValue = Utils::date_format(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'date_diff') {
            $newValue = Utils::date_diff(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'convert_unit') {
            $newValue = UnitConverter::convert(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'convert_unit_multi') {
            $newValue = UnitConverter::convert_multiple(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'get_metric_conversion_table') {
            $newValue = UnitConverter::get_metric_conversion_table();
        } elseif (isset($this->function[1]) && $this->function['1'] == 'model_mapping') {
            $newValue = ModelMapper::transform(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'make_object_list_unique') {
            $newValue = Utils::make_object_list_unique(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'duplicate_list_item') {
            $newValue = Utils::duplicate_list_item(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'get_random_string') {
            // removes data from param values
            array_shift($paramValues);
            $newValue = Randomiser::getRandomString(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'basic_arithmetic') {
            $newValue = Utils::basic_arithmetic(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'length') {
            $newValue = Utils::length($paramValues[0]);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'parse_template') {
            $newValue = TemplateParserService::parseMessageFromTemplate(...$paramValues);
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
}
