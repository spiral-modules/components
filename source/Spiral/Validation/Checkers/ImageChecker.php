<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation\Checkers;

use Spiral\Core\Container\SingletonInterface;

/**
 * Image based validations.
 */
class ImageChecker extends FileChecker implements SingletonInterface
{
    /**
     * Declaring to IoC to construct class only once.
     */
    const SINGLETON = self::class;

    /**
     * Getimagesize constants.
     */
    const WIDTH      = 0;
    const HEIGHT     = 1;
    const IMAGE_TYPE = 2;

    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "type"    => "[[Image does not supported.]]",
        "valid"   => "[[Image does not supported (allowed JPEG, PNG or GIF).]]",
        "smaller" => "[[Image size should not exceed {0}x{1}px.]]",
        "bigger"  => "[[The image dimensions should be at least {0}x{1}px.]]"
    ];

    /**
     * Known image types.
     *
     * @var array
     */
    protected $imageTypes = [
        'null',
        'gif',
        'jpeg',
        'png',
        'swf',
        'psd',
        'bmp',
        'tiff',
        'tiff',
        'jpc',
        'jp2',
        'jpx',
        'jb2',
        'swc',
        'iff',
        'wbmp',
        'xbm'
    ];

    /**
     * Check if image in a list of allowed image types.
     *
     * @param mixed       $filename
     * @param array|mixed $types
     * @return bool
     */
    public function type($filename, $types)
    {
        if (empty($image = $this->imageData($filename))) {
            return false;
        }

        if (!is_array($types)) {
            $types = array_slice(func_get_args(), 1);
        }

        return in_array($this->imageTypes[$image[self::IMAGE_TYPE]], $types);
    }

    /**
     * Shortcut to check if image has valid type (JPEG, PNG and GIF are allowed).
     *
     * @param mixed $filename
     * @return bool
     */
    public function valid($filename)
    {
        return $this->type($filename, ['jpeg', 'png', 'gif']);
    }

    /**
     * Check if image smaller that specified rectangle (height check if optional).
     *
     * @param mixed $filename
     * @param int   $width
     * @param int   $height Optional.
     * @return bool
     */
    public function smaller($filename, $width, $height = null)
    {
        if (empty($image = $this->imageData($filename))) {
            return false;
        }

        if ($image[self::WIDTH] >= $width) {
            return false;
        }

        if ($height && $image[self::HEIGHT] >= $height) {
            return false;
        }

        return true;
    }

    /**
     * Check if image is bigger that specified rectangle (height check is optional).
     *
     * @param mixed $filename
     * @param int   $width
     * @param int   $height Optional.
     * @return bool
     */
    public function bigger($filename, $width, $height = null)
    {
        if (empty($image = $this->imageData($filename))) {
            return false;
        }

        if ($image[self::WIDTH] < $width) {
            return false;
        }

        if ($height && $image[self::HEIGHT] < $height) {
            return false;
        }

        return true;
    }

    /**
     * Internal method, return image details fetched by getimagesize() or false.
     *
     * @see getimagesize()
     * @param string $filename
     * @return array|bool
     */
    protected function imageData($filename)
    {
        try {
            return getimagesize($this->filename($filename));
        } catch (\Exception $exception) {
        }

        return false;
    }
}