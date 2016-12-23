<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use MongoDB\Driver\Cursor;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\InstantiatorInterface;

/**
 * Iterates over given cursor and convert its values in a proper objects using instantiation
 * manager. Attention, this class is very important as it provides ability to story inherited
 * documents in one collection.
 *
 * Note: since new mongo drivers arrived you can emulate same functionality using '__pclass'
 * property.
 */
class CursorInstantiator extends \IteratorIterator
{
    /**
     * @var Cursor
     */
    private $cursor;

    /**
     * @var InstantiatorInterface
     */
    private $instantiator;

    /**
     * @param Cursor                $cursor
     * @param InstantiatorInterface $instantiator
     */
    public function __construct(Cursor $cursor, InstantiatorInterface $instantiator)
    {
        //Ensuring cursor fetch types
        $cursor->setTypeMap([
            'root'     => 'array',
            'document' => 'array',
            'array'    => 'array'
        ]);

        parent::__construct($cursor);
        $this->instantiator = $instantiator;
    }

    /**
     * @return Cursor
     */
    public function getCursor(): Cursor
    {
        return $this->cursor;
    }

    /**
     * @return \Spiral\ODM\CompositableInterface
     */
    public function current(): CompositableInterface
    {
        return $this->instantiator->instantiate(parent::current());
    }
}