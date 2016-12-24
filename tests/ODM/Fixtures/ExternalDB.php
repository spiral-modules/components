<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\Document;

class ExternalDB extends Document
{
    const DATABASE = 'external';

    const SCHEMA = [
        '_id' => 'MongoId'
    ];
}