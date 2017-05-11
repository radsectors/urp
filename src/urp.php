<?php

namespace radsectors;

final class urp
{
    private $lvl = 0;
    private $trk = [];

    private static $do_html;

    private function __construct() {
        // no instance
    }

    private static $pr = [
        '#^(https?://|//?)(.+)$#' => '<a href="$1$2">$1$2</a>', // convert URLs HTML links
    ];

    public static function __callStatic($name, $args)
    {
        $limit = ini_set('memory_limit', -1); // temporarily raise memory_limit
        !isset(self::$do_html) && self::$do_html = (php_sapi_name() !== 'cli');

        self::$do_html && print '<pre>';
        print "$name: ";
        call_user_func_array([new self, 'traverse'], $args);
        unset($args); // free up memory.
        self::$do_html && print '</pre>';


        if ($limit) ini_set('memory_limit', $limit); // restore memory_limit
    }

    private function traverse($obj)
    {
        $typ = gettype($obj);
        $len = $tag = $hsh = '';

        switch($typ) {
            case 'object':
                $cls = get_class($obj);
                $hsh = spl_object_hash($obj);
                if (isset($this->trk[$hsh])) {
                    $typ = "*RECURSION*";
                    break;
                }
                $this->trk[$hsh] = true;
                $typ = "$typ($cls)#$hsh ";
                $obj = (array)$obj;
            case 'array':
                $len = count($obj);
                break;
            case 'string':
                $len = strlen($obj);
                self::$do_html && $obj = preg_replace(array_keys(self::$pr), array_values(self::$pr), $obj);
                $tag = " \"$obj\"";
                break;
            case 'double':
                $typ = 'float';
                $len = $obj;
                break;
            case 'integer':
                $typ = 'int';
                $len = $obj;
                break;
            case 'boolean':
                $typ = 'bool';
                $len = $obj ? 'true' : 'false';
                break;
            case 'NULL':
                $typ = 'null';
                break;
            case 'resource':
                $len = '#nyi';
                $restyp = get_resource_type($obj);
                $tag = " of type ($restyp)";
                break;
            case 'unknown type':
            default:
                break;
        }

        if (strlen($len) > 0) $len = "($len)";

        print "$typ$len$tag";
        if (is_array($obj)) {
            print " {\n";
            $pad = str_repeat(' ', $this->lvl * 2);
            $this->lvl++;
            foreach ($obj as $idx => $o) {
                if (is_string($idx)) $idx = "'$idx'";
                print "$pad  [$idx] => ";
                self::traverse($o);
            }
            print "$pad}";
            $this->lvl--;
        }
        print "\n";
        unset($this->trk[$hsh]);
    }
}
