<?php

namespace PhelixJuma\GUIFlow\Utils;

namespace PhelixJuma\GUIFlow\Utils;


class Schema
{

    private static function extractSubschemas($schema, &$results, $path = "") {

        // Check if the current schema has definitions
        if (isset($schema->definitions)) {
            foreach ($schema->definitions as $key => $definition) {
                // Create a new path for the current definition
                $newPath = $path ? $path . '.' . $key : $key;
                // Recursively process each definition
                self::extractSubschemas($definition, $results, $newPath);
            }
        }

        // If the current schema is complex and contains properties or items, consider it a nested schema and do not save as standalone
        if (isset($schema->properties) || isset($schema->items)) {
            foreach ($schema->properties ?? [] as $propertyKey => $propertyValue) {
                // Recursively process each property
                self::extractSubschemas($propertyValue, $results, $path . '.' . $propertyKey);
            }
            if (isset($schema->items)) {
                // Recursively process items if it's an array of schemas
                if (is_array($schema->items)) {
                    foreach ($schema->items as $index => $item) {
                        self::extractSubschemas($item, $results, $path . '.items[' . $index . ']');
                    }
                } else {
                    self::extractSubschemas($schema->items, $results, $path . '.items');
                }
            }
        } else {
            // If it's a leaf node in the schema definition tree, save it as a standalone schema
            $results[$path] = $schema;
        }
    }

    /**
     * @param $version
     * @return array
     */
    public static function getGranularSchemas($version="v3") {

        $jsonSchema = ConfigurationValidator::getSchema($version);

        $results = [];

        self::extractSubschemas($jsonSchema, $results);

        return $results;
    }
}
