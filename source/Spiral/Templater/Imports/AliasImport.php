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
 * {@inheritdoc}
 *
 * Simple aliased based import, declared relation between tag name and it's location. Element alias
 * must be located in "as" attribute caused import, location in "path" attribute (will be passed
 * thought Templater->fetchLocation()).
 */
class AliasImport implements ImportInterface
{
    /**
     * @var string
     */
    private $alias = '';

    /**
     * @var mixed
     */
    private $location = null;

    /**
     * {@inheritdoc}
     */
    public function __construct(Templater $templater, array $token)
    {
        $attributes = $token[HtmlTokenizer::TOKEN_ATTRIBUTES];

        $this->location = $templater->fetchLocation($attributes['path'], $token);
        $this->alias = $attributes['as'];
    }

    /**
     * {@inheritdoc}
     */
    public function isImported($element, array $token)
    {
        return strtolower($element) == strtolower($this->alias);
    }

    /**
     * {@inheritdoc}
     */
    public function getLocation($element, array $token)
    {
        return $this->location;
    }
}