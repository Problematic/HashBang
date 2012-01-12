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
            $this->initialize();
            $this->invoke($this->main);
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
            $value = null;
            foreach ($opts as $opt => $value) {
                $short = rtrim($short, ':');
                $long = rtrim($long, ':');
                $value = $value ?: true;
                $prefix = 1 === strlen($opt) ? '-' : '--';
                if (false !== $index = array_search($prefix.$opt, $argv)) {
                    unset($argv[$index]);
                    if (true !== $value) {
                        unset($argv[++$index]);
                    }
                }
            }

            self::$options[$long] = self::$options[$short] = $value;
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
