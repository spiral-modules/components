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
 * Generic ORM contract for records to be constructed and updated by ORM.
 */
interface RecordInterface extends IdentifiedInterface
{
    /**
     * Due setContext() method and entity cache of ORM any custom initiation code in constructor
     * must not depends on relations data.
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
     * RecordInterface context must be updated in cases where single record instance can be
     * accessed from multiple places, context must not change record fields but might overwrite
     * pivot data or clarify loaded relations. Must be supplied in array form.
     *
     * @param array $context
     * @return $this
     */
    public function setContext(array $context);

    /**
     * Is record were fetched from databases or recently created?
     *
     * @return bool
     */
    public function isLoaded();

    /**
     * Indication that record data was deleted.
     *
     * @return bool
     */
    public function isDeleted();

    /**
     * Role name used in morphed relations to detect outer record table and class. In general case
     * must simply return unique name.
     *
     * @return string
     */
    public function recordRole();
}