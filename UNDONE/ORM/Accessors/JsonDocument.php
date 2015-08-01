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
     * Serialize object data for saving into database. No getters will be applied here.
     *
     * @return mixed
     */
    public function serializeData()
    {
        return json_encode(parent::serializeData());
    }

    /**
     * Get new field value to be send to database.
     *
     * @param string $field Name of field where model/accessor stored into.
     * @return mixed
     */
    public function compileUpdates($field = '')
    {
        return $this->serializeData();
    }

    /**
     * Accessor default value specific to driver.
     *
     * @param Driver $driver
     * @return mixed
     */
    public function defaultValue(Driver $driver)
    {
        return $this->serializeData();
    }

    /**
     * Update accessor mocked data.
     *
     * @param mixed $data
     * @return $this
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