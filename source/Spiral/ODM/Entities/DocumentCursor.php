<?php
/**
 * Spiral Framework, Core Components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use MongoDB\Driver\Cursor;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\ODMInterface;

/**
 * Iterates over given cursor and convert its values in a proper objects using instantiation
 * manager. Attention, this class is very important as it provides ability to story inherited
 * documents in one collection.
 *
 * Note: since new mongo drivers arrived you can emulate same functionality using '__pclass'
 * property.
 *
 * Note #2: ideally this class to be tested, but Cursor is final class and it seems unrealistic
 * without adding extra layer which will harm core readability .
 */
class DocumentCursor extends \IteratorIterator
{
    /**
     * @var Cursor
     */
    private $cursor;

    /**
     * @var string
     */
    private $class;

    /**
     * @var ODMInterface
     */
    private $odm;

    /**
     * @param Cursor       $cursor
     * @param string       $class
     * @param ODMInterface $odm
     */
    public function __construct(Cursor $cursor, string $class, ODMInterface $odm)
    {
        //Ensuring cursor fetch types
        $cursor->setTypeMap([
            'root'     => 'array',
            'document' => 'array',
            'array'    => 'array'
        ]);

        parent::__construct($cursor);

        $this->class = $class;
        $this->odm = $odm;
    }

    /**
     * @return \Spiral\ODM\CompositableInterface
     */
    public function current(): CompositableInterface
    {
        return $this->odm->create($this->class, parent::current(), false);
    }

    /**
     * @return Cursor
     */
    public function getCursor(): Cursor
    {
        return $this->cursor;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return $this->fetchAll();
    }

    /**
     * Fetch all documents.
     *
     * @return CompositableInterface[]
     */
    public function fetchAll(): array
    {
        $result = [];
        foreach ($this as $item) {
            $result[] = $item;
        }

        return $result;
    }
}