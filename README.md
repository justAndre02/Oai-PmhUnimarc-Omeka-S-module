# OaiPmhUnimarc — Omeka S Module

Exposes Omeka S items in **UNIMARC format via OAI-PMH**, compatible with the
[National Registry of Digital Objects (RNOD)](https://rnod.bnportugal.gov.pt/)
of the National Library of Portugal.

This module extends the [OAI-PMH Repository module by Daniel Berthereau](https://gitlab.com/Daniel-KM/Omeka-S-module-OaiPmhRepository)
by adding a new `oai_unimarc` metadata format, mapped from Dublin Core Terms (`dcterms`)
and Bibliographic Ontology (`bibo`) vocabularies to UNIMARC fields required by the RNOD harvester.

---

## Requirements

| Component | Minimum Version |
|---|---|
| Omeka S | 4.x |
| [OaiPmhRepository](https://gitlab.com/Daniel-KM/Omeka-S-module-OaiPmhRepository) (Daniel-KM) | 3.4.12+ |
| [Common](https://gitlab.com/Daniel-KM/Omeka-S-module-Common) (Daniel-KM) | 3.4.x |
| PHP | 8.0+ |

---

## Installation

### 1. Download

Download the latest release ZIP from the [Releases](../../releases) page.

### 2. Copy the module to the server

```bash
# Unzip and copy the folder to the Omeka S modules directory
unzip OaiPmhUnimarc.zip -d /path/to/folder/modules/

# Set correct permissions
chown -R user:user /path/to/folder/modules/OaiPmhUnimarc
chmod -R 755 /path/to/folder/modules/OaiPmhUnimarc
```

### 3. Enable in the admin panel

1. Go to **Admin → Modules**
2. Locate **OaiPmhUnimarc** in the list
3. Click **Install**

The module automatically registers the `oai_unimarc` format in the OAI-PMH Repository
settings. No manual database configuration is required.

### 4. Verify the format is available

Access the OAI-PMH endpoint:

```
http://YOUR-SERVER/oai?verb=ListMetadataFormats
```

The `oai_unimarc` format should appear in the list of supported formats.

### 5. Test an individual record

```
http://YOUR-SERVER/oai?verb=GetRecord&metadataPrefix=oai_unimarc&identifier=oai:YOUR-DOMAIN:1
```

### 6. Test the complete listing

```
http://YOUR-SERVER/oai?verb=ListRecords&metadataPrefix=oai_unimarc
```

---

## Metadata Mapping

| Omeka S Property | UNIMARC Field | Subfield | Notes |
|---|---|---|---|
| `dcterms:title` | **200** | `$a` | **RNOD Required** |
| `dcterms:creator` (1st) | **700** | `$a` | Primary responsibility |
| `dcterms:creator` (2nd+) | **701** | `$a` | Alternative responsibility |
| `dcterms:issued` / `dcterms:date` | **210** | `$d` | Year automatically extracted |
| `dcterms:publisher` | **210** | `$c` | |
| `bibo:volume` | **215** | `$v` | Issue/volume number |
| `dcterms:description` | **330** | `$a` | HTML automatically stripped |
| `bibo:content` | **327** | `$a` | HTML automatically stripped |
| `dcterms:subject` | **606** | `$a` | Repeatable |
| `dcterms:language` | **101** | `$a` | Default: `por` |
| `dcterms:rights` | **300** | `$a` | |
| Original media URL | **856** ind=`40` | `$u` + `$q` | **RNOD Required** |
| Omeka thumbnail URL | **856** ind=`41` | `$u` | Min. 400px width recommended |
| Inferred from `dcterms:rights` | **958** | `$b` | RNOD access level |
| Fixed value | **958** | `$c` | `Digitalizado` |

---

## Auto-Generated Fields

| UNIMARC Field | Value |
|---|---|
| **Leader** | `00000nab a2200000 i 4500` (serial) or `00000nam a2200000 i 4500` (monograph) |
| **001** | `USDB-{omeka_item_id}` |
| **100 $a** | Processing date + publication year + country/language code |
| **101 $a** | `por` (default, overridden by `dcterms:language` if present) |

The Leader type is automatically inferred: items with `bibo:volume` are treated as
serial publications (`nab`), all others as monographs (`nam`).

---

## RNOD-Specific Fields (958)

The `958` field is mapped automatically by the RNOD harvester:

| Subfield | RNOD Field | Value |
|---|---|---|
| `$a` | Institution | Filled automatically by RNOD based on partner login |
| `$b` | Access rights | `Livre` / `Condicionado` / `Pago` / `Interno` |
| `$c` | Object type | `Digitalizado` (fixed) |
| `$d` | Export to Europeana | `1` — **commented out by default**, uncomment in `Unimarc.php` to enable |

### Access Level Configuration

The `958 $b` field is automatically inferred from `dcterms:rights`:
- **`Livre`** — default, when no restriction keywords are found
- **`Condicionado`** — when `dcterms:rights` contains words like "restricted", "copyright", "reservado", etc.

To set a fixed value for all items, edit the constant in `src/Metadata/Unimarc.php`:

```php
const DEFAULT_ACCESS_LEVEL = 'Livre'; // Change here
```

### Europeana Export

To enable export to Europeana via RNOD, uncomment the `$d` line in `src/Metadata/Unimarc.php`:

```php
$this->appendDataField($record, '958', ' ', ' ', [
    'b' => $accessLevel,
    'c' => 'Digitalizado',
    'd' => '1', // Uncomment to export to Europeana
]);
```

---

## RNOD Registration

After verifying the endpoint works correctly, register your repository with RNOD
using the OAI-PMH base endpoint:

```
https://YOUR-DOMAIN/oai
```

RNOD will automatically harvest records using the `oai_unimarc` format.

For site-specific repositories (by-site mode):

```
https://YOUR-DOMAIN/site/s/YOUR-SITE-SLUG/oai
```

---

## Uninstallation

When the module is uninstalled via **Admin → Modules → Uninstall**, the `oai_unimarc`
format is automatically removed from the OAI-PMH Repository settings. No manual
database cleanup is required.

---

## Credits

- Developed by **André Freitas** / [Casa de Sarmento — University of Minho](https://csarmento.uminho.pt)
- Metadata mapping based on the [RNOD documentation](https://rnod.bnportugal.gov.pt/) by the **Biblioteca Nacional de Portugal**
- Built as an extension of the [OAI-PMH Repository module](https://gitlab.com/Daniel-KM/Omeka-S-module-OaiPmhRepository) by **Daniel Berthereau**

---

## License

GNU General Public License v3.0 — see [`LICENSE`](LICENSE) file.
