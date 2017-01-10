<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ORM\Entities\Nodes\Traits;

/**
 * Trait provides ability for Node to ensure that given data is unique in selection.
 */
trait DuplicateTrait
{
    /**
     * @invisible
     * @var array
     */
    private $duplicateCriteria = [];

    /**
     * Aggregated duplicate data.
     *
     * @invisible
     * @var array
     */
    private $duplicates = [];

    /**
     * In many cases (for example if you have inload of HAS_MANY relation) record data can be
     * replicated by many result rows (duplicated). To prevent wrong data linking we have to
     * deduplicate such records. This is only internal loader functionality and required due data
     * tree are built using php references.
     *
     * Method will return true if data is unique handled before and false in opposite case.
     * Provided data array will be automatically linked with it's unique state using references.
     *
     * @param array $data Reference to parsed record data, reference will be pointed to valid and
     *                    existed data segment if such data was already parsed.
     *
     * @return bool
     */
    protected function deduplicate(array &$data): bool
    {
        $criteria = $this->duplicateCriteria($data);

        if (isset($this->duplicates[$criteria])) {
            //Duplicate is presented, let's reduplicate
            $data = $this->duplicates[$criteria];

            //Duplicate is presented
            return false;
        }

        //Remember record to prevent future duplicates
        $this->duplicates[$criteria] = &$data;

        return true;
    }

    /**
     * Calculate duplication criteria.
     *
     * @param array $data
     *
     * @return mixed
     */
    protected function duplicateCriteria(array &$data)
    {
        if (empty($this->duplicateCriteria)) {
            //It is recommended to use primary keys in every record as it will speed up de-duplication.
            $criteria = serialize($data);

            return $criteria;
        } elseif (count($this->duplicateCriteria) == 1) {
            $criteria = $data[$this->duplicateCriteria[0]];

            return $criteria;
        } else {
            $criteria = [];
            foreach ($this->duplicateCriteria as $column) {
                $criteria[] = $data[$column];
            }

            $criteria = join('.', $criteria);

            return $criteria;
        }
    }
}