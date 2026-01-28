<?php

/**
 * @link https://github.com/dmstr
 * @copyright Copyright (c) 2026 dmstr
 */

namespace dmstr\anonymizer\helpers;

use dmstr\anonymizer\interfaces\AnonymizationHelperInterface;
use Yii;
use yii\db\ActiveRecord;
use yii\db\Exception;

/**
 * Anonymization helper for 2amigos/yii2-usuario tables
 *
 * Handles anonymization of:
 * - app_user: Core user data (username, email, password, etc.)
 * - app_profile: User profile data (name, bio, location, etc.)
 * - app_social_account: Social login data (username, email, data payload)
 *
 * @package dmstr\anonymizer\helpers
 */
class UsuarioAnonymizationHelper implements AnonymizationHelperInterface
{
    /**
     * @var string Table name for users
     */
    protected const TABLE_USER = '{{%user}}';

    /**
     * @var string Table name for profiles
     */
    protected const TABLE_PROFILE = '{{%profile}}';

    /**
     * @var string Table name for social accounts
     */
    protected const TABLE_SOCIAL_ACCOUNT = '{{%social_account}}';

    /**
     * @inheritdoc
     */
    public static function anonymize($user, array $options = []): array
    {
        $userId = self::getUserId($user);
        if ($userId === null) {
            return [
                'success' => false,
                'message' => 'Invalid user object - could not determine user ID',
                'records_updated' => [],
            ];
        }

        $prefix = $options['prefix'] ?? 'anon_';
        $domain = $options['domain'] ?? 'anonymized.local';

        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            $recordsUpdated = [];

            // Anonymize app_user table
            $userResult = self::anonymizeUserTable($userId, $prefix, $domain);
            if ($userResult > 0) {
                $recordsUpdated['user'] = $userResult;
            }

            // Anonymize app_profile table
            $profileResult = self::anonymizeProfileTable($userId, $prefix);
            if ($profileResult > 0) {
                $recordsUpdated['profile'] = $profileResult;
            }

            // Anonymize app_social_account table
            $socialResult = self::anonymizeSocialAccountTable($userId, $domain);
            if ($socialResult > 0) {
                $recordsUpdated['social_account'] = $socialResult;
            }

            $transaction->commit();

            Yii::info("Usuario tables anonymized for user ID {$userId}", 'anonymizer');

            return [
                'success' => true,
                'message' => 'Usuario user data anonymized successfully',
                'records_updated' => $recordsUpdated,
            ];

        } catch (Exception $e) {
            $transaction->rollBack();
            Yii::error("Usuario anonymization failed for user ID {$userId}: " . $e->getMessage(), 'anonymizer');

            return [
                'success' => false,
                'message' => 'Anonymization failed: ' . $e->getMessage(),
                'records_updated' => [],
            ];
        }
    }

    /**
     * @inheritdoc
     */
    public static function analyze($user, array $options = []): array
    {
        $userId = self::getUserId($user);
        if ($userId === null) {
            return [
                'success' => false,
                'message' => 'Invalid user object - could not determine user ID',
                'records_updated' => [],
            ];
        }

        $db = Yii::$app->db;
        $recordsUpdated = [];

        // Count user records
        $userCount = (int) $db->createCommand(
            'SELECT COUNT(*) FROM ' . self::TABLE_USER . ' WHERE id = :id',
            [':id' => $userId]
        )->queryScalar();
        if ($userCount > 0) {
            $recordsUpdated['user'] = $userCount;
        }

        // Count profile records
        $profileCount = (int) $db->createCommand(
            'SELECT COUNT(*) FROM ' . self::TABLE_PROFILE . ' WHERE user_id = :user_id',
            [':user_id' => $userId]
        )->queryScalar();
        if ($profileCount > 0) {
            $recordsUpdated['profile'] = $profileCount;
        }

        // Count social account records
        $socialCount = (int) $db->createCommand(
            'SELECT COUNT(*) FROM ' . self::TABLE_SOCIAL_ACCOUNT . ' WHERE user_id = :user_id',
            [':user_id' => $userId]
        )->queryScalar();
        if ($socialCount > 0) {
            $recordsUpdated['social_account'] = $socialCount;
        }

        return [
            'success' => true,
            'message' => 'Analysis complete - dry run mode, no changes made',
            'records_updated' => $recordsUpdated,
            'data' => [
                'mode' => 'dry-run',
                'user_id' => $userId,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public static function getDescription(): string
    {
        return 'Anonymizes yii2-usuario tables (user, profile, social_account)';
    }

    /**
     * Anonymize the user table
     *
     * @param int $userId
     * @param string $prefix
     * @param string $domain
     * @return int Number of records updated
     * @throws Exception
     */
    protected static function anonymizeUserTable(int $userId, string $prefix, string $domain): int
    {
        $db = Yii::$app->db;

        return $db->createCommand()->update(
            self::TABLE_USER,
            [
                'username' => "{$prefix}{$userId}",
                'email' => "anonymized_{$userId}@{$domain}",
                'password_hash' => Yii::$app->security->generatePasswordHash(Yii::$app->security->generateRandomString(32)),
                'auth_key' => Yii::$app->security->generateRandomString(32),
                'unconfirmed_email' => null,
                'registration_ip' => null,
                'updated_at' => time(),
                'gdpr_deleted' => 1,
            ],
            ['id' => $userId]
        )->execute();
    }

    /**
     * Anonymize the profile table
     *
     * @param int $userId
     * @param string $prefix
     * @return int Number of records updated
     * @throws Exception
     */
    protected static function anonymizeProfileTable(int $userId, string $prefix): int
    {
        $db = Yii::$app->db;

        return $db->createCommand()->update(
            self::TABLE_PROFILE,
            [
                'name' => "{$prefix}{$userId}",
                'public_email' => null,
                'gravatar_email' => null,
                'gravatar_id' => null,
                'location' => null,
                'website' => null,
                'bio' => 'This profile has been anonymized',
            ],
            ['user_id' => $userId]
        )->execute();
    }

    /**
     * Anonymize the social account table
     *
     * @param int $userId
     * @param string $domain
     * @return int Number of records updated
     * @throws Exception
     */
    protected static function anonymizeSocialAccountTable(int $userId, string $domain): int
    {
        $db = Yii::$app->db;

        $anonymizedData = json_encode([
            'anonymized' => true,
            'anonymized_at' => date('c'),
        ]);

        return $db->createCommand()->update(
            self::TABLE_SOCIAL_ACCOUNT,
            [
                'username' => "anonymized_{$userId}",
                'email' => "anonymized_{$userId}@{$domain}",
                'data' => $anonymizedData,
            ],
            ['user_id' => $userId]
        )->execute();
    }

    /**
     * Extract user ID from user object
     *
     * @param mixed $user
     * @return int|null
     */
    protected static function getUserId($user): ?int
    {
        if ($user === null) {
            return null;
        }

        // If it's an ActiveRecord with id property
        if ($user instanceof ActiveRecord && isset($user->id)) {
            return (int) $user->id;
        }

        // If it has a getId() method
        if (is_object($user) && method_exists($user, 'getId')) {
            return (int) $user->getId();
        }

        // If it has an id property
        if (is_object($user) && property_exists($user, 'id')) {
            return (int) $user->id;
        }

        // If it's already an integer
        if (is_int($user)) {
            return $user;
        }

        // If it's a numeric string
        if (is_string($user) && is_numeric($user)) {
            return (int) $user;
        }

        return null;
    }
}
