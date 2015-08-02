<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Selector\Loaders;

use Spiral\ORM\ORM;
use Spiral\ORM\Selector;
use Spiral\ORM\Selector\Loader;

class RootLoader extends Loader
{
    /**
     * RootLoader always work via INLOAD.
     */
    const LOAD_METHOD = Selector::INLOAD;

    /**
     * {@inheritdoc}
     */
    public function __construct(ORM $orm, $container, array $definition = [], Loader $parent = null)
    {
        $this->orm = $orm;
        $this->schema = $definition;

        //No need for aliases
        $this->options['method'] = Selector::INLOAD;

        //Primary table will be named under it's declared table name by default (without prefix)
        $this->options['alias'] = $this->schema[ORM::E_TABLE];

        $this->columns = array_keys($this->schema[ORM::E_COLUMNS]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureSelector(Selector $selector)
    {
        if (empty($this->loaders) && empty($this->joiners))
        {
            //No need to create any aliases
            return;
        }

        parent::configureSelector($selector);
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifySelector(Selector $selector)
    {
        //Nothing to do
    }
}


