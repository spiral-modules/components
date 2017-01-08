<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Models;

/**
 * Provides ability to identify entity by it's primary key and state.
 */
interface IdentifiedInterface
{
    /**
     * Indication that entity was fetched from it's primary source (database usually) and not just
     * created. Flag must be set to true once entity will be successfully saved.
     *
     * @return bool
     */
    public function isLoaded(): bool;

    /**
     * Primary entity key if any. Can return as scalar values as objects or even arrays in case
     * of compound primary keys.
     *
     * @todo: think about having this method
     * @return mixed|null
     */
    public function primaryKey();
}