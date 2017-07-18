<?php
namespace radsectors;

final class urp
{
    private $lvl = 0;
    private $obj = [];
    private $arr = [];

    private $inst = true;

    private static $do_html;
    private static $do_css;
    private static $do_ob;
    private static $stat;
    private static $meth;

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
        '#<#' => '&lt;',
        '#>#' => '&gt;',
        '#^(https?://|//?)(.+)$#' => '<a href="$1$2">$1$2</a>', // convert URLs HTML links
    ];

    public static function __callStatic($name, $args)
    {
        $limit = ini_set('memory_limit', -1); // temporarily raise memory_limit
        !isset(self::$do_html) && self::$do_html = (php_sapi_name() !== 'cli');
        !isset(self::$do_ob) && self::$do_ob = false;
        self::$stat = (stripos($name, 'stat') !== false);
        self::$meth = (stripos($name, 'meth') !== false);

        self::$do_ob && ob_start(); // capture output

        self::$do_html && print '<pre>';

        // get caller info
        $bt = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $bti = (int) (isset($bt[1]['function']) && in_array($bt[1]['function'], ['pq', 'pqd', ]));
        $fil = $bt[$bti]['file'];
        $lin = $bt[$bti]['line'];
        unset($bt, $bti);

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

        self::$do_ob && print ob_get_clean();

        if ($limit) ini_set('memory_limit', $limit); // restore memory_limit
    }

    private function digest($thing)
    {
        $typ = gettype($thing);
        !is_string($thing) && is_callable($thing) && $typ = 'function';
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
            case 'function':
                $meth = new \ReflectionFunction($thing);
                self::$do_html && $typ = "<span style=\"color:orange;\">$typ</span>";
                $len = '';
                $opts = 0;
                foreach ($meth->getParameters() as $i => $par) {
                    $par->isOptional() && $opts++;
                    $par->com = $i > 0 ? ',' : '';
                    $par->opl = $par->isOptional() ? " [$par->com " : "$par->com ";
                    $par->typ = $par->hasType() ? $par->getType().' ' : '';
                    $par->ref = $par->isPassedByReference() ? '&' : '';
                    $par->nam = "\$$par->name";
                    $par->def = '';
                    if ($par->isDefaultValueAvailable()) {
                        $par->val = $par->getDefaultValue();
                        $par->val = is_bool($par->val) ? boolval($par->val) ? 'true' : 'false' : $par->val;
                        $par->str = is_string($par->val);
                        $par->val = is_scalar($par->val) ? $par->val : gettype($par->val);
                        $par->str && $par->val = "'$par->val'";
                        $par->def = " = $par->val";
                    }
                    $par->opr = $par->isOptional() && $i == 0 ? ' ]' : '';

                    $len .= "$par->opl$par->typ$par->ref$par->nam$par->def";
                }
                $len .= str_repeat(' ]', $opts).' ';
                unset($par);
                $meth->hasReturnType() && $tag = ' : '.$meth->getReturnType();
                break;
            case 'unknown type':
                pq("let @radsectors know if you ever see this", $thing);
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
                if (self::$do_html && strpos($typ, 'array') !== false) {
                    $color = is_string($name) ? 'darkgreen' : 'red';
                    $name = "<span style=\"color:$color;\">$name</span>";
                }
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
        $ref = new \ReflectionObject($obj);
        if ($props = $ref->getProperties()) {
            foreach ($props as $prop) {
                if ($prop->isStatic() && !self::$stat) continue;

                $name = $prop->getName();
                $mods = $this->getmodstr($prop);
                $static = $prop->isStatic() ? 'text-decoration:underline;' : '';
                self::$do_html && $name = "<span style=\"color:darkgreen;$static\">$name</span>";
                $arr["$mods$name"] = $prop->getValue($obj);
            }
        } else {
            $props = (array)$obj;
            if (!empty($props)) pq("let @radsectors know if you ever see this", $thing);
            foreach ($props as $name => $val) {
                self::$do_html && $name = "<span style=\"color:darkgreen\">$name</span>";
                $arr["+$name"] = $val;
            }
        }

        if (!self::$meth) return $arr;

        if ($meths = $ref->getMethods()) {
            foreach ($meths as $meth) {
                if ($meth->isStatic() && !self::$stat) continue;

                $name = $meth->getName();
                $mods = $this->getmodstr($meth);
                $static = $meth->isStatic() ? 'text-decoration:underline;' : '';
                self::$do_html && $name = "<span style=\"color:darkgreen;$static\">$name</span>";
                $arr["$mods$name"] = function() use($meth) { return $meth; };
            }
        }

        return $arr;
    }

    private function getmodstr(&$thing)
    {
        if (!$thing->isDefault()) return '~';
        $mods = '';
        $thing->isPublic() && $mods .= '+';
        $thing->isPrivate() && $mods .= '-';
        $thing->isProtected() && $mods .= '#';
        !$thing->isPublic() && $thing->setAccessible(true);

        return $mods;
    }


    public static function filter($str, $filters)
    {

    }
}

// for lazy debugging of the debug
function pq() {
    foreach (func_get_args() as $arg) {
        var_dump($arg);
    }
}
function pqd() {
    foreach (func_get_args() as $arg) {
        var_dump($arg);
    }
    die();
}
