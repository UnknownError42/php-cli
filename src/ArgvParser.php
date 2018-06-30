<?php

namespace Ahc\Cli;

/**
 * Argv parser for the cli.
 *
 * @author  Jitendra Adhikari <jiten.adhikary@gmail.com>
 * @license MIT
 *
 * @link    https://github.com/adhocore/cli
 */
class ArgvParser extends Parser
{
    use InflectsString;

    /** @var string */
    protected $_version;

    /** @var string */
    protected $_name;

    /** @var string */
    protected $_desc;

    /** @var callable[] Events for options */
    protected $_events = [];

    /** @var bool Whether to allow unknown (not registered) options */
    protected $_allowUnknown = false;

    /**
     * Constructor.
     *
     * @param string $name
     * @param string $desc
     * @param bool   $allowUnknown
     */
    public function __construct(string $name, string $desc = null, bool $allowUnknown = false)
    {
        $this->_name         = $name;
        $this->_desc         = $desc;
        $this->_allowUnknown = $allowUnknown;

        $this->option('-h, --help', 'Show help')->on([$this, 'showHelp']);
        $this->option('-V, --version', 'Show version')->on([$this, 'showVersion']);
    }

    /**
     * Sets version.
     *
     * @param string $version
     *
     * @return self
     */
    public function version(string $version): self
    {
        $this->_version = $version;

        return $this;
    }

    /**
     * Registers argument definitions (all at once). Only last one can be variadic.
     *
     * @param string $definitions
     *
     * @return self
     */
    public function arguments(string $definitions): self
    {
        $definitions = \explode(' ', $definitions);
        foreach ($definitions as $i => $definition) {
            $argument = new Argument($definition);

            if ($argument->variadic() && isset($definitions[$i + 1])) {
                throw new \InvalidArgumentException('Only last argument can be variadic');
            }

            $name = $argument->attributeName();

            $this->ifAlreadyRegistered($name, $argument);

            $this->_arguments[$name] = $argument;
            $this->_values[$name]    = $argument->default();
        }

        return $this;
    }

    /**
     * Registers new option.
     *
     * @param string        $cmd
     * @param string        $desc
     * @param callable|null $filter
     * @param mixed         $default
     *
     * @return self
     */
    public function option(string $cmd, string $desc = '', callable $filter = null, $default = null): self
    {
        $option = new Option($cmd, $desc, $default, $filter);
        $name   = $option->attributeName();

        $this->ifAlreadyRegistered($name, $option);

        $this->_options[$name] = $option;
        $this->_values[$name]  = $option->default();

        return $this;
    }

    protected function ifAlreadyRegistered(string $name, Parameter $param)
    {
        if (\array_key_exists($name, $this->_values)) {
            throw new \InvalidArgumentException(\sprintf(
                'The parameter "%s" is already registered',
                $param instanceof Option ? $param->long() : $param->name()
            ));
        }
    }

    /**
     * Sets event handler for last option.
     *
     * @param callable $fn
     *
     * @return self
     */
    public function on(callable $fn): self
    {
        \end($this->_options);

        $this->_events[\key($this->_options)] = $fn;

        return $this;
    }

    protected function handleUnknown(string $arg, string $value = null)
    {
        if ($this->_allowUnknown) {
            $this->_values[$this->toCamelCase($arg)] = $value;

            return;
        }

        $values = \array_filter($this->_values, function ($value) {
            return $value !== null;
        });

        // Has some value, error!
        if ($values) {
            throw new \RuntimeException(
                \sprintf('Option "%s" not registered', $arg)
            );
        }

        // Has no value, show help!
        return $this->showHelp();
    }

    /**
     * Get values indexed by camelized attribute name.
     *
     * @param bool $withDefaults
     *
     * @return array
     */
    public function values(bool $withDefaults = true): array
    {
        $values = $this->_values;

        if (!$withDefaults) {
            unset($values['help'], $values['version']);
        }

        return $values;
    }

    /**
     * Magic getter for specific value by its key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function __get(string $key)
    {
        return $this->_values[$key] ?? null;
    }

    public function args(): array
    {
        return \array_diff_key($this->_values, $this->_options);
    }

    protected function showHelp()
    {
        echo "{$this->_name}, version {$this->_version}" . PHP_EOL;

        // @todo: build help msg!
        echo "help\n";

        _exit();
    }

    protected function showVersion()
    {
        echo $this->_version . PHP_EOL;

        _exit();
    }

    protected function emit(string $event)
    {
        if (empty($this->_events[$event])) {
            return;
        }

        $callback = $this->_events[$event];

        $callback();
    }
}

// @codeCoverageIgnoreStart
if (!\function_exists(__NAMESPACE__ . '\\_exit')) {
    function _exit($code = 0)
    {
        exit($code);
    }
}
// @codeCoverageIgnoreEnd
