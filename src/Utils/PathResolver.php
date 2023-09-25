<?php

namespace PhelixJuma\DataTransformer\Utils;

class PathResolver
{

    public static function getValueByPath($data, string $path, $acceptListReturn = false)
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {

            if (is_numeric($part)) {
                $part = (int)$part;
            }

            if ($part === '*') {

                if (!is_array($current)) {
                    return null;
                }

                $values = [];
                $currentPosition = array_search($part, $parts);
                foreach ($current as $item) {
                    $values[] = self::getValueByPath($item, implode('.', array_slice($parts, $currentPosition + 1)));
                }

//                return $acceptListReturn ? $values : $values[0];
                return $values;
            }

            if (is_array($current)) {

                if (!isset($current[$part])) {
                    return null;
                }

                $current = $current[$part];
            } elseif (is_object($current)) {

                if (!isset($current->$part)) {
                    return null;
                }

                $current = $current->$part;
            } else {
                return null;
            }

        }
        return $current;
    }

    public static function setValueByPath(array &$data, string $path, $value)
    {
        $parts = explode('.', $path);
        $current = &$data;

        foreach ($parts as $key => $part) {

            if ($part === '*') {

                if ($key === count($parts) - 1) {

                    foreach ($current as $cKey => &$item) {
                        $item = is_array($value) ? $value[$cKey] : $value;
                    }
                } else {

                    $nextPath = implode('.', array_slice($parts, $key + 1));

                    foreach ($current as $cKey => &$item) {
                        self::setValueByPath($item, $nextPath, (is_array($value) ? $value[$cKey] : $value));
                    }
                }
                return;  // Exit after processing the wildcard
            } elseif (is_numeric($part)) {
                $part = (int)$part;
            }

            // If the part doesn't exist and it's not the last key, initialize it as an array
            if (!isset($current[$part]) && $key !== count($parts) - 1) {
                $current[$part] = [];
            }

            // Move the pointer
            $current = &$current[$part];
        }

        // At the end of the loop, set the value
        $current = $value;
    }

}
