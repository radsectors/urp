<?php

namespace radsectors;

final class urp
{
    public static function __callStatic($name, $args)
    {
        $limit = ini_set('memory_limit', -1); // temporarily raise memory_limit

        // capure var_dump output
        ob_start();
        (php_sapi_name() !== 'cli') && print '<pre>';
        print "$name: ";
        call_user_func_array('var_dump', $args);
        unset($args); // free up memory.
        (php_sapi_name() !== 'cli') && print '</pre>';

        // do string manipulations
        $out = preg_replace(
            array_keys(self::$pr),
            array_values(self::$pr),
            ob_get_clean()
        );

        print $out;
        unset($out); // free up memory.
        if ($limit) ini_set('memory_limit', $limit); // restore memory_limit
    }

    private static $pr = [
        "/=>\n\s*/"                 => ' => ', // *fix* var_dump's unnecessary linebreaks
        '/=> NULL(\n)/'               => '=> null$1', // lowercase nulls. because why are they caps'd?
        '#"(https?://|//?)(.+)"#'   => '"<a href="$1$2">$1$2</a>"', // convert URLs HTML links
    ];

    private function __construct() {
        // no instance
    }
}
