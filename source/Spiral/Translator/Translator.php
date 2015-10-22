<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Singleton;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Translator\Exceptions\LanguageException;

/**
 * Default spiral translator implementation.
 */
class Translator extends Singleton implements TranslatorInterface
{
    /**
     * Has configuration.
     */
    use ConfigurableTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Configuration section.
     */
    const CONFIG = 'translator';

    /**
     * @var string
     */
    private $language = '';

    /**
     * @var array
     */
    private $languageOptions = [];

    /**
     * @var PluralizerInterface[]
     */
    private $pluralizers = [];

    /**
     * Language bundles.
     *
     * @var array
     */
    private $bundles = [];

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param ConfiguratorInterface $configurator
     * @param HippocampusInterface  $memory
     * @throws LanguageException
     */
    public function __construct(ConfiguratorInterface $configurator, HippocampusInterface $memory)
    {
        $this->config = $configurator->getConfig(static::CONFIG);
        $this->memory = $memory;

        $this->setLanguage($this->config['default']);
    }

    /**
     * {@inheritdoc}
     */
    public function setLanguage($language)
    {
        if (!isset($this->config['languages'][$language])) {
            throw new LanguageException("Invalid language '{$language}', no presets found.");
        }

        //Cleaning all bundles
        $this->bundles = [];

        $this->language = $language;
        $this->languageOptions = $this->config['languages'][$language];
    }

    /**
     * {@inheritdoc}
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * {@inheritdoc}
     */
    public function translate($bundle, $string, $options = [])
    {
        $this->loadBundle($bundle);

        if (!isset($this->bundles[$bundle][$string = $this->normalize($string)])) {
            $this->bundles[$bundle][$string] = func_get_arg(1);
            $this->saveBundle($bundle);
        }

        if (func_num_args() == 2) {
            //Just simple text line
            return $this->bundles[$bundle][$string];
        }

        if (is_array(func_get_arg(2))) {
            return \Spiral\interpolate($this->bundles[$bundle][$string], func_get_arg(2));
        }

        $arguments = array_slice(func_get_args(), 1);
        $arguments[0] = $this->bundles[$bundle][$string];

        //Formatting
        return call_user_func_array('sprintf', $arguments);
    }

    /**
     * {@inheritdoc}
     *
     * You can use custom pluralizer to create more complex word forms, for example day postfix and
     * etc. The only problem will be that system will not be able to index such things
     * automatically.
     *
     * @param PluralizerInterface $pluralizer Custom pluralizer to be used.
     */
    public function pluralize(
        $phrase,
        $number,
        $format = true,
        PluralizerInterface $pluralizer = null
    ) {
        $this->loadBundle($bundle = $this->config['plurals']);

        if (empty($pluralizer)) {
            //Active pluralizer
            $pluralizer = $this->pluralizer();
        }

        if (!isset($this->bundles[$bundle][$phrase = $this->normalize($phrase)])) {
            $this->bundles[$bundle][$phrase] = array_pad(
                [],
                $pluralizer->countForms(),
                func_get_arg(0)
            );

            $this->saveBundle($bundle);
        }

        if (is_null($number)) {
            return $this->bundles[$bundle][$phrase];
        }

        return \Spiral\interpolate(
            $pluralizer->getForm($number, $this->bundles[$bundle][$phrase]),
            ['n' => $format ? number_format($number) : $number]
        );
    }

    /**
     * Get or create instance of language pluralizer.
     *
     * @param string $language Current language will be used if null specified.
     * @return PluralizerInterface
     */
    public function pluralizer($language = null)
    {
        $language = !empty($language) ? $language : $this->language;
        if (isset($this->pluralizers[$language])) {
            return $this->pluralizers[$language];
        }

        $pluralizer = $this->config['languages'][$language]['pluralizer'];

        return $this->pluralizers[$language] = new $pluralizer;
    }

    /**
     * Set string translation in specified bundle.
     *
     * @param string $bundle
     * @param string $string
     * @param string $translation
     */
    public function set($bundle, $string, $translation = '')
    {
        $this->loadBundle($bundle);
        $this->bundles[$bundle][$string] = func_num_args() == 2 ? $translation : $string;
        $this->saveBundle($bundle);
    }

    /**
     * Location language bundle from memory if not loaded already.
     *
     * @param string $bundle
     */
    protected function loadBundle($bundle)
    {
        if (isset($this->bundles[$bundle])) {
            return;
        }

        $this->bundles[$bundle] = $this->memory->loadData($bundle,
            $this->languageOptions['directory']);
        if (empty($this->bundles[$bundle])) {
            $this->bundles[$bundle] = [];
        }
    }

    /**
     * Save language bundle into memory.
     *
     * @param string $bundle
     */
    protected function saveBundle($bundle)
    {
        if (!isset($this->bundles[$bundle])) {
            return;
        }

        $this->memory->saveData(
            $bundle,
            $this->bundles[$bundle],
            $this->languageOptions['directory']
        );
    }

    /**
     * Normalize string to remove garbage spaces and new lines.
     *
     * @param string $string
     * @return string
     */
    protected function normalize($string)
    {
        return preg_replace('/\s+/', ' ', trim($string));
    }
}