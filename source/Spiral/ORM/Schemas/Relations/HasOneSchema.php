<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Schemas\Relations;

use Spiral\ORM\Record;

class HasOneSchema extends AbstractSchema
{
    /**
     * {@inheritdoc}
     */
    const OPTIONS_TEMPLATE = [
        //Let's use parent record primary key as default inner key
        Record::INNER_KEY         => '{source:primaryKey}',

        //Outer key will be based on parent record role and inner key name
        Record::OUTER_KEY         => '{source:role}_{option:innerKey}',

        //Set constraints (foreign keys) by default
        Record::CREATE_CONSTRAINT => true,

        //@link https://en.wikipedia.org/wiki/Foreign_key
        Record::CONSTRAINT_ACTION => 'CASCADE',

        //Relation allowed to create indexes in outer table
        Record::CREATE_INDEXES    => true,

        //Has one counted as not nullable by default
        Record::NULLABLE          => false,
    ];
}