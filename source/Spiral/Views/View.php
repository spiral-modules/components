<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Views;

use Spiral\Core\Component;
use Spiral\Core\Container\SaturableInterlace;
use Spiral\Debug\Traits\BenchmarkTrait;

/**
 * Default view implementation can work with
 */
class View extends Component implements ViewInterface, SaturableInterlace
{
    /**
     * For render benchmarking.
     */
    use BenchmarkTrait;

    /**
     * @var string
     */
    private $compiledFilename = '';

    /**
     * @var array
     */
    private $data = [];

    /**
     * @var string
     */
    private $namespace = '';

    /**
     * @var string
     */
    private $view = '';

    /**
     * {@inheritdoc}
     */
    public function __construct(ViewsInterface $views, $namespace, $view, array $data = [])
    {
        $this->namespace = $namespace;
        $this->view = $view;

        $this->data = $data;
    }

    /**
     * @param string $compiledFilename
     */
    public function saturate($compiledFilename)
    {
        $this->compiledFilename = $compiledFilename;
    }

    /**
     * {@inheritdoc}
     */
    public function set($name, $value)
    {
        $this->data[$name] = $value;
    }

    /**
     * {@inheritdoc}
     */
    final public function render()
    {
        //Benchmarking context
        $context = $this->namespace . ViewsInterface::NS_SEPARATOR . $this->view;

        $this->benchmark('render', $context);
        ob_start();

        extract($this->data, EXTR_OVERWRITE);
        include $this->compiledFilename;

        $result = ob_get_clean();
        $this->benchmark('render', $context);

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString()
    {
        return $this->render();
    }
}