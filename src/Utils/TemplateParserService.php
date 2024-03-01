<?php

namespace PhelixJuma\DataTransformer\Utils;

final class TemplateParserService {

    /**
     *
     */
    public function __construct() {
    }


    private static function escapeRegexMetaCharacters(&$string, array $exceptions=[]) {
        $meta = ['.','+','*','?','{','}','(',')','[',']','$', '^', '/'];
        foreach ($meta as $item) {
            if (str_contains($string, $item) && !in_array($item, $exceptions)) {
                $string = str_replace($item, "\\$item", $string);
            }
        }
    }

    /**
     * @param $message
     * @param $template
     * @param array $matchConfig Sample match config: $matchConfig = [
     *   0 => ['non_greedy' => 1, 'only_numbers' => 1, 'only_letters' => 1]
     * ]
     * @return array|false
     */
    public static function parseMessageFromTemplate($message, $template, $matchConfig=[], $modifiers="i") {

        // Step 1: We seek to get template variables. These are within the double curly brackets
        preg_match_all("/({{.*?}})/", $template, $matches);

        $variables = []; // holds the variables from the Templates.

        // Step 2: We seek to convert the template into a regex pattern. While at it, we set the variables.
        $pattern = $template;

        foreach($matches[1] as $key => $match) {
            $regex = '.*';
            if (isset($matchConfig[$key])) {
                if (isset($matchConfig[$key]['non_greedy']) && $matchConfig[$key]['non_greedy'] == 1) {
                    $regex = ".*?";
                } elseif (isset($matchConfig[$key]['only_numbers']) && $matchConfig[$key]['only_numbers'] == 1) {
                    $regex = "\d+";
                } elseif (isset($matchConfig[$key]['only_letters']) && $matchConfig[$key]['only_letters'] == 1) {
                    $regex = "[a-zA-Z]+";
                }
            }
            $pattern = str_replace($match, "($regex)", $pattern);

            $variables[] = trim(str_replace(['{{', '}}'], "", $match));
        }

        // Step 3: We seek to find the values from the message by matching to the template pattern.
        var_dump("$pattern");
        var_dump("$modifiers");
        preg_match("/$pattern/$modifiers", $message, $values);
        var_dump($values);

        if (empty($values)) {
            return null;
        }

        array_shift($values); // remove the first group which usually captures whole message.

        // Step 4: We return the result as an associative array from the variables and the values.
        return array_combine($variables, $values);
    }

}
