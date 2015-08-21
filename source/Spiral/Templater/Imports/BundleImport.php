<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Templater\Imports;

use Spiral\Templater\Exceptions\TemplaterException;
use Spiral\Templater\HtmlTokenizer;
use Spiral\Templater\ImportInterface;
use Spiral\Templater\Templater;

/**
 * Bundle import can import imports declared in outer view (bundle). This import is very useful
 * when module or component declared many different view sources using multiple imports. We
 * excepting bundle location in "bundle" attribute of token caused import creation.
 */
class BundleImport implements ImportInterface
{
    /**
     * Importers fetched from view bundle.
     *
     * @var ImportInterface[]
     */
    protected $imports = [];

    /**
     * {@inheritdoc}
     */
    public function __construct(Templater $templater, array $token)
    {
        $attributes = $token[HtmlTokenizer::TOKEN_ATTRIBUTES];
        $this->fetchImports($templater, $templater->fetchLocation($attributes['bundle'], $token),
            $token);
    }

    /**
     * {@inheritdoc}
     */
    public function isImported($element, array $token)
    {
        foreach ($this->imports as $importer) {
            if ($importer->isImported($element, $token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function getLocation($element, array $token)
    {
        foreach ($this->imports as $importer) {
            if ($importer->isImported($element, $token)) {
                return $importer->getLocation($element, $token);
            }
        }

        return null;
    }

    /**
     * Fetch imports from declared location.
     *
     * @param Templater $templater
     * @param mixed     $location
     * @param array     $token
     * @throws TemplaterException
     */
    protected function fetchImports(Templater $templater, $location, array $token)
    {
        //Let's create bundle node
        $bundle = $templater->createNode($location, '', $token);

        /**
         * We expecting to fetch imports located in templater associated with bundle node.
         *
         * @var Templater $bundleTemplater
         */
        $bundleTemplater = $bundle->getSupervisor();
        if (!$bundleTemplater instanceof Templater) {
            throw new TemplaterException("BundleImport must be executed using Templater.", $token);
        }

        //We can fetch all importers from our bundle view
        $this->imports = $bundleTemplater->getImports();
    }
}