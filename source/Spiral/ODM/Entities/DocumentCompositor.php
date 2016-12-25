<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\ODM\Entities;

use Spiral\ODM\ODMInterface;

/**
 * Provides ability to composite multiple documents in a form of array.
 */
class DocumentCompositor
{
    /**
     * @var string
     */
    private $class;

    /**
     * @var array
     */
    private $data;

    /**
     * @var ODMInterface
     */
    protected $odm;

    /**
     * @param string       $class
     * @param array        $data
     * @param ODMInterface $odm
     */
    public function __construct(string $class, array $data, ODMInterface $odm)
    {
        $this->class = $class;
        $this->data = $data;
        $this->odm = $odm;
    }
}