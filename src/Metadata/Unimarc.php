<?php declare(strict_types=1);
/**
 * OaiPmhUnimarc — UNIMARC Format for OAI-PMH
 *
 * Generates UNIMARC/XML records from Omeka S items with Dublin Core Terms
 * (dcterms) and Bibliographic Ontology (bibo) vocabularies,
 * compatible with the RNOD harvester (National Library of Portugal).
 *
 * @copyright André Freitas / USDB, 2026
 * @license   GNU GPLv3
 */
namespace OaiPmhUnimarc\Metadata;

use DOMElement;
use OaiPmhRepository\OaiPmh\Metadata\AbstractMetadata;
use Omeka\Api\Representation\ItemRepresentation;

class Unimarc extends AbstractMetadata
{
    const METADATA_PREFIX    = 'oai_unimarc';
    const METADATA_NAMESPACE = 'http://www.loc.gov/MARC21/slim';
    const METADATA_SCHEMA    = 'http://www.loc.gov/standards/marcxml/schema/MARC21slim.xsd';
    const DEFAULT_ACCESS_LEVEL = 'Livre';
    const DEFAULT_LANGUAGE     = 'por';

    public function appendMetadata(DOMElement $metadataElement, ItemRepresentation $item): void
    {
        $document = $metadataElement->ownerDocument;

        $record = $document->createElementNS(self::METADATA_NAMESPACE, 'record');
        $metadataElement->appendChild($record);
        $record->setAttribute('xmlns:xsi', parent::XML_SCHEMA_NAMESPACE_URI);
        $record->setAttribute('xsi:schemaLocation', self::METADATA_NAMESPACE . ' ' . self::METADATA_SCHEMA);

        // Leader
        $leaderValue = $this->getFirstValue($item, 'bibo:volume') !== null
            ? '00000nab a2200000 i 4500'
            : '00000nam a2200000 i 4500';
        $record->appendChild($document->createElement('leader', $leaderValue));

        // 001 — Identificador
        $cf001 = $document->createElement('controlfield', 'USDB-' . $item->id());
        $cf001->setAttribute('tag', '001');
        $record->appendChild($cf001);

        // 100 — Dados de processo
        $pubYear = $this->extractYear($item);
        $this->appendDataField($record, '100', ' ', ' ', [
            'a' => date('Ymd') . 'd' . $pubYear . '    u||y0pory0103    ba',
        ]);

        // 101 — Língua
        $language = $this->getFirstValue($item, 'dcterms:language') ?? self::DEFAULT_LANGUAGE;
        $language = strtolower(substr($this->plainText($language), 0, 3));
        if (strlen($language) !== 3) {
            $language = self::DEFAULT_LANGUAGE;
        }
        $this->appendDataField($record, '101', '0', ' ', ['a' => $language]);

        // 200 — Título [OBRIGATÓRIO RNOD]
        $title = $this->getFirstValue($item, 'dcterms:title') ?? '[Sem título]';
        $this->appendDataField($record, '200', '1', ' ', ['a' => $this->plainText($title)]);

        // 210 — Editor + Data
        $publisher = $this->getFirstValue($item, 'dcterms:publisher');
        $year      = $this->extractYear($item);
        if ($publisher || $year) {
            $subfields = [];
            if ($publisher) $subfields['c'] = $this->plainText($publisher);
            if ($year)      $subfields['d'] = $year;
            $this->appendDataField($record, '210', ' ', ' ', $subfields);
        }

        // 215 — Numeração (bibo:volume)
        $volume = $this->getFirstValue($item, 'bibo:volume');
        if ($volume !== null) {
            $this->appendDataField($record, '215', ' ', ' ', ['v' => $this->plainText($volume)]);
        }

        // 300 — Nota de direitos
        $rights = $this->getFirstValue($item, 'dcterms:rights');
        if ($rights) {
            $this->appendDataField($record, '300', ' ', ' ', ['a' => $this->plainText($rights)]);
        }

        // 327 — Nota de conteúdo (bibo:content)
        $content = $this->getFirstValue($item, 'bibo:content');
        if ($content) {
            $this->appendDataField($record, '327', '1', ' ', ['a' => $this->plainText($content)]);
        }

        // 330 — Resumo (dcterms:description)
        $description = $this->getFirstValue($item, 'dcterms:description');
        if ($description) {
            $this->appendDataField($record, '330', ' ', ' ', ['a' => $this->plainText($description)]);
        }

        // 606 — Assuntos (repetível)
        foreach ($this->getAllValues($item, 'dcterms:subject') as $subject) {
            $this->appendDataField($record, '606', ' ', ' ', ['a' => $this->plainText($subject)]);
        }

        // 700 / 701 — Criadores
        foreach ($this->getAllValues($item, 'dcterms:creator') as $index => $creator) {
            $tag = ($index === 0) ? '700' : '701';
            $this->appendDataField($record, $tag, ' ', '1', ['a' => $this->plainText($creator)]);
        }

        // 856 — URLs [OBRIGATÓRIO RNOD]
        $mediaInfo = $this->getMediaInfo($item);
        if ($mediaInfo['original_url']) {
            $subfields856 = ['u' => $mediaInfo['original_url']];
            if ($mediaInfo['mime_type']) $subfields856['q'] = $mediaInfo['mime_type'];
            $this->appendDataField($record, '856', '4', '0', $subfields856);
        } else {
            $itemUrl = $this->singleIdentifier($item);
            if ($itemUrl) {
                $this->appendDataField($record, '856', '4', '0', ['u' => $itemUrl]);
            }
        }
        if ($mediaInfo['thumbnail_url']) {
            $this->appendDataField($record, '856', '4', '1', ['u' => $mediaInfo['thumbnail_url']]);
        }

        // 958 — Campos específicos RNOD
        $accessLevel = $this->inferAccessLevel($item);
        $this->appendDataField($record, '958', ' ', ' ', [
            'b' => $accessLevel,   // Direitos de acesso: Livre / Condicionado / Pago / Interno
            'c' => 'Digitalizado', // Tipo de objeto: Nascido digital / Digitalizado / Intenção de digitalização
            'd' => '1',         // Comentar para não exportar para a Europeana
        ]);
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    private function appendDataField(DOMElement $parent, string $tag, string $ind1, string $ind2, array $subfields): void
    {
        $document  = $parent->ownerDocument;
        $datafield = $document->createElement('datafield');
        $datafield->setAttribute('tag', $tag);
        $datafield->setAttribute('ind1', $ind1);
        $datafield->setAttribute('ind2', $ind2);

        foreach ($subfields as $code => $value) {
            if ($value === null || $value === '') continue;
            $subfield = $document->createElement('subfield');
            $subfield->setAttribute('code', (string) $code);
            $subfield->appendChild($document->createTextNode((string) $value));
            $datafield->appendChild($subfield);
        }

        $parent->appendChild($datafield);
    }

    private function getFirstValue(ItemRepresentation $item, string $property): ?string
    {
        $value = $item->value($property);
        return $value !== null ? (string) $value : null;
    }

    private function getAllValues(ItemRepresentation $item, string $property): array
    {
        $values = $item->value($property, ['all' => true]);
        return empty($values) ? [] : array_map(fn($v) => (string) $v, $values);
    }

    private function plainText(string $html): string
    {
        $text = preg_replace('/<br\s*\/?>/i', ' ', $html);
        $text = preg_replace('/<\/p>/i', ' ', $text ?? $html);
        $text = strip_tags($text ?? $html);
        $text = preg_replace('/\s+/', ' ', $text ?? '');
        return trim($text ?? '');
    }

    private function extractYear(ItemRepresentation $item): string
    {
        foreach (['dcterms:issued', 'dcterms:date'] as $property) {
            $val = $this->getFirstValue($item, $property);
            if ($val && preg_match('/(\d{4})/', $val, $m)) {
                return $m[1];
            }
        }
        return '    ';
    }

    private function getMediaInfo(ItemRepresentation $item): array
    {
        $result = ['original_url' => null, 'thumbnail_url' => null, 'mime_type' => null];
        $medias = $item->media();
        if (empty($medias)) return $result;

        $media = reset($medias);
        $originalUrl = $media->originalUrl();
        if ($originalUrl) {
            $result['original_url'] = $originalUrl;
            $result['mime_type']    = $media->mediaType() ?: $this->inferMimeType($originalUrl);
        }
        $thumbnailUrl = $media->thumbnailUrl('medium');
        if ($thumbnailUrl) {
            $result['thumbnail_url'] = $thumbnailUrl;
        }
        return $result;
    }

    private function inferMimeType(string $url): ?string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? $url, PATHINFO_EXTENSION));
        return [
            'pdf'  => 'application/pdf',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png'  => 'image/png',
            'tif'  => 'image/tiff',
            'tiff' => 'image/tiff',
            'gif'  => 'image/gif',
            'webp' => 'image/webp',
            'mp3'  => 'audio/mpeg',
            'mp4'  => 'video/mp4',
        ][$ext] ?? null;
    }

    private function inferAccessLevel(ItemRepresentation $item): string
    {
        $rights = strtolower($this->getFirstValue($item, 'dcterms:rights') ?? '');
        foreach (['restri', 'reservad', 'privad', 'condicion', 'copyright', 'all rights reserved', 'pago', 'subscri'] as $kw) {
            if (str_contains($rights, $kw)) return 'Condicionado';
        }
        return self::DEFAULT_ACCESS_LEVEL;
    }
}
