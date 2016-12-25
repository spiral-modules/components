<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Helpers;

use Spiral\Models\AccessorInterface;
use Spiral\Models\EntityInterface;
use Spiral\ODM\CompositableInterface;
use Spiral\ODM\DocumentEntity;
use Spiral\ODM\Entities\DocumentSelector;
use Spiral\ODM\Exceptions\AggregationException;
use Spiral\ODM\ODMInterface;
use Spiral\ODM\Schemas\Definitions\AggregationDefinition;

/**
 * Provides ability to configure ODM Selector based on values and query template provided by source
 * model.
 *
 * @todo add sorts and limits
 * @see  AggregationDefinition
 */
class AggregationHelper
{
    /**
     * @var DocumentEntity
     */
    private $source;

    /**
     * @var array
     */
    private $schema = [];

    /**
     * @var ODMInterface
     */
    protected $odm;

    /**
     * @param DocumentEntity $source
     * @param array          $schema DocumentEntity schema.
     * @param ODMInterface   $odm
     */
    public function __construct(DocumentEntity $source, array $schema, ODMInterface $odm)
    {
        $this->source = $source;
        $this->schema = $schema;
        $this->odm = $odm;
    }

    /**
     * Create aggregation (one or many) based on given source values.
     *
     * @param string $aggregation
     *
     * @return DocumentSelector|CompositableInterface
     *
     * @throws AggregationException
     */
    public function createAggregation(string $aggregation)
    {
        if (!isset($this->schema[DocumentEntity::SH_AGGREGATIONS][$aggregation])) {
            throw new AggregationException(
                "Undefined aggregation '{$aggregation}' in '" . get_class($this->source) . "'"
            );
        }

        $schema = $this->schema[DocumentEntity::SH_AGGREGATIONS][$aggregation];

        //Let's create selector
        $selector = new DocumentSelector(
            $this->odm->collection($schema[1]),
            $schema[1],
            $this->odm
        );

        //Ensure selection query
        $selector = $this->configureSelector($selector, $schema);

        if ($schema[0] == DocumentEntity::ONE) {
            return $selector->findOne();
        }

        return $selector;
    }

    /**
     * Configure DocumentSelector using aggregation schema.
     *
     * @param DocumentSelector $selector
     * @param array            $aggregation
     *
     * @return DocumentSelector
     */
    protected function configureSelector(
        DocumentSelector $selector,
        array $aggregation
    ): DocumentSelector {
        //@see AggregationDefinition
        $query = $aggregation[2];

        //Mounting selection values
        array_walk_recursive($query, function (&$value) {
            if (strpos($value, 'self::') === 0) {
                $value = $this->findValue(substr($value, 6));
            }
        });

        return $selector->where($query);
    }

    /**
     * Find field value using dot notation.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    private function findValue(string $name)
    {
        $source = $this->source;

        $path = explode('.', $name);
        foreach ($path as $step) {
            if ($source instanceof EntityInterface) {
                if (!$source->hasField($step)) {
                    return null;
                }

                //Sub entity or field
                $source = $source->getField($step);
                continue;
            }

            if ($source instanceof AccessorInterface) {
                $source = $source->packValue();
                continue;
            }

            if (is_array($source) && array_key_exists($step, $source)) {
                $source = &$source[$step];
                continue;
            }

            //Unable to resolve value, an exception required here
            return null;
        }

        return $source;
    }
}