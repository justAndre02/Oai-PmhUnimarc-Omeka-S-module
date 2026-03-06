<?php
/**
 * OaiPmhUnimarc — Omeka S Module
 *
 * Exposes Omeka S items in UNIMARC format via OAI-PMH,
 * compatible with the National Registry of Digital Objects (RNOD)
 * of the National Library of Portugal.
 *
 * @copyright André Freitas / USDB, 2026
 * @license   GNU GPLv3
 */
namespace OaiPmhUnimarc;

use Omeka\Module\AbstractModule;

class Module extends AbstractModule
{
    public function getConfig(): array
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function install(\Laminas\ServiceManager\ServiceLocatorInterface $services): void
    {
        $settings = $services->get('Omeka\Settings');
        $formats  = $settings->get('oaipmhrepository_metadata_formats', []);
        if (!in_array('oai_unimarc', $formats)) {
            $formats[] = 'oai_unimarc';
            $settings->set('oaipmhrepository_metadata_formats', $formats);
        }
    }

    public function uninstall(\Laminas\ServiceManager\ServiceLocatorInterface $services): void
    {
        $settings = $services->get('Omeka\Settings');
        $formats  = $settings->get('oaipmhrepository_metadata_formats', []);
        $formats  = array_values(array_filter($formats, fn($f) => $f !== 'oai_unimarc'));
        $settings->set('oaipmhrepository_metadata_formats', $formats);
    }
}
