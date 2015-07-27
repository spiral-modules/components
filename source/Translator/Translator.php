<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Translator;

use Spiral\Core\ConfiguratorInterface;
use Spiral\Core\HippocampusInterface;
use Spiral\Core\Traits\ConfigurableTrait;
use Spiral\Core\Singleton;

class Translator extends Singleton implements TranslatorInterface
{
    /**
     * Some operations should be recorded.
     */
    use ConfigurableTrait;

    /**
     * Declares to Spiral IoC that component instance should be treated as singleton.
     */
    const SINGLETON = self::class;

    /**
     * Bundle to use for short localization syntax (l function).
     */
    const DEFAULT_BUNDLE = 'default';

    /**
     * Constructed language pluralizers.
     *
     * @var PluralizerInterface[]
     */
    protected $pluralizers = [];

    /**
     * Currently selected language identifier.
     *
     * @var string
     */
    protected $language = '';

    /**
     * Options associated with currently active language, define pluralization formula, word forms
     * count and  bundles directory.
     *
     * @var array
     */
    protected $languageOptions = [];

    /**
     * Already loaded language bundles, bundle define list of associations between primary and
     * currently selected language. Bundles can be also used for "internal translating" (en => en).
     *
     * @var array
     */
    protected $bundles = [];

    /**
     * Core component.
     *
     * @var HippocampusInterface
     */
    protected $runtime = null;

    /**
     * New I18nManager component instance, while construing default language and timezone will be
     * mounted.
     *
     * @param ConfiguratorInterface $configurator
     * @param HippocampusInterface  $runtime
     */
    public function __construct(ConfiguratorInterface $configurator, HippocampusInterface $runtime)
    {
        $this->config = $configurator->getConfig($this);
        $this->runtime = $runtime;

        $this->language = $this->config['default'];
        $this->languageOptions = $this->config['languages'][$this->language];
    }

    /**
     * Change application language selection, all future translations or pluralization
     * phrases will be fetched using new language options and bundles.
     *
     * @param string $languageID Valid language identifier (en, ru, de).
     * @throws TranslatorException
     */
    public function setLanguage($languageID)
    {
        if (!isset($this->config['languages'][$languageID]))
        {
            throw new TranslatorException("Invalid language '{$languageID}', no presets found.");
        }

        //Cleaning all bundles
        $this->bundles = [];

        $this->language = $languageID;
        $this->languageOptions = $this->config['languages'][$languageID];
    }

    /**
     * Currently selected language identifier.
     *
     * @return string
     */
    public function getLanguage()
    {
        return $this->language;
    }

    /**
     * Translate and format string fetched from bundle, new strings will be automatically registered
     * in bundle with key identical to string itself. Function support embedded formatting, to enable
     * it provide arguments to insert after string.
     *
     * Examples:
     * $translator->translate('bundle', 'Some Message');
     * $translator->translate('bundle', 'Hello %s', $name);
     *
     * @param string $bundle Bundle name.
     * @param string $string String to be localized, should be sprintf compatible if formatting
     *                       required.
     * @return string
     */
    public function translate($bundle, $string)
    {
        $this->loadBundle($bundle);

        if (!isset($this->bundles[$bundle][$string = $this->normalize($string)]))
        {
            $this->bundles[$bundle][$string] = func_get_arg(1);
            $this->saveBundle($bundle);
        }

        if (func_num_args() == 2)
        {
            //Just simple text line
            return $this->bundles[$bundle][$string];
        }

        if (is_array(func_get_arg(2)))
        {
            return \Spiral\interpolate($this->bundles[$bundle][$string], func_get_arg(2));
        }

        $arguments = array_slice(func_get_args(), 1);
        $arguments[0] = $this->bundles[$bundle][$string];

        //Formatting
        return call_user_func_array('sprintf', $arguments);
    }

    /**
     * Format phase according to formula defined in selected language. Phase should include "%s" which
     * will be replaced with number provided as second argument.
     *
     * Examples:
     * $translator->pluralize("%s user", $users);
     *
     * All pluralization phases stored in same bundle defined in i18n config.
     *
     * @param string $phrase       Pluralization phase.
     * @param int    $number       Number has to be used in pluralization phrase.
     * @param bool   $formatNumber True to format number using number_format.
     * @return string
     */
    public function pluralize($phrase, $number, $formatNumber = true)
    {
        $this->loadBundle($bundle = $this->config['plurals']);

        if (!isset($this->bundles[$bundle][$phrase = $this->normalize($phrase)]))
        {
            $this->bundles[$bundle][$phrase] = array_pad(
                [],
                $this->getPluralizer()->countForms(),
                func_get_arg(0)
            );

            $this->saveBundle($bundle);
        }

        if (is_null($number))
        {
            return $this->bundles[$bundle][$phrase];
        }

        return sprintf(
            $this->getPluralizer()->getForm($number, $this->bundles[$bundle][$phrase]),
            $formatNumber ? number_format($number) : $number
        );
    }

    /**
     * Get language specific pluralizer.
     *
     * @param string $language If empty current language pluralizer will be returned.
     * @return PluralizerInterface
     */
    public function getPluralizer($language = '')
    {
        if (empty($language))
        {
            $language = $this->language;
        }

        if (isset($this->pluralizers[$language]))
        {
            return $this->pluralizers[$language];
        }

        $pluralizer = $this->config['languages'][$language]['pluralizer'];

        return $this->pluralizers[$language] = new $pluralizer;
    }

    /**
     * Load i18n bundle content to memory specific to currently selected language.
     *
     * @param string $bundle
     */
    protected function loadBundle($bundle)
    {
        if (isset($this->bundles[$bundle]))
        {
            return;
        }

        $this->bundles[$bundle] = $this->runtime->loadData(
            $bundle,
            $this->languageOptions['dataFolder']
        );

        if (empty($this->bundles[$bundle]))
        {
            $this->bundles[$bundle] = [];
        }
    }

    /**
     * Save modified i18n bundle to language specific directory.
     *
     * @param string $bundle
     */
    protected function saveBundle($bundle)
    {
        if (!isset($this->bundles[$bundle]))
        {
            return;
        }

        $this->runtime->saveData(
            $bundle,
            $this->bundles[$bundle],
            $this->languageOptions['dataFolder']
        );
    }

    /**
     * Normalizes bundle key (string) to prevent data loosing while extra lines or spaces or formatting.
     * Method will be applied only to keys, final value will be kept untouched.
     *
     * @param string $string String to be localized.
     * @return string
     */
    protected function normalize($string)
    {
        return preg_replace('/[ \t\n\r]+/', ' ', trim($string));
    }

    /**
     * Force translation for specified string in bundle file. Will replace existed translation or
     * create new one.
     *
     * @param string $bundle      Bundle name.
     * @param string $string      String to be localized, should be sprintf compatible if formatting
     *                            required.
     * @param string $translation String translation, by default equals to string itself.
     * @return string
     */
    public function set($bundle, $string, $translation = '')
    {
        $this->loadBundle($bundle);
        $this->bundles[$bundle][$string] = func_num_args() == 2 ? $translation : $string;
        $this->saveBundle($bundle);
    }
}