<?php

namespace PhelixJuma\GUIFlow\Actions;

use PhelixJuma\GUIFlow\Utils\PathResolver;

class MultiplyAction implements ActionInterface
{
    private $path;
    private $value;
    private $valueFromField;
    private $newField;
    private $targetPath;

    public function __construct(string $path, $value, $valueFromField = null, $newField = null)
    {
        $this->path = $path;
        $this->value = $value;
        $this->valueFromField = $valueFromField;
        $this->newField = $newField;
        $this->targetPath = $this->newField ?? $this->path;
    }

    public function execute(&$data)
    {
        // Get all values from the path.
        $currentValues = PathResolver::getValueByPath($data, $this->path);

        // Determine the value to multiply with.
        $valueToMultiplyWith = $this->value;
        if ($this->valueFromField !== null) {
            $valueToMultiplyWith = PathResolver::getValueByPath($data, $this->valueFromField);
        }

        // Multiply and update.
        if (is_array($currentValues)) {
            foreach ($currentValues as $index => $currentValue) {
                $value = is_array($valueToMultiplyWith) ? $valueToMultiplyWith[$index] : $valueToMultiplyWith;
                $newValue = $currentValue * $value;
                $targetPath = str_replace('*', $index, $this->targetPath);
                PathResolver::setValueByPath($data, $targetPath, $newValue);
            }
        } else {
            // This is for non-wildcard paths.
            $newValue = $currentValues * $valueToMultiplyWith;
            PathResolver::setValueByPath($data, $this->targetPath, $newValue);
        }
    }


}
