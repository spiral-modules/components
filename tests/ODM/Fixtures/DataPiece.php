<?php
/**
 * spiral-empty.dev
 *
 * @author Wolfy-J
 */
namespace Spiral\Tests\ODM\Fixtures;

use Spiral\ODM\DocumentEntity;

class DataPiece extends DocumentEntity
{
    const SCHEMA = [
        'value'     => 'string',
        'something' => 'int'
    ];

    //to allow parent->piece = [];
    const FILLABLE = [
        'value'
    ];
}