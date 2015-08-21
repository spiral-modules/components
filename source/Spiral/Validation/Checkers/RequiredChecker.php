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
use Spiral\Validation\Validator;

/**
 * Validations based dependencies between fields.
 */
class RequiredChecker extends Checker
{
    /**
     * {@inheritdoc}
     */
    protected $messages = [
        "with"       => "[[This field is required.]]",
        "withAll"    => "[[This field is required.]]",
        "without"    => "[[This field is required.]]",
        "withoutAll" => "[[This field is required.]]",
    ];

    /**
     * Check if field not empty but only if any of listed fields presented or not empty.
     *
     * @param mixed $value
     * @param array $with
     * @return bool
     */
    public function with($value, array $with)
    {
        if (!empty($value)) {
            return true;
        }

        foreach ($with as $field) {
            if ($this->validator->field($field)) {
                //Some value presented
                return false;
            }
        }

        return Validator::STOP_VALIDATION;
    }

    /**
     * Check if field not empty but only if all of listed fields presented and not empty.
     *
     * @param mixed $value
     * @param array $with
     * @return bool
     */
    public function withAll($value, array $with)
    {
        if (!empty($value)) {
            return true;
        }

        foreach ($with as $field) {
            if (!$this->validator->field($field)) {
                return Validator::STOP_VALIDATION;
            }
        }

        return false;
    }

    /**
     * Check if field not empty but only if any of listed fields missing or empty.
     *
     * @param mixed $value
     * @param array $without
     * @return bool
     */
    public function without($value, array $without)
    {
        if (!empty($value)) {
            return true;
        }

        foreach ($without as $field) {
            if (!$this->validator->field($field)) {
                //Some value presented
                return false;
            }
        }

        return Validator::STOP_VALIDATION;
    }

    /**
     * Check if field not empty but only if all of listed fields missing or empty.
     *
     * @param mixed $value
     * @param array $without
     * @return bool
     */
    public function withoutAll($value, array $without)
    {
        if (!empty($value)) {
            return true;
        }

        foreach ($without as $field) {
            if ($this->validator->field($field)) {
                return Validator::STOP_VALIDATION;
            }
        }

        return false;
    }
}