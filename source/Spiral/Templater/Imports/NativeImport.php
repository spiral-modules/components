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
 * Declares to templater that element must be treated as html tag, not Node include.
 */
class NativeImport implements ImportInterface
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
        $this->element = $attributes['native'];
    }

    /**
     * {@inheritdoc}
     */
    public function isImported($element)
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
    public function getLocation($element)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getView($element)
    {
        return null;
    }
}