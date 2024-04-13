<?php

namespace PhelixJuma\GUIFlow\Utils;

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

        foreach ($mapping as $key => $value) {

            if (is_array($value) && isset($value['from_path']) && $value['to_path']) {
                $originPath = $value['from_path'];
                $targetPath = $value['to_path'];
            } else {
                $targetPath = $key;
                $originPath = $value;
            }

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
