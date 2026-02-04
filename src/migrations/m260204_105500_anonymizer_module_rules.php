<?php

use dmstr\rbacMigration\Migration;
use yii\rbac\Item;

class m260204_105400_anonymizer_module_rules extends Migration
{
    public $defaultFlags = [
        'replace' => false,
        'ensure' => self::PRESENT
    ];

    public $privileges = [
        [
            'name' => 'AnonymizerAccess',
            'type' => Item::TYPE_ROLE,
            'description' => 'User Anonymizer Access',
            'children' => [
                [
                    'name' => 'anonymizer',
                    'type' => Item::TYPE_PERMISSION,
                    'description' => 'User Anonymizer Access Main Module'
                ],
                [
                    'name' => 'anonymizer_anonymize',
                    'type' => Item::TYPE_PERMISSION,
                    'description' => 'Anonymize Users'
                ]
            ]
        ]
    ];
}
