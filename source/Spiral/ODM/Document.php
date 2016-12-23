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
     * Document entities are able to define set of indexes for related collection.
     */
    const INDEXES = [];
}