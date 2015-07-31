<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Tokenizer;

/**
 * Tokenizers must support different classes with parsed PHP tokens (default functionality of
 * token_get_all) and ability to find project classes with specified parent or namespace.
 */
interface TokenizerInterface
{
    /**
     * Token array constants.
     */
    const TYPE = 0;
    const CODE = 1;
    const LINE = 2;

    /**
     * Fetch PHP tokens for specified filename. Usually links to token_get_all() function. Every token
     * MUST be converted into array.
     *
     * @param string $filename
     * @return array
     */
    public function fetchTokens($filename);

    /**
     * Index all available files and generate list of found classes with their names and filenames.
     * Unreachable classes or files with conflicts must be skipped. This is SLOW method, should be
     * used only for static analysis.
     *
     * Output format:
     * $result['CLASS_NAME'] = [
     *      'class'    => 'CLASS_NAME',
     *      'filename' => 'FILENAME',
     *      'abstract' => 'ABSTRACT_BOOL'
     * ]
     *
     * @param mixed  $parent    Class, interface or trait parent. By default - null (all classes).
     *                          Parent (class) will also be included to classes list as one of results.
     * @param string $namespace Only classes in this namespace will be retrieved, empty by default
     *                          (all namespaces).
     * @param string $postfix   Only classes with such postfix will be analyzed, empty by default.
     * @return array
     */
    public function getClasses($parent = null, $namespace = '', $postfix = '');
}