<?php
namespace GlowPointZero\LocalDevTools\Utility;

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
}
