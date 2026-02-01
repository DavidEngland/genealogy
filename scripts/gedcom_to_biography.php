<?php
// Stub: GEDCOM to biography converter.
//
// Usage:
//   php scripts/gedcom_to_biography.php --input GEDs/example.ged [--person @I123@] [--out path] [--json]
// Options:
//   --input FILE   Path to GEDCOM file (required)
//   --person ID    Specific individual ID (e.g., @I135@). If omitted, all people are exported.
//   --out PATH     Output file (when --person is set) or output directory (default: sources)
//   --json         Output JSON instead of Markdown (uses .json extension)
//
// Examples:
//   # Single person to markdown file
//   php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --person @I123@ --out Hargroves.md
//   # All individuals to directory (one .md per person)
//   php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --out sources/Hargroves
//   # Output as JSON with schema.json compliance
//   php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --json --out sources/hargroves.json

declare(strict_types=1);

/**
 * Extract database IDs from text (WWW fields, NOTE fields, etc.)
 * Supports: WikiTree, FamilySearch, Ancestry, Find A Grave
 */
function extractDatabaseIds(string $text): array {
    $ids = [];
    
    // WikiTree: [[WikiTree-ID|Name]] or from wiki/ URLs
    if (preg_match('/(?:WikiTree|wiki)\/([A-Z][a-z]+-\d+)/i', $text, $m)) {
        $ids['wikitree'] = $m[1];
    }
    
    // FamilySearch: ark:/61903/1:1:XXXXXX or FS: ID
    if (preg_match('/ark:\/61903\/1:1:([A-Z0-9]{6,})|(?:FamilySearch|FS)[\s:]*([A-Z0-9]{4,})/i', $text, $m)) {
        $ids['familysearch'] = $m[1] ?? $m[2];
    }
    
    // Ancestry: ancestry[.com]/ or Ancestry: number
    if (preg_match('/(?:ancestry\.com\/trees?|Ancestry)[\s:]*\/?(\d{6,})|Ancestry[\s:]*([0-9]+)/i', $text, $m)) {
        $ids['ancestry'] = $m[1] ?? $m[2];
    }
    
    // Find A Grave: findagrave.com/ or FAG: number
    if (preg_match('/(?:find[\s-]?a[\s-]?grave|findagrave|FAG)[\s:]*\/?(\d{7,})|findagrave\.com\/cgi-bin\/fg.cgi\?id=(\d+)/i', $text, $m)) {
        $ids['findagrave'] = $m[1] ?? $m[2];
    }
    
    return $ids;
}

function normalizeGedcomDatePart(string $part): ?string {
    $part = trim($part);
    if ($part === '') return null;

    if (preg_match('/^(\d{4})$/', $part, $m)) {
        return $m[1];
    }

    $monthMap = [
        'JAN' => '01', 'FEB' => '02', 'MAR' => '03', 'APR' => '04',
        'MAY' => '05', 'JUN' => '06', 'JUL' => '07', 'AUG' => '08',
        'SEP' => '09', 'OCT' => '10', 'NOV' => '11', 'DEC' => '12'
    ];

    if (preg_match('/^(\d{1,2})\s+([A-Z]{3})\s+(\d{4})$/i', $part, $m)) {
        $day = str_pad($m[1], 2, '0', STR_PAD_LEFT);
        $mon = strtoupper($m[2]);
        $year = $m[3];
        if (isset($monthMap[$mon])) {
            return $year . '-' . $monthMap[$mon] . '-' . $day;
        }
    }

    if (preg_match('/^([A-Z]{3})\s+(\d{4})$/i', $part, $m)) {
        $mon = strtoupper($m[1]);
        $year = $m[2];
        if (isset($monthMap[$mon])) {
            return $year . '-' . $monthMap[$mon];
        }
    }

    if (preg_match('/(\d{4})/', $part, $m)) {
        return $m[1];
    }

    return null;
}

function parseGedcomDate(?string $raw): ?array {
    $raw = trim((string)$raw);
    if ($raw === '') return null;

    $calendar = 'gregorian';
    if (preg_match('/@#D(JULIAN|GREGORIAN)@/i', $raw, $m)) {
        $calendar = strtoupper($m[1]) === 'JULIAN' ? 'julian' : 'gregorian';
        $raw = trim(preg_replace('/@#D(JULIAN|GREGORIAN)@/i', '', $raw));
    }

    $type = 'exact';
    $modifier = '';
    $start = null;
    $end = null;
    $normalized = null;
    $phrase = null;

    if (preg_match('/^BET\s+(.+)\s+AND\s+(.+)$/i', $raw, $m)) {
        $type = 'between';
        $modifier = 'BET';
        $start = normalizeGedcomDatePart($m[1]);
        $end = normalizeGedcomDatePart($m[2]);
        $normalized = $start ?: $end;
    } elseif (preg_match('/^FROM\s+(.+)\s+TO\s+(.+)$/i', $raw, $m)) {
        $type = 'fromTo';
        $modifier = 'FROM';
        $start = normalizeGedcomDatePart($m[1]);
        $end = normalizeGedcomDatePart($m[2]);
        $normalized = $start ?: $end;
    } elseif (preg_match('/^(ABT|BEF|AFT|CAL|EST)\s+(.+)$/i', $raw, $m)) {
        $modifier = strtoupper($m[1]);
        $datePart = $m[2];
        switch ($modifier) {
            case 'ABT': $type = 'approx'; break;
            case 'BEF': $type = 'before'; break;
            case 'AFT': $type = 'after'; break;
            case 'CAL': $type = 'calculated'; break;
            case 'EST': $type = 'estimated'; break;
        }
        $start = normalizeGedcomDatePart($datePart);
        $normalized = $start;
    } else {
        $start = normalizeGedcomDatePart($raw);
        $normalized = $start;
        if ($start === null) {
            $type = 'phrase';
            $phrase = $raw;
        }
    }

    $out = [
        'original' => $raw,
        'type' => $type,
        'calendar' => $calendar
    ];

    if ($modifier !== '') $out['modifier'] = $modifier;
    if ($start !== null) $out['start'] = $start;
    if ($end !== null) $out['end'] = $end;
    if ($normalized !== null) $out['normalized'] = $normalized;
    if ($phrase !== null) $out['phrase'] = $phrase;

    return $out;
}

function usage(): void {
    $msg = <<<'TXT'
Usage: php scripts/gedcom_to_biography.php --input <file.ged> [--person @I123@] [--out path] [OPTIONS]

Options:
  --input   Path to GEDCOM file (required)
  --person  Specific individual ID (e.g., @I135@). If omitted, all people are exported.
  --out     Output file/directory (default: sources)
  --json    Output JSON schema-compliant data instead of Markdown
  --help    Show this message

Examples:
  Single person -> markdown:
    php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --person @I123@ --out Hargroves.md
  All individuals -> markdown directory:
    php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --out sources/Hargroves
  Export as JSON with schema compliance:
    php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --json --out sources/hargroves.json
TXT;
    fwrite(STDERR, $msg);
    exit(1);
}

function parseArgs(array $argv): array {
    $args = ['input' => null, 'person' => null, 'out' => null, 'json' => false];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--input' && isset($argv[$i + 1])) {
            $args['input'] = $argv[++$i];
        } elseif ($arg === '--person' && isset($argv[$i + 1])) {
            $args['person'] = $argv[++$i];
        } elseif ($arg === '--out' && isset($argv[$i + 1])) {
            $args['out'] = $argv[++$i];
        } elseif ($arg === '--json') {
            $args['json'] = true;
        } elseif ($arg === '--help') {
            usage();
        }
    }
    if ($args['input'] === null) {
        usage();
    }
    return $args;
}

function loadGedcom(string $path): array {
    $data = @file($path, FILE_IGNORE_NEW_LINES);
    if ($data === false) {
        fwrite(STDERR, "Error: Unable to read GEDCOM file: {$path}\n");
        exit(2);
    }
    return $data;
}

function parseGedcom(array $lines): array {
    $individuals = [];
    $families = [];

    $currentType = null; // 'INDI' or 'FAM'
    $currentId = null;
    $currentEvent = null;

    foreach ($lines as $line) {
        // Pattern: level [@X@] TAG [value]
        if (!preg_match('/^(\d+)\s+(?:(@[^@]+@)\s+)?([^\s]+)\s*(.*)$/', $line, $m)) {
            continue;
        }
        $level = (int)$m[1];
        $pointer = $m[2] ?? null;
        $tag = $m[3];
        $value = trim($m[4] ?? '');

        if ($level === 0) {
            $currentEvent = null;
            if ($tag === 'INDI') {
                $currentType = 'INDI';
                $currentId = $pointer;
                $individuals[$currentId] = $individuals[$currentId] ?? [
                    'id' => $currentId,
                    'name' => '',
                    'given' => '',
                    'surname' => '',
                    'sex' => '',
                    'ids' => [],  // Multi-database ID storage
                    'events' => [],
                    'fams' => [],
                    'famc' => null,
                    'notes' => [],
                    'currentNote' => null,
                    'bioSections' => [],  // Parsed biographical sections
                ];
            } elseif ($tag === 'FAM') {
                $currentType = 'FAM';
                $currentId = $pointer;
                $families[$currentId] = $families[$currentId] ?? [
                    'id' => $currentId,
                    'husb' => null,
                    'wife' => null,
                    'children' => [],
                    'events' => [],
                ];
            } else {
                $currentType = null;
                $currentId = null;
            }
            continue;
        }

        if ($currentType === 'INDI' && $currentId !== null) {
            if ($level === 1) {
                $currentEvent = null;
                switch ($tag) {
                    case 'NAME':
                        $individuals[$currentId]['name'] = $value;
                        break;
                    case 'GIVN':
                        $individuals[$currentId]['given'] = $value;
                        break;
                    case 'SURN':
                        $individuals[$currentId]['surname'] = $value;
                        break;
                    case 'SEX':
                        $individuals[$currentId]['sex'] = $value;
                        break;
                    case 'FAMS':
                        $individuals[$currentId]['fams'][] = $value;
                        break;
                    case 'FAMC':
                        $individuals[$currentId]['famc'] = $value;
                        break;
                    case 'WWW':
                        // Extract IDs from various genealogy service URLs
                        $ids = extractDatabaseIds($value);
                        foreach ($ids as $service => $id) {
                            $individuals[$currentId]['ids'][$service] = $id;
                        }
                        break;
                    case 'NOTE':
                        // Also extract IDs from NOTE fields
                        $ids = extractDatabaseIds($value);
                        foreach ($ids as $service => $id) {
                            if (!isset($individuals[$currentId]['ids'][$service])) {
                                $individuals[$currentId]['ids'][$service] = $id;
                            }
                        }
                        $individuals[$currentId]['notes'][] = $value;
                        $individuals[$currentId]['currentNote'] = count($individuals[$currentId]['notes']) - 1;
                        break;
                    case 'BIRT':
                    case 'DEAT':
                    case 'CHR':
                    case 'BURI':
                        $currentEvent = $tag;
                        $individuals[$currentId]['events'][$currentEvent] = $individuals[$currentId]['events'][$currentEvent] ?? ['date' => '', 'place' => ''];
                        break;
                }
            } elseif ($level === 2) {
                if ($currentEvent !== null) {
                    if ($tag === 'DATE') {
                        $individuals[$currentId]['events'][$currentEvent]['date'] = $value;
                    } elseif ($tag === 'PLAC') {
                        $individuals[$currentId]['events'][$currentEvent]['place'] = $value;
                    }
                }
                // Handle CONT/CONC for NOTE continuation
                if (($tag === 'CONT' || $tag === 'CONC') && $individuals[$currentId]['currentNote'] !== null) {
                    $noteIdx = $individuals[$currentId]['currentNote'];
                    $sep = ($tag === 'CONT') ? "\n" : '';
                    $individuals[$currentId]['notes'][$noteIdx] .= $sep . $value;
                }
            }
        } elseif ($currentType === 'FAM' && $currentId !== null) {
            if ($level === 1) {
                $currentEvent = null;
                switch ($tag) {
                    case 'HUSB':
                        $families[$currentId]['husb'] = $value;
                        break;
                    case 'WIFE':
                        $families[$currentId]['wife'] = $value;
                        break;
                    case 'CHIL':
                        $families[$currentId]['children'][] = $value;
                        break;
                    case 'MARR':
                        $currentEvent = 'MARR';
                        $families[$currentId]['events']['MARR'] = $families[$currentId]['events']['MARR'] ?? ['date' => '', 'place' => ''];
                        break;
                }
            } elseif ($level >= 2 && $currentEvent !== null) {
                if ($tag === 'DATE') {
                    $families[$currentId]['events'][$currentEvent]['date'] = $value;
                } elseif ($tag === 'PLAC') {
                    $families[$currentId]['events'][$currentEvent]['place'] = $value;
                }
            }
        }
    }

    return [$individuals, $families];
}

function displayName(array $person): string {
    $given = trim($person['given'] ?? '');
    $surname = trim($person['surname'] ?? '');
    if ($given !== '' && $surname !== '') {
        return $given . ' ' . $surname;
    }
    $name = trim($person['name'] ?? '');
    if ($name !== '') {
        return str_replace('/', '', $name);
    }
    return $person['id'] ?? 'Unknown';
}

function eventText(array $event): string {
    $date = trim($event['date'] ?? '');
    $place = trim($event['place'] ?? '');
    if ($date !== '' && $place !== '') {
        return "on {$date} in {$place}";
    }
    if ($date !== '') {
        return "on {$date}";
    }
    if ($place !== '') {
        return "in {$place}";
    }
    return '';
}

function yearFromDate(string $date): string {
    if (preg_match('/(\d{4})/', $date, $m)) {
        return $m[1];
    }
    return '';
}

function buildOpening(array $person, array $families): string {
    $name = displayName($person);
    $birth = $person['events']['BIRT'] ?? null;
    $birthText = $birth ? eventText($birth) : '';
    $death = $person['events']['DEAT'] ?? null;
    $deathYear = $death ? yearFromDate($death['date'] ?? '') : '';
    $birthYear = $birth ? yearFromDate($birth['date'] ?? '') : '';

    $lifeSpan = '';
    if ($birthYear !== '' || $deathYear !== '') {
        $lifeSpan = ' (' . ($birthYear ?: '?') . ' – ' . ($deathYear ?: '?') . ')';
    }

    $line = "'''{$name}'''{$lifeSpan}";
    if ($birthText !== '') {
        $line .= " was born {$birthText}.";
    } else {
        $line .= ' is recorded in this GEDCOM file.';
    }
    return $line;
}

function buildParentage(array $person, array $families, array $individuals): ?string {
    $famc = $person['famc'] ?? null;
    if (!$famc || !isset($families[$famc])) {
        return null;
    }
    $fam = $families[$famc];
    $parents = [];
    if (!empty($fam['husb']) && isset($individuals[$fam['husb']])) {
        $parents[] = "'''" . displayName($individuals[$fam['husb']]) . "'''";
    }
    if (!empty($fam['wife']) && isset($individuals[$fam['wife']])) {
        $parents[] = "'''" . displayName($individuals[$fam['wife']]) . "'''";
    }
    if (empty($parents)) {
        return null;
    }
    $relation = ($person['sex'] ?? '') === 'F' ? 'daughter' : 'son';
    return ($relation === 'son' ? 'He' : 'She') . ' was the ' . $relation . ' of ' . implode(' and ', $parents) . '.';
}

function buildMarriageSections(array $person, array $families, array $individuals): array {
    $sections = [];
    $lines = [];
    foreach ($person['fams'] as $famId) {
        if (!isset($families[$famId])) continue;
        $fam = $families[$famId];
        $spouseId = null;
        if (($fam['husb'] ?? null) === $person['id']) {
            $spouseId = $fam['wife'] ?? null;
        } elseif (($fam['wife'] ?? null) === $person['id']) {
            $spouseId = $fam['husb'] ?? null;
        }
        $spouseName = $spouseId && isset($individuals[$spouseId]) ? "'''" . displayName($individuals[$spouseId]) . "'''" : 'an unknown spouse';
        $marr = $fam['events']['MARR'] ?? null;
        $marrText = $marr ? eventText($marr) : '';
        $sentence = $spouseName;
        if ($marrText !== '') {
            $sentence = "Married {$spouseName} {$marrText}.";
        } else {
            $sentence = "Married {$spouseName}.";
        }
        $lines[] = $sentence;
    }
    if (!empty($lines)) {
        $sections['=== Marriage ==='] = $lines;
    }
    return $sections;
}

function buildChildrenSection(array $person, array $families, array $individuals): ?array {
    $children = [];
    foreach ($person['fams'] as $famId) {
        if (!isset($families[$famId])) continue;
        foreach ($families[$famId]['children'] as $childId) {
            if (!isset($individuals[$childId])) continue;
            $child = $individuals[$childId];
            $name = "'''" . displayName($child) . "'''";
            $birthYear = '';
            if (isset($child['events']['BIRT'])) {
                $birthYear = yearFromDate($child['events']['BIRT']['date'] ?? '');
            }
            if ($birthYear !== '') {
                $children[] = "* {$name} (b. {$birthYear})";
            } else {
                $children[] = "* {$name}";
            }
        }
    }
    if (empty($children)) {
        return null;
    }
    $title = '=== Children ===';
    array_unshift($children, 'Children (from linked family records):');
    return [$title => $children];
}

function buildDeathSection(array $person): ?array {
    if (!isset($person['events']['DEAT'])) {
        return null;
    }
    $deathText = eventText($person['events']['DEAT']);
    if ($deathText === '') {
        return null;
    }
    return ['=== Death ===' => ["Died {$deathText}."]];
}

/**
 * Parse biographical sections from NOTE fields
 * Detects WikiTree-formatted section headers: == Header == or === Subheader ===
 */
function parseBiographicalSections(array $person): array {
    $sections = [];
    
    // Parse NOTE fields for WikiTree-formatted sections
    foreach ($person['notes'] as $note) {
        $lines = explode("\n", $note);
        $currentSection = null;
        $currentContent = [];
        
        foreach ($lines as $line) {
            // Detect section headers: == Text == or === Text ===
            if (preg_match('/^(=+)\s+(.+?)\s+\1$/', $line, $m)) {
                if ($currentSection !== null) {
                    $sections[$currentSection] = array_filter(array_map('trim', $currentContent));
                }
                $currentSection = $m[2];
                $currentContent = [];
            } elseif ($currentSection !== null) {
                $currentContent[] = trim($line);
            }
        }
        if ($currentSection !== null) {
            $sections[$currentSection] = array_filter(array_map('trim', $currentContent));
        }
    }
    
    return $sections;
}

function buildWikiTreeMapping(array $individuals): array {
    $mapping = [];
    foreach ($individuals as $person) {
        $wikitreeId = $person['ids']['wikitree'] ?? '';
        if ($wikitreeId !== '') {
            $mapping[$person['id']] = [
                'wikitreeId' => $wikitreeId,
                'name' => displayName($person),
                'allIds' => $person['ids']
            ];
        }
    }
    return $mapping;
}

function convertToWikiTreeLink(string $text, array $mapping): string {
    // Convert '''Name''' references to '''[[WikiTreeId|Name]]''' if mapping exists
    return preg_replace_callback(
        "/'''([^']+)'''/",
        function($matches) use ($mapping) {
            $name = $matches[1];
            // Search mapping for this name
            foreach ($mapping as $gedcomId => $info) {
                if ($info['name'] === $name) {
                    return "'''[[" . $info['wikitreeId'] . "|" . $name . "]]'''";
                }
            }
            return $matches[0]; // No mapping found, return original
        },
        $text
    );
}

function buildBiography(array $person, array $families, array $individuals, array $mapping = []): string {
    // Check for parsed biographical sections from NOTE fields
    $bioSections = parseBiographicalSections($person);
    
    // If rich content with Biography or Sources sections exists, use it with WikiTree link conversion
    if (!empty($bioSections) && (isset($bioSections['Biography']) || isset($bioSections['Sources']))) {
        $out = [];
        foreach ($bioSections as $sectionName => $content) {
            if (!empty($content)) {
                $out[] = "== $sectionName ==";
                $out[] = '';
                
                if (is_array($content)) {
                    foreach ($content as $line) {
                        if (!empty($mapping)) {
                            $line = convertToWikiTreeLink($line, $mapping);
                        }
                        $out[] = $line;
                    }
                } else {
                    if (!empty($mapping)) {
                        $content = convertToWikiTreeLink($content, $mapping);
                    }
                    $out[] = $content;
                }
                $out[] = '';
            }
        }
        return implode("\n", array_filter($out)) . "\n";
    }
    
    // Otherwise generate basic template
    $out = [];
    $out[] = '== Biography ==';
    $out[] = '';
    $out[] = buildOpening($person, $families);

    $parentLine = buildParentage($person, $families, $individuals);
    if ($parentLine) {
        if (!empty($mapping)) {
            $parentLine = convertToWikiTreeLink($parentLine, $mapping);
        }
        $out[] = $parentLine;
    }

    $sections = [];
    $sections = array_merge($sections, buildMarriageSections($person, $families, $individuals));
    $childrenSection = buildChildrenSection($person, $families, $individuals);
    if ($childrenSection) {
        $sections = array_merge($sections, $childrenSection);
    }
    $deathSection = buildDeathSection($person);
    if ($deathSection) {
        $sections = array_merge($sections, $deathSection);
    }

    if (!empty($sections)) {
        foreach ($sections as $header => $lines) {
            $out[] = '';
            $out[] = $header;
            foreach ($lines as $line) {
                if (!empty($mapping)) {
                    $line = convertToWikiTreeLink($line, $mapping);
                }
                $out[] = $line;
            }
        }
    }

    $out[] = '';
    $out[] = '== Sources ==';
    $out[] = '* Information synthesized from GEDCOM file import.';

    return implode("\n", $out) . "\n";
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            fwrite(STDERR, "Error: Unable to create output directory: {$dir}\n");
            exit(3);
        }
    }
}

function outputPathForPerson(string $baseDir, array $person, bool $json = false): string {
    $ext = $json ? '.json' : '.md';
    
    // Prefer WikiTree ID for filename
    $wikitreeId = $person['ids']['wikitree'] ?? '';
    if ($wikitreeId !== '') {
        return rtrim($baseDir, '/') . "/{$wikitreeId}{$ext}";
    }
    
    // Fall back to sanitized name
    $name = displayName($person);
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
    $safe = trim($safe, '-');
    if ($safe === '') {
        $safe = trim($person['id'], '@');
    }
    return rtrim($baseDir, '/') . "/{$safe}{$ext}";
}

/**
 * Convert person record to schema.json-compliant structure
 */
function convertPersonToJsonSchema(array $person, array $individuals): array {
    $birthEvent = $person['events']['BIRT'] ?? null;
    $deathEvent = $person['events']['DEAT'] ?? null;
    
    $jsonPerson = [
        'wikitreeId' => $person['ids']['wikitree'] ?? null,
        'gedcomId' => $person['id'],
        'name' => [
            'full' => displayName($person),
            'given' => $person['given'] ?? '',
            'surname' => $person['surname'] ?? ''
        ],
        'sex' => $person['sex'] ?? 'U'
    ];
    
    // Add cross-database IDs
    if (!empty($person['ids'])) {
        $jsonPerson['externalIds'] = $person['ids'];
    }
    
    // Add birth event
    if ($birthEvent) {
        $jsonPerson['birth'] = array_filter([
            'date' => parseGedcomDate($birthEvent['date'] ?? null) ?? ($birthEvent['date'] ?? null),
            'place' => $birthEvent['place'] ?? null
        ]);
    }

    // Add death event
    if ($deathEvent) {
        $jsonPerson['death'] = array_filter([
            'date' => parseGedcomDate($deathEvent['date'] ?? null) ?? ($deathEvent['date'] ?? null),
            'place' => $deathEvent['place'] ?? null
        ]);
    }
    
    // Add family relationships
    if (!empty($person['fams'])) {
        $jsonPerson['familyAsSpouse'] = $person['fams'];
    }
    if ($person['famc']) {
        $jsonPerson['familyAsChild'] = $person['famc'];
    }
    
    // Add parsed biographical notes
    $bioSections = parseBiographicalSections($person);
    if (!empty($bioSections)) {
        $jsonPerson['biographicalSections'] = $bioSections;
    }
    
    // Add all notes
    if (!empty($person['notes'])) {
        $jsonPerson['notes'] = implode("\n\n", $person['notes']);
    }
    
    return array_filter($jsonPerson);
}

/**
 * Build complete JSON output conforming to schema.json structure
 */
function buildJsonOutput(array $individuals, array $families): array {
    $output = [
        'metadata' => [
            'generated' => date('c'),
            'source' => 'GEDCOM to Biography Converter (Enhanced)',
            'gedcomVersion' => '5.5.1'
        ],
        'people' => [],
        'families' => []
    ];
    
    // Convert all people
    foreach ($individuals as $person) {
        $output['people'][] = convertPersonToJsonSchema($person, $individuals);
    }
    
    // Convert family records
    foreach ($families as $family) {
        $famRecord = ['familyId' => $family['id']];
        if ($family['husb']) $famRecord['husband'] = $family['husb'];
        if ($family['wife']) $famRecord['wife'] = $family['wife'];
        if (!empty($family['children'])) $famRecord['children'] = $family['children'];
        if (!empty($family['events']['MARR'])) {
            $marriage = $family['events']['MARR'];
            $famRecord['marriage'] = array_filter([
                'date' => parseGedcomDate($marriage['date'] ?? null) ?? ($marriage['date'] ?? null),
                'place' => $marriage['place'] ?? null
            ]);
        }
        $output['families'][] = array_filter($famRecord);
    }
    
    return $output;
}

function exportReferenceDatabase(string $outDir, array $individuals, array $families): void {
    $data = [
        'metadata' => [
            'generated' => date('c'),
            'source' => 'GEDCOM to Biography Converter'
        ],
        'people' => [],
        'relationships' => []
    ];
    
    foreach ($individuals as $person) {
        $wikitreeId = $person['ids']['wikitree'] ?? '';
        if ($wikitreeId === '') continue; // Only export people with WikiTree IDs
        
        $birthDate = $person['events']['BIRT']['date'] ?? '';
        $birthPlace = $person['events']['BIRT']['place'] ?? '';
        $deathDate = $person['events']['DEAT']['date'] ?? '';
        $deathPlace = $person['events']['DEAT']['place'] ?? '';
        
        $data['people'][] = [
            'wikitreeId' => $wikitreeId,
            'gedcomId' => $person['id'],
            'name' => displayName($person),
            'givenName' => $person['given'],
            'surname' => $person['surname'],
            'sex' => $person['sex'],
            'allIds' => $person['ids'],
            'birth' => array_filter(['date' => $birthDate, 'place' => $birthPlace]),
            'death' => array_filter(['date' => $deathDate, 'place' => $deathPlace])
        ];
    }
    
    $jsonPath = rtrim($outDir, '/') . '/reference-database.json';
    file_put_contents($jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    
    // Also export CSV for spreadsheet use
    $csvPath = rtrim($outDir, '/') . '/reference-database.csv';
    $fp = fopen($csvPath, 'w');
    fputcsv($fp, ['WikiTree ID', 'Name', 'Given Name', 'Surname', 'Sex', 'FamilySearch ID', 'Ancestry ID', 'Find A Grave ID', 'Birth Date', 'Birth Place', 'Death Date', 'Death Place'], ',', '"', '\\');
    foreach ($data['people'] as $p) {
        fputcsv($fp, [
            $p['wikitreeId'],
            $p['name'],
            $p['givenName'],
            $p['surname'],
            $p['sex'],
            $p['allIds']['familysearch'] ?? '',
            $p['allIds']['ancestry'] ?? '',
            $p['allIds']['findagrave'] ?? '',
            $p['birth']['date'] ?? '',
            $p['birth']['place'] ?? '',
            $p['death']['date'] ?? '',
            $p['death']['place'] ?? ''
        ], ',', '"', '\\');
    }
    fclose($fp);
    
    fwrite(STDOUT, "Exported reference database: {$jsonPath} and {$csvPath}\n");
}

// Main
$args = parseArgs($argv);
$inputPath = $args['input'];
$personId = $args['person'];
$outArg = $args['out'];
$outputJson = $args['json'] ?? false;

if (!file_exists($inputPath)) {
    fwrite(STDERR, "Error: Input file not found: {$inputPath}\n");
    exit(4);
}

$lines = loadGedcom($inputPath);
[$individuals, $families] = parseGedcom($lines);

// Build WikiTree mapping for cross-references
$mapping = buildWikiTreeMapping($individuals);

if ($personId !== null && !isset($individuals[$personId])) {
    fwrite(STDERR, "Error: Person ID not found in GEDCOM: {$personId}\n");
    exit(5);
}

if ($outputJson) {
    // Output as JSON
    $jsonData = buildJsonOutput($individuals, $families);
    $outDir = $outArg ? dirname($outArg) : 'sources';
    ensureDir($outDir);
    
    if ($personId !== null) {
        // Single person as JSON
        $target = $individuals[$personId];
        $personJson = convertPersonToJsonSchema($target, $individuals);
        $outFile = $outArg ?? outputPathForPerson($outDir, $target, true);
        $json = json_encode(['person' => $personJson], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
        // All people as JSON
        $outFile = $outArg ?? rtrim($outDir, '/') . '/genealogy.json';
        $json = json_encode($jsonData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    if (@file_put_contents($outFile, $json) === false) {
        fwrite(STDERR, "Error: Failed to write JSON to {$outFile}\n");
        exit(6);
    }
    fwrite(STDOUT, "Wrote JSON to {$outFile}\n");
    exit(0);
}

if ($personId !== null) {
    // Single person as Markdown
    $target = $individuals[$personId];
    $md = buildBiography($target, $families, $individuals, $mapping);
    if ($outArg !== null && preg_match('/\.md$/i', $outArg)) {
        $outFile = $outArg;
        $outDir = dirname($outFile);
        ensureDir($outDir);
    } else {
        $outDir = $outArg ?? 'sources';
        ensureDir($outDir);
        $outFile = outputPathForPerson($outDir, $target, false);
    }
    if (@file_put_contents($outFile, $md) === false) {
        fwrite(STDERR, "Error: Failed to write output to {$outFile}\n");
        exit(6);
    }
    fwrite(STDOUT, "Wrote {$outFile}\n");
    exit(0);
}

// Batch: all individuals as Markdown
$outDir = $outArg ?? 'sources';
ensureDir($outDir);
$count = 0;
foreach ($individuals as $person) {
    $md = buildBiography($person, $families, $individuals, $mapping);
    $outFile = outputPathForPerson($outDir, $person, false);
    if (@file_put_contents($outFile, $md) === false) {
        fwrite(STDERR, "Error: Failed to write output to {$outFile}\n");
        exit(6);
    }
    $count++;
}
fwrite(STDOUT, "Wrote {$count} biographies to {$outDir}\n");

// Export reference database
exportReferenceDatabase($outDir, $individuals, $families);

?>
