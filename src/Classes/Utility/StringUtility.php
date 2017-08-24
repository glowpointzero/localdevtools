<?php
namespace Glowpointzero\LocalDevTools\Utility;

class StringUtility
{
    
    /**
     * Generates a random string.
     *
     * @param int $length
     * @param string $characters
     * @return string
     */
    public static function generateRandomString($length = 8, $characters = 'abcdefghijklmnopqrstuvwxyz0123456789$!=%*')
    {
        $randomString = '';
        while (strlen($randomString) < $length) {
            $randomString .= substr($characters, random_int(0, strlen($characters)-1), 1);
        }
        return $randomString;
    }

    /**
     * Removes and ascii control characters (decimal 0-21, hex 00 to 1F)
     * from a string
     * 
     * @param $string
     * @parm $matches
     * @return string
     */
    public static function removeAsciiControlCharacters($string, &$matches = [])
    {
        $string = urlencode($string);
        // Strip any control characters except LF and CR (10 and 13)
        $controlCharactersDec = range(0, 31);
        unset($controlCharactersDec[13]);
        unset($controlCharactersDec[10]);
        foreach ($controlCharactersDec as $controlCharacterDec) {
            $originalString = $string;
            $hexCode = dechex($controlCharacterDec);
            if (strlen($hexCode) === 1) {
                $hexCode = '0' . $hexCode;
            }
            $string = str_replace('%' . strtoupper($hexCode), '', $string);
            $string = str_replace('%' . strtolower($hexCode), '', $string);
            if ($originalString !== $string) {
                $matches[] = $controlCharacterDec;
            }
        }
        var_dump($string);
        var_dump(urldecode($string));
        return urldecode($string);
    }
}
