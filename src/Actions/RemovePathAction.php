<?php

namespace PhelixJuma\GUIFlow\Actions;

use PhelixJuma\GUIFlow\Exceptions\MissingValueMappingException;
use PhelixJuma\GUIFlow\Utils\PathResolver;

class RemovePathAction implements ActionInterface
{
    private $path;

    public function __construct($path)
    {
        $this->path = $path;
    }

    public function execute(&$data)
    {
        PathResolver::removePath($data, $this->path);
    }
}
