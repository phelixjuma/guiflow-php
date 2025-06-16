<?php

namespace PhelixJuma\GUIFlow\Actions;

use PhelixJuma\GUIFlow\Exceptions\MissingValueMappingException;
use PhelixJuma\GUIFlow\Utils\PathResolver;

class DeleteValueAction implements ActionInterface
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function execute(&$data)
    {
        // Get all values from the path.
        $currentValues = PathResolver::getValueByPath($data, $this->path);

        if (is_array($currentValues)) {
            foreach ($currentValues as $index => $currentValue) {
                PathResolver::setValueByPath($data, $this->path, null);
            }
        } else {
            // This is for non-wildcard paths.
            PathResolver::setValueByPath($data, $this->path, null);
        }
    }
}
