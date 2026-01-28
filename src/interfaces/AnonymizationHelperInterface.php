<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace dmstr\anonymizer\interfaces;

/**
 * Interface for user anonymization helpers
 *
 * Implementations of this interface should handle specific aspects
 * of user data anonymization (e.g., user data, profile data, external systems).
 *
 * @package dmstr\anonymizer\interfaces
 */
interface AnonymizationHelperInterface
{
    /**
     * Anonymize user data
     *
     * @param mixed $user The user to anonymize
     * @param array $options Additional options for anonymization
     * @return array Result of anonymization operation with format:
     *               [
     *                   'success' => bool,
     *                   'message' => string,
     *                   'records_updated' => array<string, int>,
     *                   'data' => array (optional additional data)
     *               ]
     */
    public static function anonymize($user, array $options = []): array;

    /**
     * Analyze user data (dry-run mode)
     *
     * Returns the same structure as anonymize() but without making changes.
     * Used for previewing what would be anonymized.
     *
     * @param mixed $user The user to analyze
     * @param array $options Additional options for analysis
     * @return array Analysis result with same format as anonymize()
     */
    public static function analyze($user, array $options = []): array;

    /**
     * Get a description of what this helper anonymizes
     *
     * @return string Human-readable description
     */
    public static function getDescription(): string;
}
