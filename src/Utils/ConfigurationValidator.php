<?php

namespace PhelixJuma\GUIFlow\Utils;

namespace PhelixJuma\GUIFlow\Utils;

use Exception;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;

class ConfigurationValidator
{

    /**
     * @param $data
     * @param $schemaVersion
     * @return array|true
     */
    public static function getValidationErrors($data, $schemaVersion='v3'): bool|array
    {

        $schema = self::getSchema($schemaVersion);

        $validator = new Validator();

        $result = $validator->validate($data, $schema);

        if ($result->isValid()) {
            return true;
        } else {
            return ((new ErrorFormatter())->format($result->error()));
        }
    }

    public static function validateGeneralSchema($data, $schema): bool
    {

        $validator = new Validator();

        $result = $validator->validate($data, $schema);

        if ($result->isValid()) {
            return true;
        } else {

            $errors = ((new ErrorFormatter())->format($result->error()));

            throw new \InvalidArgumentException(json_encode($errors));
        }
    }

    public static function validate($data, $schemaVersion = null): bool
    {

        $schema = self::getSchema($schemaVersion);

        return self::validateGeneralSchema($data, $schema);
    }

    /**
     * @param $v
     * @return string
     */
    public static function getSchemaPath($v = 'v3'): string
    {

        if (!empty($v)) {
            $extension = ".$v.json";
        } else {
            $extension = ".json";
        }
        return dirname(__DIR__) . DIRECTORY_SEPARATOR . "SchemaDefinitions/config-schema{$extension}";
    }

    public static function getSchema($v = 'v3') {

        $schemaPath = self::getSchemaPath($v);

        return json_decode(file_get_contents($schemaPath));
    }

    /**
     * @param $v
     * @return array
     * @throws Exception
     */
    public static function getSchemaComponents($v = 'v3') {

        // We get the schema
        $schemaPath = self::getSchemaPath($v);
        $schema = json_decode(file_get_contents($schemaPath), JSON_FORCE_OBJECT);

        // We resolve the schema
        $schema = JsonSchemaResolver::resolveSchema($schema);

        // We get all definitions
        $definitions = $schema['definitions'] ?? [];

        $components = [];

        if (!empty($definitions)) {

            foreach ($definitions as $definitionName => $definition) {

                if (str_starts_with($definitionName, 'Action_') || str_starts_with($definitionName, 'Rule_')) {
                    $parts = explode("_", $definitionName);
                    $type = $parts[0];
                    $subType = $parts[1] ?? $type;
                    $name = $parts[2] ?? $subType;

                    // If the definition is not fully resolved, as is the case with cyclic definitions, we add the definition
                    $unresolvedDefinitions = [];
                    $refs = [];
                    JsonSchemaResolver::traverseSchema($definition, '', $refs);

                    if (!empty($refs)) {
                        foreach ($refs as $ref) {
                            if (!in_array($ref['value'], $unresolvedDefinitions)) {
                                $unresolvedDefinitions[] = $ref['value'];
                                // We add the definition
                                $keyParts = explode("/", $ref['value']);
                                $key = $keyParts[sizeof($keyParts) - 1];
                                $definition['definitions'][$key] = $schema['definitions'][$key];
                            }
                        }
                    }

                    $components[$definitionName] = [
                        'type'      => $type,
                        'sub_type'  => $subType,
                        'name'      => $name,
                        'title'     => $definition['title'] ?? $name,
                        'schema'    => $definition
                    ];
                }
            }
        }

        return $components;
    }
}
