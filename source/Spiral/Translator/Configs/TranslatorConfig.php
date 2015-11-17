<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator\Configs;

use Spiral\Core\ArrayConfig;

/**
 * Translation component configuration.
 */
class TranslatorConfig extends ArrayConfig
{
    /**
     * Configuration section.
     */
    const CONFIG = 'translator';

    /**
     * @var array
     */
    protected $config = [
        'default'   => '',
        'plurals'   => 'plural-phrases',
        'languages' => []
    ];

    /**
     * @return string
     */
    public function defaultLanguage()
    {
        return $this->config['default'];
    }

    /**
     * @param string $language
     * @return bool
     */
    public function hasLanguage($language)
    {
        return isset($this->config['languages'][$language]);
    }

    /**
     * @param string $language
     * @return array
     */
    public function languageOptions($language)
    {
        return $this->config['languages'][$language];
    }

    /**
     * @param string $language
     * @return string
     */
    public function languagePluralizer($language)
    {
        return $this->config['languages'][$language]['pluralizer'];
    }
}