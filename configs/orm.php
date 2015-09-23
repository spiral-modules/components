<?php
/**
 * ORM configuration and mapping.
 * - default state of entity cache and it's maxium size
 * - mutators to be automatically applied to record fields based on it's type
 * - mutator aliases to be used in model definitions
 * - relation classes including schema generators, loaders and relation representers
 */
use Spiral\ORM\Entities;
use Spiral\ORM\RecordEntity;

return [
    'entityCache'    => [
        'enabled' => true,
        'maxSize' => 1000
    ],
    'mutators'       => [
        'php:int'    => ['setter' => 'intval', 'getter' => 'intval'],
        'php:float'  => ['setter' => 'floatval', 'getter' => 'floatval'],
        'php:string' => ['setter' => 'strval'],
        'php:bool'   => ['setter' => 'boolval', 'getter' => 'boolval']
    ],
    'mutatorAliases' => [
    ],
    'relations'      => [
        RecordEntity::BELONGS_TO         => [
            'class'  => Entities\Relations\BelongsTo::class,
            'schema' => Entities\Schemas\Relations\BelongsToSchema::class,
            'loader' => Entities\Loaders\BelongsToLoader::class
        ],
        RecordEntity::BELONGS_TO_MORPHED => [
            'class'  => Entities\Relations\BelongsToMorphed::class,
            'schema' => Entities\Schemas\Relations\BelongsToMorphedSchema::class
        ],
        RecordEntity::HAS_ONE            => [
            'class'  => Entities\Relations\HasOne::class,
            'schema' => Entities\Schemas\Relations\HasOneSchema::class,
            'loader' => Entities\Loaders\HasOneLoader::class
        ],
        RecordEntity::HAS_MANY           => [
            'class'  => Entities\Relations\HasMany::class,
            'schema' => Entities\Schemas\Relations\HasManySchema::class,
            'loader' => Entities\Loaders\HasManyLoader::class
        ],
        RecordEntity::MANY_TO_MANY       => [
            'class'  => Entities\Relations\ManyToMany::class,
            'schema' => Entities\Schemas\Relations\ManyToManySchema::class,
            'loader' => Entities\Loaders\ManyToManyLoader::class
        ],
        RecordEntity::MANY_TO_MORPHED    => [
            'class'  => Entities\Relations\ManyToMorphed::class,
            'schema' => Entities\Schemas\Relations\ManyToMorphedSchema::class
        ]
    ]
];