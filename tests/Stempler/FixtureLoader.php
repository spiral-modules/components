<?php
/**
 * components
 *
 * @author    Wolfy-J
 */
namespace Spiral\Tests\Stempler;

use Spiral\Files\FilesInterface;
use Spiral\Stempler\LoaderInterface;

/**
 * RIP Off from default spiral view loader.
 */
class FixtureLoader implements LoaderInterface
{
    const VIEW_FILENAME  = 0;
    const VIEW_NAMESPACE = 1;
    const VIEW_NAME      = 2;

    /**
     * Such extensions will automatically be added to every file but only if no other extension
     * specified in view name. As result you are able to render "home" view, instead of "home.twig".
     *
     * @var string|null
     */
    protected $extension = 'php';

    /**
     * Available view namespaces associated with their directories.
     *
     * @var array
     */
    protected $namespaces = [];

    /**
     * @var FilesInterface
     */
    protected $files = null;

    /**
     * @param array          $namespaces
     * @param FilesInterface $files
     */
    public function __construct(array $namespaces, FilesInterface $files)
    {
        $this->namespaces = $namespaces;
        $this->files = $files;
    }

    /**
     * {@inheritdoc}
     */
    public function getSource($path): string
    {
        return $this->files->read($this->locateView($path)[self::VIEW_FILENAME]);
    }

    /**
     * {@inheritdoc}
     */
    public function localFilename(string $path): string
    {
        return $this->locateView($path)[self::VIEW_FILENAME];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchNamespace(string $path): string
    {
        return $this->locateView($path)[self::VIEW_NAMESPACE];
    }

    /**
     * {@inheritdoc}
     */
    public function fetchName(string $path): string
    {
        return $this->locateView($path)[self::VIEW_NAME];
    }

    /**
     * Locate view filename based on current loader settings.
     *
     * @param string $path
     *
     * @return array [namespace, name]
     *
     * @throws \LogicException
     */
    protected function locateView(string $path): array
    {
        //Making sure requested name is valid
        $this->validateName($path);

        list($namespace, $filename) = $this->parsePath($path);

        foreach ($this->namespaces[$namespace] as $directory) {
            //Seeking for view filename
            if ($this->files->exists($directory . $filename)) {
                return [
                    self::VIEW_FILENAME  => $directory . $filename,
                    self::VIEW_NAMESPACE => $namespace,
                    self::VIEW_NAME      => $this->resolveName($filename)
                ];
            }
        }

        throw new \LogicException("Unable to locate view '{$filename}' in namespace '{$namespace}'");
    }

    /**
     * Fetch namespace and filename from view name or force default values.
     *
     * @param string $path
     *
     * @return array
     * @throws \LogicException
     */
    protected function parsePath(string $path): array
    {
        //Cutting extra symbols (see Twig)
        $filename = preg_replace('#/{2,}#', '/', str_replace('\\', '/', (string)$path));

        if (strpos($filename, '.') === false && !empty($this->extension)) {
            //Forcing default extension
            $filename .= '.' . $this->extension;
        }

        if (strpos($filename, ':') !== false) {
            return explode(':', $filename);
        }

        //Twig like namespaces
        if (isset($filename[0]) && $filename[0] == '@') {
            if (($separator = strpos($filename, '/')) === false) {
                throw new \LogicException(sprintf(
                    'Malformed namespaced template name "%s" (expecting "@namespace/template_name")',
                    $path
                ));
            }

            $namespace = substr($filename, 1, $separator - 1);
            $filename = substr($filename, $separator + 1);

            return [$namespace, $filename];
        }

        //Let's force default namespace
        return ['default', $filename];
    }

    /**
     * Make sure view filename is OK. Same as in twig.
     *
     * @param string $name
     *
     * @throws \LogicException
     */
    protected function validateName(string $name)
    {
        if (false !== strpos($name, "\0")) {
            throw new \LogicException('A template name cannot contain NUL bytes');
        }

        $name = ltrim($name, '/');
        $parts = explode('/', $name);
        $level = 0;
        foreach ($parts as $part) {
            if ('..' === $part) {
                --$level;
            } elseif ('.' !== $part) {
                ++$level;
            }

            if ($level < 0) {
                throw new \LogicException(sprintf(
                    'Looks like you try to load a template outside configured directories (%s)',
                    $name
                ));
            }
        }
    }

    /**
     * Resolve view name based on filename (depends on current extension settings).
     *
     * @param string $filename
     *
     * @return string
     */
    private function resolveName(string $filename): string
    {
        if (empty($this->extension)) {
            return $filename;
        }

        return substr($filename, 0, -1 * (1 + strlen($this->extension)));
    }
}