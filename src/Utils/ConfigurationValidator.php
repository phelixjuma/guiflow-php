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

        $schema = json_decode(json_encode($schema));
        $data = json_decode(json_encode($data));

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
    public static function getSchemaComponents($v = 'v3', $resolve = false) {

        // We get the schema
        $schemaPath = self::getSchemaPath($v);
        $schema = json_decode(file_get_contents($schemaPath), JSON_FORCE_OBJECT);

        // We resolve the schema
        if ($resolve) {
            $schema = JsonSchemaResolver::resolveSchema($schema);
        }

        // We get all definitions
        $definitions = $schema['definitions'] ?? [];

        $components = [];

        if (!empty($definitions)) {

            foreach ($definitions as $definitionName => $definition) {

                if (str_starts_with($definitionName, 'Action_') || str_starts_with($definitionName, 'Rule_')) {
                    $parts = explode("_", $definitionName);
                    $type = $parts[0];
                    $subType = $parts[1] ?? $type;

                    // If the definition is not fully resolved, as is the case with cyclic definitions, we add the definition
                    $unresolvedDefinitions = [];
                    $refs = [];
                    JsonSchemaResolver::traverseSchema($definition, '', $refs);

                    // Keep processing until no more refs remain
                    while (!empty($refs)) {
                        // Take one reference off the list
                        $ref = array_pop($refs);

                        // Make sure we haven't already handled this reference
                        if (!in_array($ref['value'], $unresolvedDefinitions, true)) {
                            $unresolvedDefinitions[] = $ref['value'];

                            // Derive the local definition key from $ref['value'], e.g. #/definitions/SomeType
                            $keyParts = explode('/', $ref['value']);
                            $key      = end($keyParts);

                            // Add the referenced definition into $definition
                            // (assuming $schema['definitions'][$key] exists)
                            $definition['definitions'][$key] = $schema['definitions'][$key];

                            // Now, the newly added definition might have more references of its own
                            // so let's traverse it and add them into the queue
                            $newRefs = [];
                            JsonSchemaResolver::traverseSchema($definition['definitions'][$key], '', $newRefs);

                            // Add any newly found references onto the stack so they get processed too
                            foreach ($newRefs as $newRef) {
                                // Only push them if they're not already known
                                if (!in_array($newRef['value'], $unresolvedDefinitions, true)) {
                                    $refs[] = $newRef;
                                }
                            }
                        }
                    }

                    $components[] = [
                        'name'          => $definitionName,
                        'type'          => $type,
                        'group'         => $subType,
                        'title'         => $definition['title'] ?? ($parts[2] ?? $subType),
                        'description'   => $definition['description'] ?? '',
                        'schema'        => $definition
                    ];
                }
            }
        }

        return $components;
    }

    public static function generateSchemaComponents($version = 'v3')
    {

        $components = ConfigurationValidator::getSchemaComponents($version);

        // Save all refs to a JSON file
        file_put_contents(dirname(__DIR__,2).DIRECTORY_SEPARATOR."src/SchemaDefinitions/schema-components.$version.json", json_encode($components));
    }
}
