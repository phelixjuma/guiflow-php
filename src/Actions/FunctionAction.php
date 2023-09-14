<?php

namespace PhelixJuma\DataTransformer\Actions;

use PhelixJuma\DataTransformer\Utils\Filter;
use PhelixJuma\DataTransformer\Utils\ModelMapper;
use PhelixJuma\DataTransformer\Utils\Utils;
use PhelixJuma\DataTransformer\Utils\PathResolver;

class FunctionAction implements ActionInterface
{
    private $path;
    private $function;
    private $args;
    private $newField;
    private $targetPath;

    /**
     * @param string $path
     * @param $function
     * @param array $args
     *
     */
    public function __construct(string $path, $function, array $args, $newField = null)
    {
        $this->path = $path;
        $this->function = $function;
        $this->args = $args;
        $this->newField = $newField;
        $this->targetPath = $this->newField ?? $this->path;
    }

    public function execute(&$data)
    {

        // Get all values from the path.
        $currentValues = !empty($this->path) ? PathResolver::getValueByPath($data, $this->path) : $data;

        // Prepare function parameters:  We set the current values data as the first param
        $paramValues = [$currentValues];

        foreach ($this->args as $param) {
            if (is_array($param) && isset($param['path'])) {

                $paramPath = $param['path'];
                $paramValue = PathResolver::getValueByPath($data, $paramPath);
                $paramValues[] = $paramValue;

            } else {

                $param = self::getFilterCriteria($data, $param);

                $paramValues[] = $param;
            }
        }

        if (isset($this->function[1]) && $this->function['1'] == 'filter') {
            $newValue = self::dataFilterFunction(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'sort_multi_by_key') {
            $newValue = Utils::sortMultiAssocArrayByKey(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'format_date') {
            $newValue = Utils::format_date(...$paramValues);
        } elseif (isset($this->function[1]) && $this->function['1'] == 'model_mapping') {
            $newValue = ModelMapper::transform(...$paramValues);
        } elseif (isset($this->function[1]) &&  function_exists($this->function['1'])) {
            $newValue = $this->function['1'](...$paramValues);
        } else {
            $newValue = call_user_func_array($this->function, $paramValues);
        }

        if (empty($this->targetPath)) {
            // If target path is not set, it means the whole data is to be updated
            $data = $newValue;
        } else {
            // Otherwise, we only update the relevant parts
            PathResolver::setValueByPath($data, $this->targetPath, $newValue);
        }
    }

    /**
     * @param $data
     * @param $filters
     * @return array
     */
    private static function dataFilterFunction($data, $filters): array
    {
        return Filter::filterArray($data, $filters);
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
            foreach ($criteria['conditions'] as $condition) {
                return self::getFilterCriteria($data, $condition);
            }
        }
        return $criteria;
    }
}
