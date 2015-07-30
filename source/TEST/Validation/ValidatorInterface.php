<?php
/**
 * Spiral Framework.
 *
 * @license   MIT
 * @author    Anton Titov (Wolfy-J)
 * @copyright ©2009-2015
 */
namespace Spiral\Validation;

use Spiral\Core\ContainerInterface;

interface ValidatorInterface
{
    /**
     * ValidatorInterface instance with specified input data and validation rules.
     *
     * @param array|\ArrayAccess $data             Data to be validated.
     * @param array              $validates        Validation rules.
     * @param array              $options          Validation specific options.
     * @param ContainerInterface $container        Container instance used to resolve checkers, global
     *                                             container will be used if nothing else provided.
     */
    public function __construct($data, array $validates, array $options = [], ContainerInterface $container);

    /**
     * Update validation data (context), this method will automatically clean all existed error
     * messages and set validated flag to false.
     *
     * @param array|\ArrayAccess $data Data to be validated.
     * @return self
     */
    public function setData($data);

    /**
     * Update validation rules.
     *
     * @param array $validates
     * @return self
     */
    public function setRules(array $validates);

    /**
     * Validate data (if not already validated) and return validation status, true if all fields
     * passed validation and false is some error messages collected (error messages can be forced
     * manually using addError() method).
     *
     * @return bool
     */
    public function isValid();

    /**
     * Evil tween of isValid() method: validate data (if not already validated) and return true if
     * any validation error occurred including errors added using addError() method.
     *
     * @return bool
     */
    public function hasErrors();

    /**
     * Manually force error for some field ("forced" condition will be used).
     *
     * @param string $field
     * @param string $message Custom error message, will be interpolated if interpolateMessages
     *                        property set to true.
     */
    public function addError($field, $message);

    /**
     * Validate data (if not already) and return all error messages associated with their field names.
     * Output format can vary based on validator implementation.
     *
     * @return array
     */
    public function getErrors();
}