<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\exceptions;

use yii\base\Exception;

/**
 * Exception thrown when a configured helper class is not found
 *
 * @package dmstr\anonymizer\exceptions
 */
class HelperNotFoundException extends Exception
{
    /**
     * @var string The helper class name that was not found
     */
    public string $helperClass;

    /**
     * HelperNotFoundException constructor.
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
            $message = "Anonymization helper class '{$helperClass}' not found";
        }

        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'Helper Not Found';
    }
}
