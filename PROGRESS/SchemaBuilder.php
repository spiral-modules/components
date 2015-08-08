<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ODM\Entities;


class SchemaBuilders
{

    /**
     * Normalize ODM schema and export it to be used by ODM component and all documents.
     *
     * @return array
     */
    public function normalizeSchema()
    {
        $schema = [];

        foreach ($this->collections as $collection) {
            $schema[$collection->getDatabase() . '/' . $collection->getName()] = [
                ODM::C_DEFINITION => $this->classDefinition($collection->classDefinition())
            ];
        }

        foreach ($this->documents as $document) {
            if ($document->isAbstract()) {
                continue;
            }

            $documentSchema = [];
            if ($document->getCollection()) {
                $documentSchema[ODM::D_COLLECTION] = $document->getCollection();
                $documentSchema[ODM::D_DB] = $document->getDatabase();
            }

            $documentSchema[ODM::D_DEFAULTS] = $document->getDefaults();
            $documentSchema[ODM::D_HIDDEN] = $document->getHidden();
            $documentSchema[ODM::D_SECURED] = $document->getSecured();
            $documentSchema[ODM::D_FILLABLE] = $document->getFillable();

            $documentSchema[ODM::D_MUTATORS] = $document->getMutators();
            $documentSchema[ODM::D_VALIDATES] = $document->getValidates();

            $documentSchema[ODM::D_AGGREGATIONS] = [];
            foreach ($document->getAggregations() as $name => $aggregation) {
                $documentSchema[ODM::D_AGGREGATIONS][$name] = [
                    ODM::AGR_TYPE       => $aggregation['type'],
                    ODM::AGR_COLLECTION => $aggregation['collection'],
                    ODM::AGR_DB         => $aggregation['database'],
                    ODM::AGR_QUERY      => $aggregation['query']
                ];
            }

            $documentSchema[ODM::D_COMPOSITIONS] = array_keys($document->getCompositions());

            ksort($documentSchema);
            $schema[$document->getClass()] = $documentSchema;
        }

        return $schema;
    }

    /**
     * Create all required collection indexes.
     *
     * @param ODM $odm ODM component is required as source for databases and collections.
     */
    public function createIndexes(ODM $odm)
    {
        foreach ($this->getDocumentSchemas() as $document) {
            if (!$indexes = $document->getIndexes()) {
                continue;
            }

            $collection = $odm->db($document->getDatabase())->odmCollection(
                $document->getCollection()
            );

            foreach ($indexes as $index) {
                $options = [];
                if (isset($index[Document::INDEX_OPTIONS])) {
                    $options = $index[Document::INDEX_OPTIONS];
                    unset($index[Document::INDEX_OPTIONS]);
                }

                $collection->ensureIndex($index, $options);
            }
        }
    }

    /**
     * Normalizing class detection definition.
     *
     * @param mixed $classDefinition
     * @return array
     */
    protected function classDefinition($classDefinition)
    {
        if (is_string($classDefinition)) {
            //Single collection class
            return $classDefinition;
        }

        return [
            ODM::DEFINITION         => $classDefinition['type'],
            ODM::DEFINITION_OPTIONS => $classDefinition['options']
        ];
    }
}