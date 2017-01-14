<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM;

use Spiral\Models\EntityInterface;
use Spiral\ORM\Exceptions\RecordException;
use Spiral\ORM\Exceptions\RelationException;

interface RecordInterface extends EntityInterface
{
    /**
     * {@inheritdoc}
     *
     * @param bool $queueRelations
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueStore(bool $queueRelations = true): ContextualCommandInterface;

    /**
     * {@inheritdoc}
     *
     * @throws RecordException
     * @throws RelationException
     */
    public function queueDelete(): CommandInterface;
}