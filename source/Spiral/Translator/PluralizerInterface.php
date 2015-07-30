<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Translator;

/**
 * Simple interface to pluralize messages.
 */
interface PluralizerInterface
{
    /**
     * Get abstract pluralization formula.
     *
     * @link http://docs.translatehouse.org/projects/localization-guide/en/latest/l10n/pluralforms.html
     * @return string
     */
    public function getFormula();

    /**
     * How many forms presented.
     *
     * @return int
     */
    public function countForms();

    /**
     * Select pluralization form from list using number value.
     *
     * @param int   $number
     * @param array $forms
     * @return string
     */
    public function getForm($number, array $forms);
}