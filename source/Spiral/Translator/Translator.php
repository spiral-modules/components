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
use Spiral\Debug\Traits\BenchmarkTrait;
use Spiral\Files\FilesInterface;
use Spiral\Translator\Configs\TranslatorConfig;
use Spiral\Translator\Exceptions\LocaleException;
use Spiral\Translator\Exceptions\PluralizationException;
use Symfony\Component\Translation\MessageSelector;

/**
 * Simple implementation of Symfony\TranslatorInterface with memory caching and automatic message
 * registration.
 */
class Translator extends Component implements SingletonInterface, TranslatorInterface
{
    use BenchmarkTrait;

    /**
     * Memory section.
     */
    const MEMORY = 'translator';

    /**
     * @var TranslatorConfig
     */
    private $config = null;

    /**
     * Symfony selection logic is little
     *
     * @var MessageSelector
     */
    private $selector = null;

    /**
     * Current locale.
     *
     * @var string
     */
    private $locale = '';

    /**
     * Loaded catalogues.
     *
     * @var Catalogue
     */
    private $catalogues = [];

    /**
     * Catalogue to be used for fallback translation.
     *
     * @var Catalogue
     */
    private $fallbackCatalogue = null;

    /**
     * @var array
     */
    private $loadedLocales = [];

    /**
     * @var array
     */
    protected $domains = [];

    /**
     * To load locale data from application files.
     *
     * @var FilesInterface
     */
    protected $source = null;

    /**
     * @var HippocampusInterface
     */
    protected $memory = null;

    /**
     * @param TranslatorConfig     $config
     * @param HippocampusInterface $memory
     * @param SourceInterface      $source
     * @param MessageSelector      $selector
     */
    public function __construct(
        TranslatorConfig $config,
        HippocampusInterface $memory,
        SourceInterface $source,
        MessageSelector $selector = null
    ) {
        $this->config = $config;
        $this->memory = $memory;
        $this->source = $source;
        $this->selector = $selector;

        $this->locale = $this->config->defaultLocale();

        //List of known and loaded locales
        $this->loadedLocales = (array)$this->memory->loadData(static::MEMORY);
        $this->fallbackCatalogue = $this->loadCatalogue($this->config->fallbackLocale());
    }

    /**
     * @return SourceInterface
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * {@inheritdoc}
     */
    public function resolveDomain($bundle)
    {
        return $this->config->resolveDomain($bundle);
    }

    /**
     * {@inheritdoc}
     *
     * Parameters will be embedded into string using { and } braces.
     *
     * @throws LocaleException
     */
    public function trans(
        $id,
        array $parameters = [],
        $domain = self::DEFAULT_DOMAIN,
        $locale = null
    ) {
        //Automatically falls back to default locale
        $translation = $this->get($domain, $id, $locale);

        return \Spiral\interpolate($translation, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * Default symfony pluralizer to be used. Parameters will be embedded into string using { and }
     * braces. In addition you can use forced parameter {n} which contain formatted number value.
     *
     * @throws LocaleException
     * @throws PluralizationException
     */
    public function transChoice(
        $id,
        $number,
        array $parameters = [],
        $domain = self::DEFAULT_DOMAIN,
        $locale = null
    ) {
        if (empty($parameters['{n}'])) {
            $parameters['{n}'] = number_format($number);
        }

        //Automatically falls back to default locale
        $translation = $this->get($domain, $id, $locale);

        try {
            $pluralized = $this->selector->choose($translation, $number, $locale);
        } catch (\InvalidArgumentException $e) {
            //Wrapping into more explanatory exception
            throw new PluralizationException($e->getMessage(), $e->getCode(), $e);
        }

        return \Spiral\interpolate($pluralized, $parameters);
    }

    /**
     * {@inheritdoc}
     *
     * @return $this
     *
     * @throws LocaleException
     */
    public function setLocale($locale)
    {
        if (!$this->hasLocale($locale)) {
            throw new LocaleException("Undefined locale '{$locale}'");
        }

        $this->locale = $locale;

        return $this;
    }

    /**
     * @return string
     */
    public function getLocale()
    {
        return $this->locale;
    }

    /**
     * {@inheritdoc}
     *
     * Attention, method will return cached locales first.
     */
    public function getLocales()
    {
        if (!empty($this->loadedLocales)) {
            return array_keys($this->loadedLocales);
        }

        $this->loadLocales();

        return $this->source->getLocales();
    }

    /**
     * Return catalogue for specific locate or return default one if no locale specified.
     *
     * @param string $locale
     * @return Catalogue
     *
     * @throws LocaleException
     */
    public function getCatalogue($locale = null)
    {
        if (empty($locale)) {
            $locale = $this->locale;
        }

        if (!$this->hasLocale($locale)) {
            throw new LocaleException("Undefined locale '{$locale}'");
        }

        if (!isset($this->catalogues[$locale])) {
            $this->catalogues[$locale] = $this->loadCatalogue($locale);
        }

        return $this->catalogues[$locale];
    }

    /**
     * Load all possible locales.
     *
     * @return $this
     */
    public function loadLocales()
    {
        foreach ($this->source->getLocales() as $locale) {
            $this->loadCatalogue($locale);
        }

        return $this;
    }

    /**
     * Flush all loaded locales data.
     *
     * @return $this
     */
    public function flushLocales()
    {
        $this->loadedLocales = [];
        $this->catalogues = [];

        $this->memory->saveData(static::MEMORY, []);

        //Reloading fallback locale
        $this->fallbackCatalogue = $this->loadCatalogue($this->config->fallbackLocale());

        return $this;
    }

    /**
     * Get message from specific locale, add it into fallback locale cache (to be later exported) if
     * enabled (see TranslatorConfig) and no translations found.
     *
     * @param string $domain
     * @param string $string
     * @param string $locale
     *
     * @return string
     */
    protected function get($domain, $string, $locale)
    {
        //Active language first
        if ($this->getCatalogue($locale)->has($domain, $string)) {
            return $this->getCatalogue($locale)->get($domain, $string);
        }

        if ($this->fallbackCatalogue->has($domain, $string)) {
            return $this->fallbackCatalogue->get($domain, $string);
        }

        //Automatic message registration.
        if ($this->config->registerMessages()) {
            $this->fallbackCatalogue->set($domain, $string, $string);
            $this->fallbackCatalogue->saveDomains();
        }

        //Unable to find translation
        return $string;
    }

    /**
     * Load catalogue data from source.
     *
     * @param string $locale
     * @return Catalogue
     */
    protected function loadCatalogue($locale)
    {
        $catalogue = new Catalogue($locale, $this->memory);

        if (array_key_exists($locale, $this->loadedLocales) && $this->config->cacheLocales()) {
            //Has been loaded
            return $catalogue;
        }

        $benchmark = $this->benchmark('load', $locale);
        try {

            //Loading catalogue data from source
            foreach ($this->source->loadLocale($locale) as $messageCatalogue) {
                $catalogue->mergeFrom($messageCatalogue);
            }

            //To remember that locale already loaded
            $this->loadedLocales[$locale] = $catalogue->getDomains();
            $this->memory->saveData(static::MEMORY, $this->loadedLocales);

            //Saving domains memory
            $catalogue->saveDomains();
        } finally {
            $this->benchmark($benchmark);
        }

        return $catalogue;
    }

    /**
     * Check if given locale exists.
     *
     * @param string $locale
     * @return bool
     */
    private function hasLocale($locale)
    {
        if (array_key_exists($locale, $this->loadedLocales)) {
            return true;
        }

        return $this->source->hasLocale($locale);
    }

    /**
     * Check if string has translation braces [[ and ]].
     *
     * @param string $string
     * @return bool
     */
    public static function isMessage($string)
    {
        return substr($string, 0, 2) == self::I18N_PREFIX
        && substr($string, -2) == self::I18N_POSTFIX;
    }
}