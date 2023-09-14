<?php

namespace PhelixJuma\DataTransformer\Actions;

use PhelixJuma\DataTransformer\Exceptions\DivisionByZeroException;
use PhelixJuma\DataTransformer\Utils\PathResolver;

class DivideAction implements ActionInterface
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

    /**
     * @param $data
     * @return void
     * @throws DivisionByZeroException
     */
    public function execute(&$data)
    {
        // Get all values from the path.
        $currentValues = PathResolver::getValueByPath($data, $this->path);

        // Determine the value to multiply with.
        $valueToDivideWith = $this->value;
        if ($this->valueFromField !== null) {
            $valueToDivideWith = PathResolver::getValueByPath($data, $this->valueFromField);
        }

        // Multiply and update.
        if (is_array($currentValues)) {
            foreach ($currentValues as $index => $currentValue) {
                $value = is_array($valueToDivideWith) ? $valueToDivideWith[$index] : $valueToDivideWith;

                if ($value == 0) {
                    throw new DivisionByZeroException("Division by zero error");
                }

                $newValue = $currentValue / $value;
                $targetPath = str_replace('*', $index, $this->targetPath);
                PathResolver::setValueByPath($data, $targetPath, $newValue);
            }
        } else {
            // This is for non-wildcard paths.
            if ($valueToDivideWith == 0) {
                throw new DivisionByZeroException("Division by zero error");
            }
            $newValue = $currentValues / $valueToDivideWith;
            PathResolver::setValueByPath($data, $this->targetPath, $newValue);
        }
    }


}
