<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Tokenizer;

use Spiral\Tokenizer\Reflections\ReflectionInvocation;

/**
 * Must provide generic information about given filename.
 */
interface ReflectionFileInterface
{
    /**
     * Reflection filename.
     *
     * @return string
     */
    public function getFilename();

    /**
     * List of declared function names.
     *
     * @return array
     */
    public function getFunctions();

    /**
     * List of declared classe names.
     *
     * @return array
     */
    public function getClasses();

    /**
     * List of declared trait names.
     *
     * @return array
     */
    public function getTraits();

    /**
     * List of declared interface names.
     *
     * @return array
     */
    public function getInterfaces();

    /**
     * Indication that file contains require/include statements.
     *
     * @return bool
     */
    public function hasIncludes();

    /**
     * Locate and return list of every method or function call in specified file. Only static class
     * methods will be indexed.
     *
     * @return ReflectionInvocation[]
     */
    public function getInvocations();
}