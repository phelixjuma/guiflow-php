<?php

namespace PhelixJuma\DataTransformer\Actions;

use PhelixJuma\DataTransformer\Utils\PathResolver;

class SetValueAction implements ActionInterface
{
    private $path;
    private $value;
    private $valueFromField;
    private $valueMapping;
    private $targetPath;

    public function __construct($path, $value = null, $valueFromField = null, $valueMapping = null)
    {
        $this->path = $path;
        $this->value = $value;
        $this->valueFromField = $valueFromField;
        $this->valueMapping = $valueMapping;
        $this->targetPath = $this->path;
    }

    public function execute(&$data)
    {
        // Get all values from the path.
        $currentValues = PathResolver::getValueByPath($data, $this->path);

        // Determine the value to map with.
        $valueToMap = $this->value;
        if ($this->valueFromField !== null) {
            $valueToMap = PathResolver::getValueByPath($data, $this->valueFromField);
        }

        if (is_array($currentValues)) {
            foreach ($currentValues as $index => $currentValue) {

                $value = is_array($valueToMap) ? $valueToMap[$index] : $valueToMap;

                if (empty($this->valueMapping) || !isset($this->valueMapping[$value])) {
                    $newValue = $valueToMap;
                } else {
                    $newValue = $this->valueMapping[$value];
                }

                $targetPath = str_replace('*', $index, $this->targetPath);
                PathResolver::setValueByPath($data, $targetPath, $newValue);

            }
        } else {
            // This is for non-wildcard paths.

            if (empty($this->valueMapping) || !isset($this->valueMapping[$valueToMap])) {
                $newValue = $valueToMap;
            } else {
                $newValue = $this->valueMapping[$valueToMap];
            }
            PathResolver::setValueByPath($data, $this->targetPath, $newValue);
        }
    }
}
