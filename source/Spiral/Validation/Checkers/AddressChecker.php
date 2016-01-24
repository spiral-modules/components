<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 */
namespace Spiral\Validation\Checkers;

use Spiral\Core\Container\SingletonInterface;
use Spiral\Validation\Checker;

/**
 * Validate different addresses: email, url and etc.
 */
class AddressChecker extends Checker implements SingletonInterface
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "email" => "[[Must be a valid email address.]]",
        "url"   => "[[Must be a valid URL address.]]",
    ];

    /**
     * Check if email is valid.
     *
     * @link http://www.ietf.org/rfc/rfc2822.txt
     * @param string
     * @return bool
     */
    public function email($email)
    {
        return (bool)filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Check if URL is valid.
     *
     * @link http://www.faqs.org/rfcs/rfc2396.html
     * @param string $url
     * @param bool   $requireScheme If true, this will require having a protocol definition.
     * @return bool
     */
    public function url($url, $requireScheme = true)
    {
        if (
            !$requireScheme
            && stripos($url, 'http://') === false && stripos($url, 'https://') === false
        ) {
            //Forcing scheme (not super great idea)
            $url = 'http://' . $url;
        }

        return (bool)filter_var($url, FILTER_VALIDATE_URL);
    }
}