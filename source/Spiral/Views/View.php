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
use Spiral\Debug\Traits\BenchmarkTrait;

class View extends Component implements ViewInterface
{
    /**
     * For render benchmarking.
     */
    use BenchmarkTrait;

    /**
     * Compiled view in a form of PHP file.
     *
     * @var string
     */
    protected $filename = '';

    /**
     * @var array
     */
    protected $data = [];

    /**
     * @var string
     */
    protected $namespace = '';

    /**
     * @var string
     */
    protected $view = '';

    /**
     * {@inheritdoc}
     *
     * @param ViewsInterface $views
     * @param string         $namespace
     * @param string         $view
     * @param array          $data
     * @param string         $filename Pre-defined view filename.
     */
    public function __construct(
        ViewsInterface $views,
        $namespace,
        $view,
        array $data = [],
        $filename = ''
    )
    {
        if (empty($this->filename = $filename))
        {
            $this->filename = $views->getFilename($namespace, $view);
        }

        $this->namespace = $namespace;
        $this->view = $view;

        $this->data = $data;
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
    public function render()
    {
        //Benchmarking context
        $context = $this->namespace . ViewsInterface::NS_SEPARATOR . $this->view;

        $this->benchmark('render', $context);
        ob_start();

        extract($this->data, EXTR_OVERWRITE);
        include $this->filename;

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