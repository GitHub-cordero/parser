<?php namespace Orchestra\Parser;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class Document
{
    /**
     * Container instance.
     *
     * @var \Illuminate\Container\Container
     */
    protected $app;

    /**
     * Content.
     *
     * @var mixed
     */
    protected $content;

    /**
     * Construct a new document.
     *
     * @param \Illuminate\Container\Container $app
     */
    public function __construct(Container $app)
    {
        $this->app = $app;
    }


    /**
     * Parse document.
     *
     * @param  array    $schema
     * @param  array    $config
     * @return array
     */
    public function parse(array $schema, array $config = array())
    {
        $output = array();

        foreach ($schema as $key => $data) {
            $value = $this->parseData($data);

            if (! Arr::get($config, 'ignore', false)) {
                $output[$key] = $value;
            }
        }

        return $output;
    }

    /**
     * Set the content.
     *
     * @param  mixed $content
     * @return void
     */
    public function setContent($content)
    {
        $this->content = $content;
    }

    /**
     * Get the content.
     *
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Filter value.
     *
     * @param  mixed    $value
     * @param  string   $filter
     * @return mixed
     */
    protected function filterValue($value, $filter)
    {
        $resolver = $this->getFilterResolver($filter);

        if (method_exists($resolver[0], $resolver[1])) {
            return $this->app->call($resolver, array($value));
        }

        return $value;
    }

    /**
     * Resolve value from content.
     *
     * @param  array    $config
     * @param  string   $hash
     * @return mixed
     */
    protected function resolveValue(array $config, $hash)
    {
        if (! isset($config['uses'])) {
            return $config['default'];
        }

        if (! is_array($config['uses'])) {
            return $this->getValue($this->content, $config['uses'], $hash);
        }

        $values = array();

        foreach ($config['uses'] as $use) {
            $values[] = $this->getValue($this->content, $use, $hash);
        }

        return $values;
    }

    /**
     * Resolve value from uses filter.
     *
     * @param  mixed    $content
     * @param  string   $use
     * @param  string   $default
     * @return mixed
     */
    abstract protected function getValue($content, $use, $default = null);

    /**
     * Get filter resolver.
     *
     * @param  string   $filter
     * @return array
     */
    protected function getFilterResolver($filter)
    {
        $class  = $filter;
        $method = 'filter';

        if (Str::startsWith($filter, '@')) {
            $method = 'filter' . Str::studly(substr($filter, 1));
            return array($this, $method);
        }

        if (Str::contains($filter, '@')) {
            list($class, $method) = explode('@', $filter, 2);
        }

        return array($this->app->make($class), $method);
    }

    /**
     * Parse single data.
     *
     * @param  mixed    $data
     * @return mixed
     */
    protected function parseData($data)
    {
        $hash   = Str::random(60);
        $value  = $data;
        $filter = null;

        if (is_array($data)) {
            $value  = $this->resolveValue($data, $hash);
            $filter = Arr::get($data, 'filter');
        }

        if ($value === $hash) {
            $value = Arr::get($data, 'default');
        }

        if (! is_null($filter)) {
            $value = $this->filterValue($value, $filter);
        }

        return $value;
    }
}
