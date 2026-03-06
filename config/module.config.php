<?php
/**
 * OaiPmhUnimarc module configuration.
 *
 * Registers the 'oai_unimarc' format in the OaiPmhRepository module (Daniel-KM v3.4.12+).
 *
 * Correct structure confirmed by the module.config.php of OaiPmhRepository:
 *  - metadata_formats.factories  → class => factory
 *  - metadata_formats.aliases    → OAI prefix => class
 * The factory is located in OaiPmhRepository\Service\OaiPmh\Metadata\MetadataFormatFactory
 */
return [
    'oaipmhrepository' => [
        'metadata_formats' => [
            'factories' => [
                \OaiPmhUnimarc\Metadata\Unimarc::class =>
                    \OaiPmhRepository\Service\OaiPmh\Metadata\MetadataFormatFactory::class,
            ],
            'aliases' => [
                'oai_unimarc' => \OaiPmhUnimarc\Metadata\Unimarc::class,
            ],
        ],
    ],
];
