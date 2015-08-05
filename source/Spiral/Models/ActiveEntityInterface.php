<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Models;

use Spiral\Validation\ValidatesInterface;

/**
 * Represents generic ActiveRecord patten.
 */
interface ActiveEntityInterface extends EntityInterface, ValidatesInterface
{
    //TODO: SAVE
    //TODO: CREATE
    //TODO: DELETE
    //TODO: IS LOADED
}