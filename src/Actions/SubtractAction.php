<?php

namespace PhelixJuma\GUIFlow\Actions;

use PhelixJuma\GUIFlow\Utils\PathResolver;

class SubtractAction implements ActionInterface
{
    private $path;
    private $value;
    private $valueFromField;
    private $newField;   // the new field where we want to store the result
    private $targetPath; // the path where the new value should be set

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
        $valueToAddWith = $this->value;
        if ($this->valueFromField !== null) {
            $valueToAddWith = PathResolver::getValueByPath($data, $this->valueFromField);
        }

        // Multiply and update.
        if (is_array($currentValues)) {
            foreach ($currentValues as $index => $currentValue) {
                $value = is_array($valueToAddWith) ? $valueToAddWith[$index] : $valueToAddWith;
                $newValue = $currentValue - $value;
                $targetPath = str_replace('*', $index, $this->targetPath);
                PathResolver::setValueByPath($data, $targetPath, $newValue);
            }
        } else {
            // This is for non-wildcard paths.
            $newValue = $currentValues - $valueToAddWith;
            PathResolver::setValueByPath($data, $this->targetPath, $newValue);
        }
    }
}
