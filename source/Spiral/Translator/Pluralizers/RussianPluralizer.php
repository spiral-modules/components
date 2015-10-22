<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Translator\Pluralizers;

use Spiral\Translator\PluralizerInterface;

/**
 * Russian and similar languages.
 */
class RussianPluralizer implements PluralizerInterface
{
    /**
     * {@inheritdoc}
     */
    public function getFormula()
    {
        return 'n%10==1&&n%100!=11?0:(n%10>=2&&n%10<=4&&(n100<10||n100>=20)?1:2)';
    }

    /**
     * {@inheritdoc}
     */
    public function countForms()
    {
        return 3;
    }

    /**
     * {@inheritdoc}
     */
    public function getForm($number, array $forms)
    {
        return ($number % 10 == 1 && $number % 100 != 11)
            ? $forms[0]
            : (
            $number % 10 >= 2 && $number % 10 <= 4 && ($number % 100 < 10 || $number % 100 >= 20)
                ? $forms[1]
                : $forms[2]
            );
    }
}