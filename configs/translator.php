<?php
/**
 * Translator component options.
 * - default language to be used
 * - bundle name to store plural phrases
 * - language options, including bundle storage directory (memory location) and pluralizer class
 */
use Spiral\Translator\Pluralizers;

return [
    'default'   => 'en',
    'plurals'   => 'plural-phrases',
    'languages' => [
        'en' => [
            'directory'  => 'i18n/english/',
            'pluralizer' => Pluralizers\EnglishPluralizer::class
        ],
        'ru' => [
            'directory'  => 'i18n/russian/',
            'pluralizer' => Pluralizers\RussianPluralizer::class
        ]
    ]
];