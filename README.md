# dmstr/yii2-anonymizer-module

GDPR-compliant user data anonymization module for Yii2 applications.

## Features

- REST API endpoints for user data anonymization
- CLI commands for analysis and execution
- Configurable helper classes for custom anonymization logic
- JWT-based authentication for REST endpoints
- Dry-run mode for previewing changes
- Built-in helper for yii2-usuario tables

## Requirements

- PHP >= 8.1
- Yii2 >= 2.0.45
- bizley/jwt >= 4.0

## Installation

### Via Composer (recommended)

```bash
composer require dmstr/yii2-anonymizer-module
```

### Manual Installation (PSR-4 Autoload)

If not using Packagist, add to your `composer.json`:

```json
{
    "autoload": {
        "psr-4": {
            "dmstr\\anonymizer\\": "path/to/yii2-anonymizer-module/src"
        }
    }
}
```

Then run:

```bash
composer dump-autoload
```

### 2. Module Configuration

Add to `project/config/main.php` in the `$common['modules']` section:

```php
'anonymizer' => [
    'class' => \dmstr\anonymizer\Module::class,
    'helpers' => [
        \dmstr\anonymizer\helpers\UsuarioAnonymizationHelper::class,
        // Add application-specific helpers here
    ],
    'userModelClass' => \project\modules\user\models\User::class,
    'requiredRole' => 'Tech_User',  // Optional: Role required for REST access
    'anonymizationPrefix' => 'ANONYMIZED_USER_',
    'anonymizationDomain' => 'waldportal.local',
],
```

### 3. CLI Command Configuration

Add to `project/config/main.php` in the `$console['controllerMap']` section:

```php
$console = [
    'controllerMap' => [
        'anonymizer' => [
            'class' => \dmstr\anonymizer\commands\AnonymizerController::class,
        ],
    ],
];
```

### 4. URL Rules (REST API)

Add to `project/config/main.php` in the `urlManager.rules` section:

```php
[
    'class' => 'yii\rest\UrlRule',
    'controller' => ['anonymizer/anonymize'],
    'patterns' => [
        'DELETE {uuid}' => 'remove',
        'GET {uuid}' => 'analyze',
        'OPTIONS {uuid}' => 'options',
    ],
],
```

### 5. User Model Requirement

Your User model must implement a static `findUserByUuid()` method:

```php
public static function findUserByUuid(string $uuid): ?self
{
    $socialAccount = \Da\User\Model\SocialNetworkAccount::find()
        ->andWhere(['client_id' => $uuid])
        ->one();

    if (!$socialAccount) {
        return null;
    }

    return static::findOne($socialAccount->user_id);
}
```

## Usage

### REST API

**Anonymize user data:**

```bash
curl -X DELETE \
  "https://your-app.com/anonymizer/anonymize/550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer <jwt-token>"
```

**Analyze (dry-run):**

```bash
curl -X GET \
  "https://your-app.com/anonymizer/anonymize/550e8400-e29b-41d4-a716-446655440000" \
  -H "Authorization: Bearer <jwt-token>"
```

### CLI Commands

**List configured helpers:**

```bash
yii anonymizer/helpers
```

**Analyze user data (dry-run):**

```bash
yii anonymizer/analyze --uuid=550e8400-e29b-41d4-a716-446655440000
```

**Execute anonymization:**

```bash
yii anonymizer/execute --uuid=550e8400-e29b-41d4-a716-446655440000 --force
```

**JSON output format:**

```bash
yii anonymizer/analyze --uuid=<uuid> --format=json
```

## Creating Custom Helpers

Implement `AnonymizationHelperInterface`:

```php
<?php

namespace app\helpers;

use dmstr\anonymizer\interfaces\AnonymizationHelperInterface;
use Yii;

class CustomDataHelper implements AnonymizationHelperInterface
{
    public static function anonymize($user, array $options = []): array
    {
        // Perform anonymization
        $count = Yii::$app->db->createCommand()
            ->update('custom_table', ['data' => null], ['user_id' => $user->id])
            ->execute();

        return [
            'success' => true,
            'message' => 'Custom data anonymized',
            'records_updated' => ['custom_table' => $count],
        ];
    }

    public static function analyze($user, array $options = []): array
    {
        // Count affected records (dry-run)
        $count = (int) Yii::$app->db->createCommand(
            'SELECT COUNT(*) FROM custom_table WHERE user_id = :id',
            [':id' => $user->id]
        )->queryScalar();

        return [
            'success' => true,
            'message' => 'Analysis complete',
            'records_updated' => ['custom_table' => $count],
        ];
    }

    public static function getDescription(): string
    {
        return 'Anonymizes custom application data';
    }
}
```

## Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `helpers` | array | `[]` | List of helper class names |
| `userModelClass` | string | required | User model class with `findUserByUuid()` |
| `requiredRole` | string\|null | `null` | Role required for REST access |
| `anonymizationPrefix` | string | `'anon_'` | Prefix for anonymized values |
| `anonymizationDomain` | string | `'anonymized.local'` | Domain for anonymized emails |

## License

BSD-3-Clause
