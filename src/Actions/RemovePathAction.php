<?php

namespace PhelixJuma\DataTransformer\Actions;

use PhelixJuma\DataTransformer\Exceptions\MissingValueMappingException;
use PhelixJuma\DataTransformer\Utils\PathResolver;

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
