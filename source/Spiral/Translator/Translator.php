<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator;

use Spiral\Core\Component;
use Spiral\Core\Container\SingletonInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Translator\Configs\TranslatorConfig;
use Spiral\Translator\Exceptions\LanguageException;
use Spiral\Translator\Exceptions\TranslatorException;

/**
 * Default spiral translator implementation.
 */
class Translator extends Component implements SingletonInterface, TranslatorInterface
{
    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

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
     * @var TranslatorConfig
     */
    protected $config = null;

    /**
     * @invisible
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param TranslatorConfig     $config
     * @param HippocampusInterface $memory
     * @throws LanguageException
     */
    public function __construct(TranslatorConfig $config, HippocampusInterface $memory)
    {
        $this->config = $config;
        $this->memory = $memory;

        $this->setLanguage($this->config->defaultLanguage());
    }

    /**
     * {@inheritdoc}
     */
    public function setLanguage($language)
    {
        if (!$this->config->hasLanguage($language)) {
            throw new LanguageException("Invalid language '{$language}', no presets found.");
        }

        //Cleaning all bundles
        $this->bundles = [];

        $this->language = $language;
        $this->languageOptions = $this->config->languageOptions($language);
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
        $this->loadBundle($bundle = $this->config->pluralsBundle());

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

        $pluralizer = $this->config->languagePluralizer($language);

        return $this->pluralizers[$language] = new $pluralizer;
    }

    /**
     * {@inheritdoc}
     */
    public function knows($bundle, $string)
    {
        $this->loadBundle($bundle);

        return isset($this->bundles[$bundle][$string]);
    }

    /**
     * {@inheritdoc}
     */
    public function set($bundle, $string, $translation = '')
    {
        if (empty($translation)) {
            $translation = $string;
        }

        if ($bundle == $this->config->pluralsBundle()) {
            if (!is_array($translation) || count($translation) != $this->pluralizer()->countForms()) {
                throw new TranslatorException(
                    "Translation for plural phrases must include all phrase forms."
                );
            }
        }

        $this->loadBundle($bundle);
        $this->bundles[$bundle][$string] = $translation;
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

        $this->bundles[$bundle] = $this->memory->loadData(
            $bundle,
            $this->languageOptions['directory']
        );

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