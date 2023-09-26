<?php

namespace PhelixJuma\DataTransformer\Utils;

class ModelMapper {

    /**
     * Transforms the data based on the provided mapping.
     *
     * @param array $data The data to be transformed.
     * @param array $mapping The mapping to be used for transformation.
     * @param bool $inverted Indicates if the mapping should be inverted.
     * @return array
     */
    public static function transform(array $data, array $mapping, bool $inverted = false): array
    {

        $result = [];

        foreach ($mapping as $targetPath => $originPath) {
            if ($inverted) {
                $value = PathResolver::getValueByPath($data, $targetPath);
                PathResolver::setValueByPath($result, $originPath, $value);
            } else {
                $value = PathResolver::getValueByPath($data, $originPath);
                PathResolver::setValueByPath($result, $targetPath, $value);
            }
        }
        return $result;
    }
}
