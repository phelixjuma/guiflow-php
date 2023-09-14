<?php

namespace PhelixJuma\DataTransformer\Utils;

namespace PhelixJuma\DataTransformer\Utils;

use JsonSchema\Validator;
use JsonSchema\Constraints\Constraint;

class ConfigurationValidator
{
    public function validate($data)
    {

        $schema = self::getSchema();

        $validator = new Validator;
        $validator->validate($data, $schema, Constraint::CHECK_MODE_APPLY_DEFAULTS);

        if ($validator->isValid()) {
            return true;
        } else {
            $errors = [];
            foreach ($validator->getErrors() as $error) {
                $errors[] = sprintf("[%s] %s", $error['property'], $error['message']);
            }
            throw new \InvalidArgumentException(implode(', ', $errors));
        }
    }

    public static function getSchema() {
        return json_decode(file_get_contents(__DIR__ . "/config-schema.json"));
    }
}
