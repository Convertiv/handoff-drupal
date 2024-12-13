<?php

namespace Drupal\handoff;

use Handlebars\Handlebars;

class TwigTranspile
{

    public $buffer = [];
    public $parsed;
    public $iterator = [];

    /**
     * Pass code to the transpiler
     *
     * @param string $code
     */
    public function __construct($code)
    {
        $handlebars = new Handlebars();
        $this->parsed = $handlebars->loadTemplate($code);
    }

    /**
     * Parse a content block
     * Content blocks should just treated as raw text
     *
     * @param Handlebars $node
     * @return void
     */
    public function content($node)
    {
        $this->buffer[] = $node['value'];
    }

    /**
     * Handle a mustache block
     *
     * @param Handlebars $node
     * @return void
     */
    public function mustache($node)
    {
        $name = $node['name'];
        $end = end($this->iterator);
        if ($end) {
            if ($name == $end) {
                $name = 'loop.index0';
            }
            if ($name === 'this') {
                $name = $end;
            } elseif (str_contains($name, 'this.')) {
                $name = str_replace('this.', "$end.", $name);
            }
        }
        $this->buffer[] = '{{' . $name . '}}';
    }

    /**
     * Handle a block
     *
     * @param Handlebars $node
     * @return void
     */
    public function block($node)
    {
        switch ($node['name']) {
            case 'if':
                $this->ifBlock($node);
                break;
            case 'each':
                $this->eachBlock($node);
                break;
            case 'unless':
                $this->unlessBlock($node);
                break;
        }
    }

    /**
     * Handle an if block
     *
     * @param Handlebars $node
     * @return void
     */
    public function ifBlock($node)
    {
        $this->buffer[] = '{% if ' . $node['args'] . ' %}';
        $this->buffer[] = $this->program($node['nodes']);
        $this->buffer[] = '{% endif %}';
    }

    /**
     * Handle an each block
     *
     * @param Handlebars $node
     * @return void
     */
    public function eachBlock($node)
    {
        if (isset($node['args'])) {
            $arg = $node['args'];
            // Get the last arg in the path resolution if possible
            if (str_contains($arg, '.')) {
                $args = explode('.', $arg);
                $arg = end($args);
            }
            // Get the first letter of the arg to make a loop itterator
            $this->iterator[] = $current = substr($arg, 0, 1);
            $this->buffer[] = '{% for ' . $current . ' in ' . $node['args'] . ' %}';
            $this->buffer[] = $this->program($node['nodes']);
            $this->buffer[] = '{% endfor %}';
            array_pop($this->iterator);
        }
    }

    /**
     * Handle an unless block
     *
     * @param Handlebars $node
     * @return void
     */
    public function unlessBlock($node)
    {
        $this->buffer[] = '{% if not ' . $node['args'] . ' %}';
        $this->buffer[] = $this->program($node['nodes']);
        $this->buffer[] = '{% endif %}';
    }

    /**
     * Render the transpiled code
     *
     * @return string
     */
    public function render()
    {
        $this->program($this->parsed);
        return implode(" ", $this->buffer);
    }

    /**
     * Parse a program
     *
     * @param object|array $data
     * @return void
     */
    public function program($data)
    {
        if (!is_array($data)) {
            $tree = $data->getTree();
        } else {
            $tree = $data;
        }
        foreach ($tree as $parsed) {
            switch ($parsed['type']) {
                case '_t':
                    $this->content($parsed);
                    break;
                case '_v':
                    $this->mustache($parsed);
                    break;
                case '#':
                    $this->block($parsed);
                    break;
                default:
                    throw new \Exception('Unknown node type: ' . $parsed['type']);
                    break;
            }
        }
    }
}
