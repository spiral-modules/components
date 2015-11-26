<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator;

use Spiral\Translator\Exceptions\LocaleException;
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
     * @throws LocaleException
     */
    public function setLocale($language);

    /**
     * Get current language.
     *
     * @return string
     */
    public function getLocate();

    /**
     * Translate value using active language. Method must support message interpolation.
     *
     * Examples:
     * $translator->translate('bundle', 'Some Message');
     * $translator->translate('bundle', 'Hello {name}', ['name' => $name]);
     *
     * @param string $bundle
     * @param string $string
     * @param array  $options Interpolation options.
     * @return string
     * @throws TranslatorException
     */
    public function translate($bundle, $string, array $options = []);

    /**
     * Pluralize string using language pluralization options and specified numeric value. Number
     * has to be ingested at place of {n} placeholder.
     *
     * Examples:
     * $translator->pluralize("{n} user", $users);
     *
     * @param string $phrase Should include {n} as placeholder.
     * @param int    $number
     * @param bool   $format Format number using number_format function.
     * @return string
     * @throws TranslatorException
     */
    public function pluralize($phrase, $number, $format = true);
}