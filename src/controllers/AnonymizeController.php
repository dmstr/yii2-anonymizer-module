<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\controllers;

use dmstr\anonymizer\components\AnonymizationExecutor;
use dmstr\anonymizer\Module;
use Yii;
use yii\web\BadRequestHttpException;
use yii\web\ConflictHttpException;
use yii\web\NotFoundHttpException;
use yii\web\ServerErrorHttpException;

/**
 * REST controller for user data anonymization
 *
 * Provides endpoints for:
 * - DELETE /anonymize/{uuid} - Anonymize user data
 * - GET /analyze/{uuid} - Dry-run analysis
 *
 * @package dmstr\anonymizer\controllers
 */
class AnonymizeController extends BaseController
{
    /**
     * @var string UUID v4 validation pattern (RFC 4122)
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @inheritdoc
     */
    protected array $allowedVerbs = ['GET', 'DELETE', 'OPTIONS'];

    /**
     * DELETE /anonymize/{uuid}
     *
     * Anonymize user data for the given UUID.
     *
     * @param string $uuid User UUID
     * @return array JSON response
     */
    public function actionRemove(string $uuid): array
    {
        try {
            // Validate UUID format
            $this->validateUuid($uuid);

            // Find user
            $user = $this->findUser($uuid);

            // Check if already anonymized
            $this->checkAlreadyAnonymized($user);

            // Execute anonymization
            /** @var Module $module */
            $module = $this->module;
            $executor = new AnonymizationExecutor(
                $module->getHelpers(),
                $module->getDefaultOptions()
            );

            $result = $executor->execute($user);

            // Log the action
            Yii::info("User anonymized via REST: UUID={$uuid}", 'anonymizer');

            if (!$result->success && empty($result->results)) {
                Yii::error("Anonymization failed for UUID={$uuid}: " . implode(', ', $result->errors), 'anonymizer');
                throw new ServerErrorHttpException('Anonymization failed: ' . implode(', ', $result->errors));
            }

            return [
                'success' => $result->success,
                'message' => 'User data removed successfully',
                'data' => [
                    'user_id' => $this->getUserId($user),
                    'user_uuid' => $uuid,
                    'removed_at' => $result->timestamp,
                    'helpers_executed' => $result->helpersExecuted,
                    'total_records_updated' => $result->totalRecordsUpdated,
                    'helpers_results' => $result->results,
                    'errors' => $result->errors,
                ],
                'timestamp' => $result->timestamp,
            ];
        } catch (\Exception $exception) {
            Yii::error($exception->getMessage(), 'anonymizer');
        }

        return [
            'success' => false,
            'message' => 'Exception raised while removing user data',
        ];

    }

    /**
     * GET /analyze/{uuid}
     *
     * Analyze what would be anonymized (dry-run mode).
     *
     * @param string $uuid User UUID
     * @return array JSON response
     * @throws BadRequestHttpException if UUID format is invalid
     * @throws NotFoundHttpException if user not found
     */
    public function actionAnalyze(string $uuid): array
    {
        // Validate UUID format
        $this->validateUuid($uuid);

        // Find user
        $user = $this->findUser($uuid);

        // Execute analysis (dry-run)
        /** @var Module $module */
        $module = $this->module;
        $executor = new AnonymizationExecutor(
            $module->getHelpers(),
            $module->getDefaultOptions()
        );

        $result = $executor->analyze($user);

        // Log the action
        Yii::info("User analyzed via REST: UUID={$uuid}", 'anonymizer');

        return [
            'success' => true,
            'message' => 'Analysis completed',
            'data' => [
                'user_id' => $this->getUserId($user),
                'user_uuid' => $uuid,
                'is_anonymized' => $this->isUserAnonymized($user),
                'helpers_configured' => count($module->getHelpers()),
                'helpers_results' => $result->results,
            ],
            'timestamp' => $result->timestamp,
        ];
    }

    /**
     * Validate UUID format
     *
     * @param string $uuid
     * @throws BadRequestHttpException
     */
    protected function validateUuid(string $uuid): void
    {
        if (!preg_match(self::UUID_PATTERN, $uuid)) {
            Yii::warning("Invalid UUID format: {$uuid}", 'anonymizer');
            throw new BadRequestHttpException(
                'Invalid UUID format. Expected RFC 4122 UUID v4 format.'
            );
        }
    }

    /**
     * Find user by UUID
     *
     * @param string $uuid
     * @return mixed User model
     * @throws NotFoundHttpException
     */
    protected function findUser(string $uuid)
    {
        /** @var Module $module */
        $module = $this->module;
        $user = $module->findUserByUuid($uuid);

        if ($user === null) {
            Yii::warning("User not found for UUID: {$uuid}", 'anonymizer');
            throw new NotFoundHttpException(
                'User not found for provided UUID.'
            );
        }

        return $user;
    }

    /**
     * Check if user is already anonymized
     *
     * @param mixed $user
     */
    protected function checkAlreadyAnonymized($user): void
    {
        if ($this->isUserAnonymized($user)) {
            $userId = $this->getUserId($user);
            Yii::info("User already anonymized: ID={$userId}", 'anonymizer');
        }
    }

    /**
     * Check if user is anonymized
     *
     * Detection criteria:
     * - gdpr_deleted flag is set to 1
     * - username starts with configured prefix
     * - email starts with "anonymized_"
     *
     * @param mixed $user
     * @return bool
     */
    protected function isUserAnonymized($user): bool
    {
        // Check gdpr_deleted flag
        if (isset($user->gdpr_deleted) && $user->gdpr_deleted == 1) {
            return true;
        }

        /** @var Module $module */
        $module = $this->module;
        $prefix = $module->anonymizationPrefix;

        // Check username prefix
        if (isset($user->username) && str_starts_with($user->username, $prefix)) {
            return true;
        }

        // Check email prefix
        if (isset($user->email) && str_starts_with($user->email, 'anonymized_')) {
            return true;
        }

        return false;
    }

    /**
     * Get user ID from user object
     *
     * @param mixed $user
     * @return int|null
     */
    protected function getUserId($user): ?int
    {
        if (isset($user->id)) {
            return (int) $user->id;
        }

        if (method_exists($user, 'getId')) {
            return (int) $user->getId();
        }

        return null;
    }
}
