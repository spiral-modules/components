<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */

namespace Spiral\ORM;

use Spiral\Models\IdentifiedInterface;

/**
 * Generic ORM contract for records.
 */
interface RecordInterface extends IdentifiedInterface
{
    /**
     * Due setContext() method and entity cache of ORM any custom initiation code in constructor
     * must not depends on database data.
     *
     * @see Component::staticContainer()
     * @see setContext
     * @param array      $data
     * @param bool|false $loaded
     * @param ORM|null   $orm
     * @param array      $ormSchema
     */
    public function __construct(
        array $data = [],
        $loaded = false,
        ORM $orm = null,
        array $ormSchema = []
    );

    /**
     * Record context must be updated in cases where single record instance can be accessed from
     * multiple places, context must not change record fields but might overwrite pivot data or
     * clarify loaded relations.
     *
     * @param array $context
     * @return $this
     */
    public function setContext(array $context);
}