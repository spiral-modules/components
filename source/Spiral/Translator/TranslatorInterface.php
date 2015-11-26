<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator;

use Spiral\Translator\Exceptions\LanguageException;
use Spiral\Translator\Exceptions\TranslatorException;

/**
 * Provides bundle based string translations.
 */
interface TranslatorInterface
{
    /**
     * Default translation bundle.
     */
    const DEFAULT_BUNDLE = 'default';

    /**
     * Default set of braces to be used in classes or views for indication of translatable content.
     */
    const I18N_PREFIX  = '[[';
    const I18N_POSTFIX = ']]';

    /**
     * Change language.
     *
     * @param string $language
     * @throws LanguageException
     */
    public function setLanguage($language);

    /**
     * Get current language.
     *
     * @return string
     */
    public function getLanguage();

    /**
     * Translate value using active language. Method must support message interpolation using
     * interpolate method and sptrinf.
     *
     * Examples:
     * $translator->translate('bundle', 'Some Message');
     * $translator->translate('bundle', 'Hello %s', $name);
     *
     * @param string      $bundle
     * @param string      $string
     * @param array|mixed $options Interpolation options.
     * @return string
     * @throws TranslatorException
     */
    public function translate($bundle, $string, $options = []);

    /**
     * Pluralize string using language pluralization options and specified numeric value. Number
     * has to be ingested at place of {n} placeholder.
     *
     * Examples:
     * $translator->pluralize("{n} user", $users);
     *
     * @param string $phrase Should include {n} as placeholder.
     * @param int    $number
     * @param bool   $format Format number.
     * @return string
     * @throws TranslatorException
     */
    public function pluralize($phrase, $number, $format = true);

    /**
     * Check if given string known to translator.
     *
     * @param string $bundle
     * @param string $string
     * @return bool
     */
    public function knows($bundle, $string);

    /**
     * Set string translation in specified bundle.
     *
     * @param string       $bundle
     * @param string       $string
     * @param string|array $translation Must contain array of phrase forms for plural phrases.
     */
    public function set($bundle, $string, $translation = '');
}