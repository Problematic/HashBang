<?php

class HashBang
{

    protected $main;

    protected $switches = array();
    protected $args = array();
    protected $requiredArgs = array();
    protected $optionalArgs = array();

    protected $argv = array();
    protected $options = array();

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

    public function addArg($arg, $required = true)
    {
        $this->args[$arg] = null;
        if ($required) {
            $this->requiredArgs[] = $arg;
        } else {
            $this->optionalArgs[] = $arg;
        }
    }

    public function addSwitch($short, $long = null)
    {
        $switch = array();
        $switch['short'] = $short;
        $switch['long'] = $long;

        $this->switches[] = $switch;
    }

    protected function invoke(\Closure $main)
    {
        $refl = new \ReflectionFunction($main);

        $args = $this->args;
        $args['argv'] = $this->argv;
        $args['options'] = $this->options;
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

        $count = count($this->switches);
        for($i = 0; $i < $count; $i++) {
            $short = ltrim($this->switches[$i]['short'], '-');
            $long = ltrim($this->switches[$i]['long'], '-');

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

            $this->options[$long] = $this->options[$short] = $value;
        }
        unset($this->options['']); // artifact from switches without a long version

        while ($this->requiredArgs) {
            $required = array_shift($this->requiredArgs);
            $val = array_shift($argv);
            if (null === $val) {
                throw new \HashBangException(sprintf("'%s' is a required argument", $required));
            }
            $this->args[$required] = $val;
        }

        while ($this->optionalArgs) {
            $optional = array_shift($this->optionalArgs);
            $this->args[$optional] = array_shift($argv);
        }

        $this->argv = $argv;
    }

    protected function handleException(hashbangException $e)
    {
        echo "Error: {$e->getMessage()}\n";
    }

}

class HashBangException extends \Exception {}
