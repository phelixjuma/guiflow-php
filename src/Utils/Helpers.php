<?php

namespace PhelixJuma\GUIFlow\Utils;

use ArrayJoin\Builder;
use ArrayJoin\On;
use FuzzyWuzzy\Fuzz;
use FuzzyWuzzy\Process;
use PhelixJuma\GUIFlow\Actions\FunctionAction;
use PhelixJuma\GUIFlow\Workflow;
use PhelixJuma\GUIFlow\Exceptions\UnknownOperatorException;

class Helpers
{

    public static function isIndexedArray($array): bool
    {
        return !empty($array) && is_array($array) && array_keys($array) === range(0, count($array) - 1);
    }

    /**
     * @param $arrayOfObjects
     * @return array
     */
    public static function indexArrayOfObjects($arrayOfObjects) {

        $indexedArrays = [];

        if (is_array($arrayOfObjects) && sizeof($arrayOfObjects) > 0) {
            foreach ($arrayOfObjects as $object) {
                $indexedArrays[] = $object;
            }
        }
        return $indexedArrays;

    }

    /**
     * @param $string
     * @return bool
     */
    public static function isJson($string) {

        if (!is_string($string)) {
            return false;
        }

        json_decode($string);

        return (json_last_error() == JSON_ERROR_NONE);
    }
}
