<?php
/**
 * This is script handles random strings
 * @author Phelix Juma <jumaphelix@Kuza\Krypton.co.ke>
 * @copyright (c) 2018, Kuza Lab
 * @package Kuza\Krypton
 */

namespace PhelixJuma\GUIFlow\Utils;

/**
 * Class for handling random string generation
 * @package Kuza\Krypton
 */
class Randomiser {

    /**
     *
     */
    public function __construct() {
    }

    /**
     * @param $length
     * @param $alphabet
     * @return string
     */
    public static function getRandomString($length = 10, $alphabet="") {

        $length = (int) $length;

        if (empty($alphabet)) {
            $alphabet = implode(range('a', 'z'))
                . implode(range('A', 'Z'))
                . implode(range(0, 9));
        }

        $alphabetLength = strlen($alphabet);

        $token = '';

        for ($i = 0; $i < $length; $i++) {
            $randomKey = self::getRandomInteger(0, $alphabetLength);
            $token .= $alphabet[$randomKey];
        }

        return $token;
    }

    /**
     * Function to get a random integer
     * @param int $min
     * @param int $max
     * @return int
     */
    protected static function getRandomInteger($min, $max) {
        $range = ($max - $min);

        if ($range < 0) {
            // Not so random...
            return $min;
        }

        $log = log($range, 2);

        // Length in bytes.
        $bytes = (int) ($log / 8) + 1;

        // Length in bits.
        $bits = (int) $log + 1;

        // Set all lower bits to 1.
        $filter = (int) (1 << $bits) - 1;

        do {
            $rnd = hexdec(bin2hex(openssl_random_pseudo_bytes($bytes)));

            // Discard irrelevant bits.
            $rnd = $rnd & $filter;
        } while ($rnd >= $range);

        return ($min + $rnd);
    }

}
