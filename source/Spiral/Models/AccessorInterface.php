<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */

namespace Spiral\Models;

use Spiral\Models\Exceptions\AccessorExceptionInterface;

/**
 * Accessors used to mock access to model field, control value setting, serializing and etc.
 */
interface AccessorInterface extends \JsonSerializable
{
    //By internal agreement accessors will receive value and accessor context (field, entity)
    //public function __construct($value, array $context = []);

    /**
     * Change value of accessor, no keyword "set" used to keep compatibility with model magic
     * methods. Attention, method declaration MUST contain internal validation and filters, MUST NOT
     * affect mocked data directly.
     *
     * @see fetchValue
     *
     * @param mixed $data
     *
     * @throws AccessorExceptionInterface
     */
    public function mountValue($data);

    /**
     * Convert object data into serialized value (array or string for example).
     *
     * @return mixed
     *
     * @throws AccessorExceptionInterface
     */
    public function fetchValue();
}