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

        foreach ($mapping as $key => $path) {
            if ($inverted) {
                $value = PathResolver::getValueByPath($data, $key);
                PathResolver::setValueByPath($result, $path, $value);
            } else {
                $value = PathResolver::getValueByPath($data, $path);
                PathResolver::setValueByPath($result, $key, $value);
            }
        }
        return $result;
    }
}
