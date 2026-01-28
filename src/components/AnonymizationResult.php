<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\components;

/**
 * Value object representing the result of an anonymization operation
 *
 * @package dmstr\anonymizer\components
 */
class AnonymizationResult
{
    /**
     * @var bool Whether the operation was successful
     */
    public bool $success;

    /**
     * @var int Number of helpers executed
     */
    public int $helpersExecuted;

    /**
     * @var array<string, int> Total records updated per table
     */
    public array $totalRecordsUpdated;

    /**
     * @var array Results from each helper
     */
    public array $results;

    /**
     * @var array<string> Error messages
     */
    public array $errors;

    /**
     * @var string ISO-8601 timestamp
     */
    public string $timestamp;

    /**
     * AnonymizationResult constructor.
     *
     * @param bool $success
     * @param int $helpersExecuted
     * @param array $totalRecordsUpdated
     * @param array $results
     * @param array $errors
     */
    public function __construct(
        bool $success = true,
        int $helpersExecuted = 0,
        array $totalRecordsUpdated = [],
        array $results = [],
        array $errors = []
    ) {
        $this->success = $success;
        $this->helpersExecuted = $helpersExecuted;
        $this->totalRecordsUpdated = $totalRecordsUpdated;
        $this->results = $results;
        $this->errors = $errors;
        $this->timestamp = date('c');
    }

    /**
     * Convert to array for JSON response
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'success' => $this->success,
            'helpers_executed' => $this->helpersExecuted,
            'total_records_updated' => $this->totalRecordsUpdated,
            'helpers_results' => $this->results,
            'errors' => $this->errors,
            'timestamp' => $this->timestamp,
        ];
    }
}
