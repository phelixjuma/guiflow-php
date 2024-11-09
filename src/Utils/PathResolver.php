<?php

namespace PhelixJuma\GUIFlow\Utils;

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

                // Ensure that the current item is an array
                if (!is_array($current)) {
                    $current = [];
                }

                // If $current is empty and $value is an array, initialize $current with the same structure
                if (empty($current) && is_array($value)) {
                    foreach ($value as $v) {
                        $current[] = [];
                    }
                }

                if ($key === count($parts) - 1) {
                    foreach ($current as $cKey => &$item) {
                        $item = is_array($value) ? $value[$cKey] : $value;
                    }
                } else {

                    $nextPath = implode('.', array_slice($parts, $key + 1));

                    foreach ($current as $cKey => &$item) {
                        // Ensure that the subsequent nested structures exist
                        if (!isset($item[$parts[$key + 1]])) {
                            $item[$parts[$key + 1]] = [];
                        }
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

    public static function removePath(array &$data, string $path)
    {
        $parts = explode('.', $path);
        $current = &$data;

        foreach ($parts as $key => $part) {

            if ($part === '*') {

                // Ensure that the current item is an array
                if (!is_array($current)) {
                    return;
                }

                if ($key === count($parts) - 1) {
                    // If wildcard is the last part, unset all items in the current array
                    foreach ($current as &$item) {
                        unset($item);
                    }
                } else {
                    $nextPath = implode('.', array_slice($parts, $key + 1));
                    foreach ($current as &$item) {
                        self::removePath($item, $nextPath);
                    }
                }
                return;  // Exit after processing the wildcard
            } elseif (is_numeric($part)) {
                $part = (int)$part;
            }

            // If the part doesn't exist, exit early
            if (is_array($current) && !array_key_exists($part, $current)) {
                return;
            } elseif (is_object($current) && !property_exists($current, $part)) {
                return;
            }

            // If it's the last part of the path, unset it
            if ($key === count($parts) - 1) {
                unset($current[$part]);
                return;
            }

            // Move the pointer
            $current = &$current[$part];
        }
    }

}
