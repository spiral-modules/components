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
     * View filename, usually cached.
     *
     * @var string
     */
    protected $filename = '';

    /**
     * Runtime data has to be passed to view.
     *
     * @var array
     */
    protected $data = [];

    /**
     * View namespace.
     *
     * @var string
     */
    protected $namespace = '';

    /**
     * View name.
     *
     * @var string
     */
    protected $view = '';

    /**
     * View instance binded to specified view file (file has to be already pre-processed).
     *
     * @param ViewsInterface $viewFacade ViewManager component.
     * @param string               $filename   Compiled view file.
     * @param array                $data       Runtime data passed by controller or model, should be
     *                                         injected
     *                                         into view.
     * @param string               $namespace  View namespace.
     * @param string               $view       View name.
     */
    public function __construct(
        ViewsInterface $viewFacade,
        $filename,
        array $data = [],
        $namespace,
        $view
    )
    {
        $this->filename = $filename;
        $this->data = $data;

        $this->namespace = $namespace;
        $this->view = $view;
    }

    /**
     * Perform view file rendering. View data has to be associated array and will be exported using
     * extract() function and set of local view variables, here variable name will be identical to
     * array key.
     *
     * Every view file will be pro-processed using view processors (also defined in view config) before
     * rendering, result of pre-processing will be stored in names cache file to speed-up future
     * renderings.
     *
     * @return string
     */
    public function render()
    {
        !empty($this->view) && $this->benchmark('render', $this->namespace . ':' . $this->view);

        //RENDERING PROCESS
        ob_start();
        extract($this->data, EXTR_OVERWRITE);
        include $this->filename;
        $result = ob_get_clean();
        //END RENDERING PROCESS

        !empty($this->view) && $this->benchmark('render', $this->namespace . ':' . $this->view);

        return $result;
    }

    /**
     * Alias for render method.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->render();
    }
}