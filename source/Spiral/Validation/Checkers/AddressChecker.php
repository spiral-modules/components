<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Checkers;

use Spiral\Validation\Checker;

/**
 * Validate different addresses: email, url and etc.
 */
class AddressChecker extends Checker
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
     * @param bool   $requireProtocol If true, this will require having a protocol definition.
     * @return bool
     */
    public function url($url, $requireProtocol = true)
    {
        if (!$requireProtocol && stripos($url, 'http://') === false && stripos($url,
                'https://') === false
        ) {
            $url = 'http://' . $url;
        }

        return (bool)filter_var($url, FILTER_VALIDATE_URL);
    }
}