<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Spiral\Validation\ValueInterface;

/**
 * Accessors used to mock access to model field, control value setting, serializing and etc.
 */
interface AccessorInterface extends ValueInterface, \JsonSerializable
{
    /**
     * Accessors creation flow is unified and must be performed without Container for performance
     * reasons.
     *
     * @param mixed  $data
     * @param object $parent
     */
    public function __construct($data, $parent);

    /**
     * Must embed accessor to another parent model. Allowed to clone itself.
     *
     * @param object $parent
     */
    public function embed($parent);

    /**
     * Change mocked data.
     *
     * @param mixed $data
     */
    public function setData($data);

    /**
     * Serialize mocked data to be stored in database or retrieved by user.
     *
     * @return mixed
     */
    public function serializeData();
}