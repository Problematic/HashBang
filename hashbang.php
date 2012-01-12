<?php

class HashBang
{

    protected $main;

    protected static $switches = array();
    protected static $args = array();
    protected static $requiredArgs = array();
    protected static $optionalArgs = array();

    protected static $argv = array();
    protected static $options = array();

    public function __construct(\Closure $main)
    {
        $this->main = $main;
    }

    public function go()
    {
        try {
            $argv = $this->initialize();
            $options = array(); // todo: implement option switches
            $this->invoke($this->main, $argv);
        } catch (\HashBangException $e) {
            $this->handleException($e);
        }
    }

    public static function addArg($arg, $required = true)
    {
        self::$args[$arg] = null;
        if ($required) {
            self::$requiredArgs[] = $arg;
        } else {
            self::$optionalArgs[] = $arg;
        }
    }

    public static function addSwitch($short, $long = null)
    {
        $switch = array();
        $switch['short'] = $short;
        $switch['long'] = $long;

        self::$switches[] = $switch;
    }

    protected function invoke(\Closure $main)
    {
        $refl = new \ReflectionFunction($main);

        $args = self::$args;
        $args['argv'] = self::$argv;
        $args['options'] = self::$options;
        $argList = array();

        $params = $refl->getParameters();
        foreach ($params as $param) {
            $argList[$param->getPosition()] = isset($args[$param->name]) ? $args[$param->name] : null;
        }

        $refl->invokeArgs($argList);
    }

    /**
     * @return array values left in $argv after initialization
     */
    protected function initialize()
    {
        $argv = $GLOBALS['argv'];
        array_shift($argv);

        $count = count(self::$switches);
        for($i = 0; $i < $count; $i++) {
            $short = ltrim(self::$switches[$i]['short'], '-');
            $long = ltrim(self::$switches[$i]['long'], '-');

            $opts = getopt($short, array($long));
            array_walk($opts, function($value, $key) use(&$argv) {
                $prefix = 1 === strlen($key) ? '-' : '--';
                if (false !== $index = array_search($prefix . $key, $argv)) {
                    unset($argv[$index]);
                }
            });

            self::$options[$long] = self::$options[$short] = true;
        }
        unset(self::$options['']); // artifact from switches without a long version

        while (self::$requiredArgs) {
            $required = array_shift(self::$requiredArgs);
            $val = array_shift($argv);
            if (null === $val) {
                throw new \HashBangException(sprintf("'%s' is a required argument", $required));
            }
            self::$args[$required] = $val;
        }

        while (self::$optionalArgs) {
            $optional = array_shift(self::$optionalArgs);
            self::$args[$optional] = array_shift($argv);
        }

        self::$argv = $argv;
    }

    protected function handleException(hashbangException $e)
    {
        echo "Error: {$e->getMessage()}\n";
    }

}

class HashBangException extends \Exception {}
