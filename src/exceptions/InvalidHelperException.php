<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\exceptions;

use yii\base\Exception;

/**
 * Exception thrown when a helper class does not implement the required interface
 *
 * @package dmstr\anonymizer\exceptions
 */
class InvalidHelperException extends Exception
{
    /**
     * @var string The helper class name that is invalid
     */
    public string $helperClass;

    /**
     * InvalidHelperException constructor.
     *
     * @param string $helperClass
     * @param string $message
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct(string $helperClass, string $message = '', int $code = 0, ?\Throwable $previous = null)
    {
        $this->helperClass = $helperClass;

        if (empty($message)) {
            $message = "Anonymization helper class '{$helperClass}' does not implement AnonymizationHelperInterface";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Invalid Helper';
    }
}
