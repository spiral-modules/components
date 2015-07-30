<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Translator\Pluralizers;

use Spiral\Translator\PluralizerInterface;

/**
 * English and similar languages.
 */
class EnglishPluralizer implements PluralizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getFormula()
    {
        return 'n==1?0:1';
    }

    /**
     * {@inheritdoc}
     */
    public function countForms()
    {
        return 2;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm($number, array $forms)
    {
        return $number == 1 ? $forms[0] : $forms[1];
    }
}