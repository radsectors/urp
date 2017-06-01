<?php
namespace radsectors;

final class urp
{
    private $lvl = 0;
    private $obj = [];
    private $arr = [];

    private static $do_html;
    private static $do_css;
    private static $do_ob;

    private static $template = [];

    private function __construct()
    {
        self::$template['text'] = (object)[
            'typ' => "",
            'typ_open' => "",
            'typ_clos' => "",
            'typ_cls' => "(%s)",
            'len' => "%s",
            'len_open' => "(",
            'len_clos' => ")",
            'con_open' => " {\n",
            'con_clos' => "}\n",
        ];
        self::$template['html'] = (object)[
            'typ' => "",
            'typ_open' => "",
            'typ_clos' => "",
            'typ_cls' => "",
            'len' => "",
            'len_open' => "",
            'len_clos' => "",
            'con_open' => "",
            'con_clos' => "",
        ];
    }

    private static $pr = [
        '#^(https?://|//?)(.+)$#' => '<a href="$1$2">$1$2</a>', // convert URLs HTML links
    ];

    public static function __callStatic($name, $args)
    {
        $limit = ini_set('memory_limit', -1); // temporarily raise memory_limit
        !isset(self::$do_html) && self::$do_html = (php_sapi_name() !== 'cli');
        !isset(self::$do_ob) && $do_ob = false;

        $do_ob && ob_start(); // capture output

        self::$do_html && print '<pre>';

        // get caller info
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $bti = (int) (isset($bt[1]['function']) && in_array($bt[1]['function'], ['pq', 'pqd', ]));
        $fil = $bt[$bti]['file'];
        $lin = $bt[$bti]['line'];
        unset($bt);

        self::$do_html && $name = "<strong>$name</strong>";
        self::$do_html && $lin = "<strong>$lin</strong>";

        $hdr = "$name: $fil:$lin";
        self::$do_html && $hdr = "<span style=\"background-color:lightgray;\">$hdr</span>";
        print "$hdr\n";
        unset($hdr);

        foreach ($args as $arg) {
            call_user_func([new self, 'digest'], $arg);
        }
        unset($args); // free up memory.
        self::$do_html && print '</pre>';

        $do_ob && print ob_get_clean();

        if ($limit) ini_set('memory_limit', $limit); // restore memory_limit
    }

    private function digest($thing)
    {
        $typ = gettype($thing);
        $len = $tag = $hsh = '';

        switch($typ) {
            case 'object':
                $cls = get_class($thing);
                $hsh = spl_object_hash($thing);
                if (isset($this->obj[$hsh])) {
                    $typ = "*RECURSION*";
                    $thing = null;
                    self::$do_html && $typ = "<a href=\"#$hsh\" title=\"$hsh\">$typ</a>";
                    break;
                }
                $this->obj[$hsh] = true;
                self::$do_html && $cls = "<a name=\"$hsh\" title=\"$hsh\">$cls</a>";
                $typ = "$typ($cls)";
                $thing = $this->invade($thing);
                $len = count($thing);
                break;
            case 'array':
                if ($hsh = array_search($thing, $this->arr, true)) {
                    $typ = "*RECURSION*";
                    $thing = null;
                    self::$do_html && $typ = "<a href=\"#$hsh\" title=\"$hsh\">$typ</a>";
                    break;
                } else {
                    $hsh = uniqid(uniqid('', true), true);
                }
                $this->arr[$hsh] = $thing;
                self::$do_html && $typ = "<a name=\"$hsh\" title=\"$hsh\" style=\"color:darkorange;\">$typ</a>";
                $len = count($thing);
                break;
            case 'string':
                $len = strlen($thing);
                self::$do_html && $thing = preg_replace(array_keys(self::$pr), array_values(self::$pr), $thing);
                $tag = $thing;
                self::$do_html && $tag = " <span style=\"color:darkgreen;text-decoration:underline;\">$thing</span>";
                break;
            case 'double':
                $typ = 'float';
                $len = $thing;
                self::$do_html && $len = "<span style=\"color:red;\">$len</span>";
                break;
            case 'integer':
                $typ = 'int';
                $len = $thing;
                self::$do_html && $len = "<span style=\"color:red;\">$len</span>";
                break;
            case 'boolean':
                $typ = 'bool';
                $len = $thing ? 'true' : 'false';
                self::$do_html && $len = "<span style=\"color:teal;\">$len</span>";
                break;
            case 'NULL':
                $typ = 'null';
                self::$do_html && $typ = "<span style=\"color:grey;\">$typ</span>";
                break;
            case 'resource':
                $len = '#nyi';
                $restyp = get_resource_type($thing);
                $tag = " of type ($restyp)";
                break;
            case 'unknown type':
            default:
                break;
        }

        if (strlen($len) > 0) $len = "($len)";

        print "$typ$len$tag";
        if (is_array($thing)) {
            // print $template->con_open;
            print " {\n";
            $pad = str_repeat(' ', $this->lvl * 2);
            $this->lvl++;
            foreach ($thing as $name => $t) {
                !self::$do_html && is_numeric($name) && is_string() && $name = "'$name'";
                self::$do_html && is_string($name) && $name = "<span style=\"color:darkgreen;\">$name</span>";
                self::$do_html && !is_string($name) && $name = "<span style=\"color:red;\">$name</span>";
                print "$pad  [$name] => ";
                self::digest($t);
            }
            print "$pad}";
            $this->lvl--;
            if (isset($this->arr[$hsh])) unset($this->arr[$hsh]);
        }
        print "\n";

        if (isset($this->obj[$hsh])) unset($this->obj[$hsh]);
    }

    private function invade($obj)
    {
        $arr = [];
        $ref = new \ReflectionClass($obj);
        $filter = \ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED | \ReflectionProperty::IS_PRIVATE;
        if ($props = $ref->getProperties($filter)) {
            foreach ($props as $prop) {
                if (!$prop->isStatic()) {
                    $name = $prop->getName();
                    self::$do_html && $name = "<span style=\"color:darkgreen\">$name</span>";
                    $mods = '';
                    if (!$prop->isDefault()) $mods .= '~';
                    if ($prop->isPublic()) $mods .= '+';
                    if ($prop->isPrivate()) $mods .= '-';
                    if ($prop->isProtected()) $mods .= '#';
                    if (!$prop->isPublic()) $prop->setAccessible(true);
                    $arr["$mods$name"] = $prop->getValue($obj);
                }
            }
        } else {
            foreach ((array)$obj as $name => $val) {
                self::$do_html && $name = "<span style=\"color:darkgreen\">$name</span>";
                $arr["+$name"] = $val;
            }
            // $arr = (array)unserialize(serialize($obj), ['allowed_classes' => false]);
            // unset($arr['__PHP_Incomplete_Class_Name']);
        }

        return $arr;
    }


    public static function filter($str, $filters)
    {

    }
}

// for lazy debugging of the debug
function pq() {
    var_dump(func_get_args());
}
function pqd() {
    var_dump(func_get_args());
    die();
}
