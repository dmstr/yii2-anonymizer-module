<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\components;

use dmstr\anonymizer\interfaces\AnonymizationHelperInterface;
use Yii;

/**
 * Executor class for user data anonymization
 *
 * Orchestrates multiple anonymization helpers to comply with GDPR
 * and data privacy requirements.
 *
 * @package dmstr\anonymizer\components
 */
class AnonymizationExecutor
{
    /**
     * @var array<string> List of helper class names
     */
    private array $helpers;

    /**
     * @var array Default options for anonymization
     */
    private array $defaultOptions;

    /**
     * AnonymizationExecutor constructor.
     *
     * @param array $helpers List of helper class names
     * @param array $defaultOptions Default options for anonymization
     */
    public function __construct(array $helpers = [], array $defaultOptions = [])
    {
        $this->helpers = $helpers;
        $this->defaultOptions = $defaultOptions;
    }

    /**
     * Execute anonymization using all configured helpers
     *
     * @param mixed $user The user to anonymize
     * @param array $options Additional options for anonymization
     * @return AnonymizationResult
     */
    public function execute($user, array $options = []): AnonymizationResult
    {
        return $this->runHelpers($user, $options, false);
    }

    /**
     * Analyze what would be anonymized (dry-run mode)
     *
     * @param mixed $user The user to analyze
     * @param array $options Additional options for analysis
     * @return AnonymizationResult
     */
    public function analyze($user, array $options = []): AnonymizationResult
    {
        return $this->runHelpers($user, $options, true);
    }

    /**
     * Run all configured helpers
     *
     * @param mixed $user
     * @param array $options
     * @param bool $dryRun
     * @return AnonymizationResult
     */
    private function runHelpers($user, array $options, bool $dryRun): AnonymizationResult
    {
        $mergedOptions = array_merge($this->defaultOptions, $options);
        $results = [];
        $totalRecordsUpdated = [];
        $errors = [];

        foreach ($this->helpers as $helperClass) {
            try {
                /** @var AnonymizationHelperInterface $helperClass */
                $result = $dryRun
                    ? $helperClass::analyze($user, $mergedOptions)
                    : $helperClass::anonymize($user, $mergedOptions);

                $results[] = [
                    'helper' => $helperClass,
                    'description' => $helperClass::getDescription(),
                    'result' => $result,
                ];

                // Accumulate records updated
                if (isset($result['records_updated']) && is_array($result['records_updated'])) {
                    foreach ($result['records_updated'] as $table => $count) {
                        $totalRecordsUpdated[$table] = ($totalRecordsUpdated[$table] ?? 0) + $count;
                    }
                }

                // Log the action
                $action = $dryRun ? 'analyzed' : 'anonymized';
                Yii::info("Helper {$helperClass} {$action} user data", 'anonymizer');

            } catch (\Exception $e) {
                $errors[] = "Helper {$helperClass} failed: " . $e->getMessage();
                Yii::warning("Anonymization helper {$helperClass} failed: " . $e->getMessage(), 'anonymizer');
            }
        }

        $success = empty($errors) || !empty($results);

        if (!empty($errors) && empty($results)) {
            Yii::error('All anonymization helpers failed: ' . implode(', ', $errors), 'anonymizer');
        }

        return new AnonymizationResult(
            $success,
            count($results),
            $totalRecordsUpdated,
            $results,
            $errors
        );
    }

    /**
     * Get configured helpers
     *
     * @return array<string>
     */
    public function getHelpers(): array
    {
        return $this->helpers;
    }

    /**
     * Set helpers
     *
     * @param array $helpers
     * @return void
     */
    public function setHelpers(array $helpers): void
    {
        $this->helpers = $helpers;
    }
}
