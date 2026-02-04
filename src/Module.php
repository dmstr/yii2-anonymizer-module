<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer;

use bizley\jwt\JwtHttpBearerAuth;
use dmstr\anonymizer\interfaces\AnonymizationHelperInterface;
use dmstr\web\traits\AccessBehaviorTrait;
use Yii;
use yii\base\InvalidConfigException;

/**
 * Anonymizer Module
 *
 * Provides REST endpoints and CLI commands for GDPR-compliant user data anonymization.
 *
 * Configuration example:
 * ```php
 * 'modules' => [
 *     'anonymizer' => [
 *         'class' => 'dmstr\anonymizer\Module',
 *         'helpers' => [
 *             'app\helpers\UserAnonymizationHelper',
 *             'app\helpers\ProfileAnonymizationHelper',
 *         ],
 *         'userModelClass' => 'app\models\User',
 *         'requiredRole' => 'admin',
 *         'anonymizationPrefix' => 'anon_',
 *         'anonymizationDomain' => 'anonymized.local',
 *     ],
 * ],
 * ```
 *
 * @package dmstr\anonymizer
 */
class Module extends \yii\base\Module
{
    use AccessBehaviorTrait {
        AccessBehaviorTrait::behaviors as accessBehaviors;
    }

    /**
     * @var string Module version
     */
    public const VERSION = '1.0.0';

    /**
     * @var array<string> List of helper class names that implement AnonymizationHelperInterface
     */
    public array $helpers = [];

    /**
     * @var string User model class that must have a static findUserByUuid($uuid) method
     */
    public string $userModelClass = '';

    /**
     * @var string|null Required role for REST endpoint access. If null, only JWT validation is required.
     */
    public ?string $requiredRole = null;

    /**
     * @var string Prefix for anonymized data (e.g., 'anon_' results in 'anon_abc123')
     */
    public string $anonymizationPrefix = 'anon_';

    /**
     * @var string Domain for anonymized email addresses (e.g., 'anonymized.local')
     */
    public string $anonymizationDomain = 'anonymized.local';

    /**
     * @var array<string> Validated helper class names (populated in init())
     */
    private array $validatedHelpers = [];

    /**
     * @inheritdoc
     * @throws InvalidConfigException
     */
    public function init(): void
    {
        parent::init();

        // Disable session for REST API
        if (!Yii::$app instanceof \yii\console\Application) {
            Yii::$app->user->enableSession = false;
            Yii::$app->user->enableAutoLogin = false;
        }

        // Validate configuration
        $this->validateConfiguration();

        // Validate and register helpers
        $this->validateHelpers();
    }

    /**
     * Validate module configuration
     *
     * @throws InvalidConfigException
     */
    protected function validateConfiguration(): void
    {
        if (empty($this->userModelClass)) {
            throw new InvalidConfigException(
                'The "userModelClass" property must be set and must reference a class with findUserByUuid() method.'
            );
        }

        if (!class_exists($this->userModelClass)) {
            throw new InvalidConfigException(
                "User model class '{$this->userModelClass}' does not exist."
            );
        }

        if (!method_exists($this->userModelClass, 'findUserByUuid')) {
            throw new InvalidConfigException(
                "User model class '{$this->userModelClass}' must implement a static findUserByUuid(\$uuid) method."
            );
        }
    }

    /**
     * Validate all configured helpers
     *
     * @throws InvalidConfigException
     */
    protected function validateHelpers(): void
    {
        foreach ($this->helpers as $helperClass) {
            // Check if class exists
            if (!class_exists($helperClass)) {
                throw new InvalidConfigException(
                    "Anonymization helper class '{$helperClass}' not found. " .
                    "Please ensure the class exists and is autoloadable."
                );
            }

            // Check if class implements the interface
            if (!is_subclass_of($helperClass, AnonymizationHelperInterface::class)) {
                throw new InvalidConfigException(
                    "Anonymization helper class '{$helperClass}' does not implement " .
                    AnonymizationHelperInterface::class . ". " .
                    "All helpers must implement this interface."
                );
            }

            $this->validatedHelpers[] = $helperClass;
        }

        if (empty($this->validatedHelpers)) {
            Yii::warning(
                'No anonymization helpers configured. ' .
                'Configure helpers in the module configuration to enable anonymization.',
                'anonymizer'
            );
        } else {
            Yii::info(
                'Anonymizer module initialized with ' . count($this->validatedHelpers) . ' helper(s): ' .
                implode(', ', $this->validatedHelpers),
                'anonymizer'
            );
        }
    }

    /**
     * Get validated helper class names
     *
     * @return array<string>
     */
    public function getHelpers(): array
    {
        return $this->validatedHelpers;
    }

    /**
     * Get default anonymization options
     *
     * @return array
     */
    public function getDefaultOptions(): array
    {
        return [
            'prefix' => $this->anonymizationPrefix,
            'domain' => $this->anonymizationDomain,
        ];
    }

    /**
     * Find user by UUID using the configured user model
     *
     * @param string $uuid
     * @return mixed|null User model instance or null if not found
     */
    public function findUserByUuid(string $uuid)
    {
        $userModelClass = $this->userModelClass;
        return $userModelClass::findUserByUuid($uuid);
    }

    /**
     * @inheritdoc
     */
    public function beforeAction($action)
    {
        // Skip authentication for OPTIONS requests (CORS preflight)
        if ($action->id === 'options') {
            Yii::info('OPTIONS preflight request - skipping authentication', 'anonymizer');
            return parent::beforeAction($action);
        }

        // Set JSON response format for non-console applications
        if (!Yii::$app instanceof \yii\console\Application) {
            Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;
            Yii::$app->user->enableSession = false;
            Yii::$app->user->loginUrl = null;

            // Set CORS credentials header
            Yii::$app->response->getHeaders()->set('Access-Control-Allow-Credentials', 'true');
        }

        return parent::beforeAction($action);
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        // Skip behaviors for console application
        if (Yii::$app instanceof \yii\console\Application) {
            return parent::behaviors();
        }

        $behaviors = [];

        // CORS filter must be defined BEFORE any auth behavior
        // See: https://www.yiiframework.com/doc/guide/2.0/en/rest-controllers#cors
        $behaviors['corsFilter'] = [
            'class' => \yii\filters\Cors::class,
            'cors' => [
                'Origin' => ['*'],
                'Access-Control-Request-Method' => ['GET', 'DELETE', 'OPTIONS'],
                'Access-Control-Request-Headers' => ['*'],
                'Access-Control-Allow-Credentials' => false,
                'Access-Control-Max-Age' => 3600,
                'Access-Control-Allow-Headers' => ['Origin', 'Content-Type', 'Accept', 'Authorization'],
            ],
        ];

        $behaviors += parent::behaviors(); //
        $accessBehavior = self::accessBehaviors()['access'];
        $accessBehavior['except'] = [
            '*/options'
        ];

        // JWT authentication
        $behaviors['auth'] = [
            'class' => JwtHttpBearerAuth::class,
            'optional' => [
                '*/options',
            ],
        ];

        $behaviors["access"] = $accessBehavior;
        return $behaviors;
    }

}
