<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Accessors;

use Spiral\Database\Driver;
use Spiral\ODM\Document;
use Spiral\ORM\ModelAccessorInterface;

abstract class JsonDocument extends Document implements ModelAccessorInterface
{
    /**
     * {@inheritdoc}
     */
    public function serializeData()
    {
        return json_encode(parent::serializeData());
    }

    /**
     * {@inheritdoc}
     */
    public function compileUpdates($field = '')
    {
        return $this->serializeData();
    }

    /**
     * {@inheritdoc}
     */
    public function defaultValue(Driver $driver)
    {
        return $this->serializeData();
    }

    /**
     * {@inheritdoc}
     */
    public function setData($data)
    {
        if (is_string($data))
        {
            $data = json_decode($data);
        }

        return parent::setData($data);
    }
}