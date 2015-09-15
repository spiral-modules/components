<?php

/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\ORM\Entities\Loaders;

use Spiral\ORM\Entities\Loader;
use Spiral\ORM\Entities\Selector;
use Spiral\ORM\LoaderInterface;
use Spiral\ORM\ORM;

class RootLoader extends Loader
{
    /**
     * RootLoader always work via INLOAD.
     */
    const LOAD_METHOD = self::INLOAD;

    /**
     * {@inheritdoc}
     *
     * We don't need to initiate parent constructor as root loader is pretty simple and used only
     * for primary record parsing without any conditions.
     */
    public function __construct(
        ORM $orm,
        $container,
        array $definition = [],
        LoaderInterface $parent = null
    ) {
        $this->orm = $orm;
        $this->schema = $definition;

        //No need for aliases
        $this->options['method'] = self::INLOAD;

        //Primary table will be named under it's declared table name by default (without prefix)
        $this->options['alias'] = $this->schema[ORM::M_ROLE_NAME];

        $this->dataColumns = array_keys($this->schema[ORM::M_COLUMNS]);
    }

    /**
     * {@inheritdoc}
     */
    public function configureSelector(Selector $selector)
    {
        if (empty($this->loaders) && empty($this->joiners)) {
            //No need to create any column aliases
            return;
        }

        parent::configureSelector($selector);
    }

    /**
     * {@inheritdoc}
     */
    protected function clarifySelector(Selector $selector)
    {
        //Nothing to do for root loader, no conditions required
    }
}