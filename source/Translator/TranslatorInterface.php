<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Translator;

interface TranslatorInterface
{
    /**
     * Models and other classes which inherits I18nIndexable interface allowed to be automatically
     * parsed and analyzed for messages stored in default property values (static and non static),
     * such values can be prepended and appended with i18n prefixes ([[ and ]] by default) and will
     * be localized on output.
     *
     * Class should implement i18nNamespace method (static) which will define required i18n namespace.
     */
    const I18N_PREFIX  = '[[';
    const I18N_POSTFIX = ']]';

    /**
     * Change application language selection, all future translations or pluralization
     * phrases will be fetched using new language options and bundles.
     *
     * @param string $languageID Valid language identifier (en, ru, de).
     * @throws TranslatorException
     */
    public function setLanguage($languageID);

    /**
     * Currently selected language identifier.
     *
     * @return string
     */
    public function getLanguage();

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
    public function translate($bundle, $string);

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
    public function pluralize($phrase, $number, $formatNumber = true);
}