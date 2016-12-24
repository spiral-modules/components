<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM;

abstract class Document extends DocumentEntity
{
    /**
     * Associated collection and database names, by default will be resolved based on a class name.
     */
    const DATABASE   = null;
    const COLLECTION = null;

    /**
     * Set of indexes to be created for associated collection. Use "@options" for additional
     * index options.
     *
     * Example:
     * const INDEXES = [
     *      ['email' => 1, '@options' => ['unique' => true]],
     *      ['name' => 1]
     * ];
     *
     * @link http://php.net/manual/en/mongocollection.ensureindex.php
     * @var array
     */
    const INDEXES = [];

    //primary key?
    //isLoaded?

    public function save()
    {

    }

    public function delete()
    {
    }
}