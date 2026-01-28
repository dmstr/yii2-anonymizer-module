<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\commands;

use dmstr\anonymizer\components\AnonymizationExecutor;
use dmstr\anonymizer\Module;
use Yii;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;
use yii\helpers\Json;

/**
 * CLI controller for user data anonymization
 *
 * Provides commands for:
 * - analyze: Dry-run analysis of what would be anonymized
 * - execute: Execute anonymization
 *
 * Usage:
 *   yii anonymizer/analyze --uuid=<uuid>
 *   yii anonymizer/execute --uuid=<uuid> --force
 *
 * @package dmstr\anonymizer\commands
 */
class AnonymizerController extends Controller
{
    /**
     * @var string UUID v4 validation pattern (RFC 4122)
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * @var string User UUID to process
     */
    public string $uuid = '';

    /**
     * @var string Output format: text or json
     */
    public string $format = 'text';

    /**
     * @var bool Force execution without confirmation
     */
    public bool $force = false;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);

        $options[] = 'uuid';
        $options[] = 'format';

        if ($actionID === 'execute') {
            $options[] = 'force';
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function optionAliases(): array
    {
        return [
            'u' => 'uuid',
            'f' => 'format',
        ];
    }

    /**
     * Analyze what would be anonymized (dry-run mode)
     *
     * Usage: yii anonymizer/analyze --uuid=<uuid> [--format=text|json]
     *
     * @return int Exit code
     */
    public function actionAnalyze(): int
    {
        // Validate UUID
        if (!$this->validateUuid()) {
            return ExitCode::USAGE;
        }

        // Check helpers configuration
        if (!$this->checkHelpersConfigured()) {
            return ExitCode::CONFIG;
        }

        // Find user
        $user = $this->findUser();
        if ($user === null) {
            return ExitCode::DATAERR;
        }

        // Execute analysis
        $module = $this->getModule();
        $executor = new AnonymizationExecutor(
            $module->getHelpers(),
            $module->getDefaultOptions()
        );

        $result = $executor->analyze($user);

        // Output results
        if ($this->format === 'json') {
            $this->outputJson($result->toArray());
        } else {
            $this->outputTextAnalysis($result, $user);
        }

        return ExitCode::OK;
    }

    /**
     * Execute anonymization
     *
     * Usage: yii anonymizer/execute --uuid=<uuid> --force [--format=text|json]
     *
     * @return int Exit code
     */
    public function actionExecute(): int
    {
        // Validate UUID
        if (!$this->validateUuid()) {
            return ExitCode::USAGE;
        }

        // Check helpers configuration
        if (!$this->checkHelpersConfigured()) {
            return ExitCode::CONFIG;
        }

        // Find user
        $user = $this->findUser();
        if ($user === null) {
            return ExitCode::DATAERR;
        }

        // Check if already anonymized
        if ($this->isUserAnonymized($user)) {
            $this->stderr("User is already anonymized.\n", Console::FG_YELLOW);
            return ExitCode::OK;
        }

        // Confirmation
        if (!$this->force) {
            $this->stdout("WARNING: This action will permanently anonymize user data.\n", Console::FG_YELLOW);
            $this->stdout("User UUID: {$this->uuid}\n");

            if (!$this->confirm('Do you want to proceed?')) {
                $this->stdout("Operation cancelled.\n");
                return ExitCode::OK;
            }
        }

        // Execute anonymization
        $module = $this->getModule();
        $executor = new AnonymizationExecutor(
            $module->getHelpers(),
            $module->getDefaultOptions()
        );

        $result = $executor->execute($user);

        // Log the action
        Yii::info("User anonymized via CLI: UUID={$this->uuid}", 'anonymizer');

        // Output results
        if ($this->format === 'json') {
            $this->outputJson($result->toArray());
        } else {
            $this->outputTextExecution($result);
        }

        if (!$result->success) {
            return ExitCode::UNSPECIFIED_ERROR;
        }

        return ExitCode::OK;
    }

    /**
     * List configured helpers
     *
     * Usage: yii anonymizer/helpers
     *
     * @return int Exit code
     */
    public function actionHelpers(): int
    {
        $module = $this->getModule();
        $helpers = $module->getHelpers();

        if (empty($helpers)) {
            $this->stderr("No helpers configured.\n", Console::FG_YELLOW);
            $this->stderr("Configure helpers in the module configuration.\n");
            return ExitCode::CONFIG;
        }

        $this->stdout("Configured Anonymization Helpers:\n\n", Console::BOLD);

        foreach ($helpers as $index => $helperClass) {
            $number = $index + 1;
            $this->stdout("{$number}. ", Console::FG_CYAN);
            $this->stdout("{$helperClass}\n");
            $this->stdout("   Description: ", Console::FG_GREY);
            $this->stdout($helperClass::getDescription() . "\n\n");
        }

        return ExitCode::OK;
    }

    /**
     * Validate UUID parameter
     *
     * @return bool
     */
    protected function validateUuid(): bool
    {
        if (empty($this->uuid)) {
            $this->stderr("Error: UUID is required.\n", Console::FG_RED);
            $this->stderr("Usage: yii anonymizer/{$this->action->id} --uuid=<uuid>\n");
            return false;
        }

        if (!preg_match(self::UUID_PATTERN, $this->uuid)) {
            $this->stderr("Error: Invalid UUID format.\n", Console::FG_RED);
            $this->stderr("Expected RFC 4122 UUID v4 format.\n");
            return false;
        }

        return true;
    }

    /**
     * Check if helpers are configured
     *
     * @return bool
     */
    protected function checkHelpersConfigured(): bool
    {
        $module = $this->getModule();
        $helpers = $module->getHelpers();

        if (empty($helpers)) {
            $this->stderr("Warning: No anonymization helpers configured.\n", Console::FG_YELLOW);
            $this->stderr("Configure helpers in the module configuration to enable anonymization.\n");
            $this->stderr("Example:\n");
            $this->stderr("  'helpers' => [\n");
            $this->stderr("      'app\\helpers\\UserAnonymizationHelper',\n");
            $this->stderr("  ],\n");
            return false;
        }

        return true;
    }

    /**
     * Find user by UUID
     *
     * @return mixed|null User model or null
     */
    protected function findUser()
    {
        $module = $this->getModule();
        $user = $module->findUserByUuid($this->uuid);

        if ($user === null) {
            $this->stderr("Error: User not found for UUID: {$this->uuid}\n", Console::FG_RED);
            return null;
        }

        return $user;
    }

    /**
     * Check if user is already anonymized
     *
     * @param mixed $user
     * @return bool
     */
    protected function isUserAnonymized($user): bool
    {
        if (isset($user->gdpr_deleted) && $user->gdpr_deleted == 1) {
            return true;
        }

        $module = $this->getModule();
        $prefix = $module->anonymizationPrefix;

        if (isset($user->username) && str_starts_with($user->username, $prefix)) {
            return true;
        }

        if (isset($user->email) && str_starts_with($user->email, 'anonymized_')) {
            return true;
        }

        return false;
    }

    /**
     * Get the anonymizer module instance
     *
     * @return Module
     */
    protected function getModule(): Module
    {
        return Yii::$app->getModule('anonymizer');
    }

    /**
     * Output JSON formatted result
     *
     * @param array $data
     */
    protected function outputJson(array $data): void
    {
        $this->stdout(Json::encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /**
     * Output text formatted analysis result
     *
     * @param \dmstr\anonymizer\components\AnonymizationResult $result
     * @param mixed $user
     */
    protected function outputTextAnalysis($result, $user): void
    {
        $this->stdout("\n");
        $this->stdout("=== Anonymization Analysis (Dry-Run) ===\n\n", Console::BOLD);

        $this->stdout("User UUID: ", Console::FG_GREY);
        $this->stdout("{$this->uuid}\n");

        if (isset($user->id)) {
            $this->stdout("User ID: ", Console::FG_GREY);
            $this->stdout("{$user->id}\n");
        }

        $this->stdout("Already Anonymized: ", Console::FG_GREY);
        $isAnonymized = $this->isUserAnonymized($user);
        $this->stdout($isAnonymized ? "Yes\n" : "No\n", $isAnonymized ? Console::FG_YELLOW : Console::FG_GREEN);

        $this->stdout("\nHelpers Configured: ", Console::FG_GREY);
        $this->stdout("{$result->helpersExecuted}\n");

        if (!empty($result->totalRecordsUpdated)) {
            $this->stdout("\nRecords that would be updated:\n", Console::BOLD);
            foreach ($result->totalRecordsUpdated as $table => $count) {
                $this->stdout("  - {$table}: ", Console::FG_CYAN);
                $this->stdout("{$count}\n");
            }
        }

        if (!empty($result->results)) {
            $this->stdout("\nHelper Details:\n", Console::BOLD);
            foreach ($result->results as $helperResult) {
                $this->stdout("  [{$helperResult['helper']}]\n", Console::FG_PURPLE);
                $this->stdout("    Description: {$helperResult['description']}\n");
                if (isset($helperResult['result']['records_updated'])) {
                    foreach ($helperResult['result']['records_updated'] as $table => $count) {
                        $this->stdout("    - {$table}: {$count}\n");
                    }
                }
            }
        }

        $this->stdout("\n");
    }

    /**
     * Output text formatted execution result
     *
     * @param \dmstr\anonymizer\components\AnonymizationResult $result
     */
    protected function outputTextExecution($result): void
    {
        $this->stdout("\n");

        if ($result->success) {
            $this->stdout("=== Anonymization Completed ===\n\n", Console::BOLD, Console::FG_GREEN);
        } else {
            $this->stdout("=== Anonymization Completed with Errors ===\n\n", Console::BOLD, Console::FG_YELLOW);
        }

        $this->stdout("User UUID: ", Console::FG_GREY);
        $this->stdout("{$this->uuid}\n");

        $this->stdout("Helpers Executed: ", Console::FG_GREY);
        $this->stdout("{$result->helpersExecuted}\n");

        $this->stdout("Timestamp: ", Console::FG_GREY);
        $this->stdout("{$result->timestamp}\n");

        if (!empty($result->totalRecordsUpdated)) {
            $this->stdout("\nRecords Updated:\n", Console::BOLD);
            foreach ($result->totalRecordsUpdated as $table => $count) {
                $this->stdout("  - {$table}: ", Console::FG_CYAN);
                $this->stdout("{$count}\n", Console::FG_GREEN);
            }
        }

        if (!empty($result->errors)) {
            $this->stdout("\nErrors:\n", Console::BOLD, Console::FG_RED);
            foreach ($result->errors as $error) {
                $this->stderr("  - {$error}\n", Console::FG_RED);
            }
        }

        $this->stdout("\n");
    }
}
