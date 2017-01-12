<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities;

class RelationBucket
{

    /**
     * Extract relations data from given entity fields.
     *
     * @param array $data
     */
    public function extractRelations(array &$data)
    {
//        //Fetch all relations
//        $relations = array_intersect_key($data, $this->recordSchema[self::SH_RELATIONS]);
//
//        foreach ($relations as $name => $relation) {
//            $this->relations[$name] = $relation;
//            unset($data[$name]);
//        }
    }
}