<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater\Imports;

use Spiral\Templater\HtmlTokenizer;
use Spiral\Templater\ImportInterface;
use Spiral\Templater\Templater;

/**
 * Declares to templater that element must be treated as html tag, not Node include. Stop keyword
 * must be located in
 * "stop" attribute of tag caused import.
 */
class StopImport implements ImportInterface
{
    /**
     * Html tag name.
     *
     * @var string
     */
    protected $element = '';

    /**
     * {@inheritdoc}
     */
    public function __construct(Templater $templater, array $token)
    {
        $attributes = $token[HtmlTokenizer::TOKEN_ATTRIBUTES];

        //Html tag name must be stored in this attribute
        $this->element = $attributes['stop'];
    }

    /**
     * {@inheritdoc}
     */
    public function isImported($element, array $token)
    {
        if ($this->element == '*') {
            //To disable every lower level importer, you can still define more importers after that
            return true;
        }

        return strtolower($element) == strtolower($this->element);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocation($element, array $token)
    {
        return null;
    }
}