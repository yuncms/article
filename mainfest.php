<?php
return [
    'id'=> 'article',
    'migrationPath' => '@vendor/yuncms/article/migrations',
    'translations' => [
        'yuncms/article' => [
            'class' => 'yii\i18n\PhpMessageSource',
            'sourceLanguage' => 'en-US',
            'basePath' => '@vendor/yuncms/article/messages',
        ],
    ],
    'backend' => [
        'class'=>'yuncms\article\backend\Module'
    ],
    'frontend' => [
        'class'=>'yuncms\article\frontend\Module'
    ],
];