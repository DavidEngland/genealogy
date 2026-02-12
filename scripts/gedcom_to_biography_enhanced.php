<?php
// GEDCOM to WikiTree-style Biography Markdown & JSON Generator
// Enhanced with Categories, Stickers/Templates, and multi-database ID extraction
//
// Usage:
//   php gedcom_to_biography_enhanced.php --input GEDs/example.ged [--person @I123@] [--out path] [--json]
// Options:
//   --input FILE   Path to GEDCOM file (required)
//   --person ID    Specific individual ID (e.g., @I135@). If omitted, all people are exported.
//   --out PATH     Output file (when --person is set) or output directory (default: sources)
//   --json         Output JSON instead of Markdown (uses .json extension)
//   --categories FILE  Path to categories configuration JSON file (optional)
//
// Category Configuration:
//   Create a JSON file to define automatic category assignments based on:
//   - Geographic locations (birth/death places)
//   - Military service
//   - Notable events
//   - Family connections
//   - Occupations
//   - Time periods

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

/**
 * Extract military service information from person data
 */
function extractMilitaryService(array $person): ?array {
    $service = null;
    $notes = implode(' ', $person['notes'] ?? []);

    // Detect Civil War service
    if (preg_match('/(Confederate|Union|CSA|USA|C\.S\.A\.|U\.S\.A\.)/i', $notes, $sideMatch)) {
        $service = [
            'war' => 'United States Civil War',
            'side' => null,
            'rank' => null,
            'unit' => null,
            'enlisted' => null,
            'mustered' => null,
            'regiment' => null,
            'company' => null,
            'isPOW' => false,
            'captured' => null,
            'prisonCamp' => null
        ];

        // Determine side
        if (preg_match('/Confederate|CSA|C\.S\.A\./i', $notes)) {
            $service['side'] = 'CSA';
        } elseif (preg_match('/Union|USA|U\.S\.A\./i', $notes)) {
            $service['side'] = 'USA';
        }

        // Extract rank
        if (preg_match('/(Private|Sergeant|Lieutenant|Captain|Major|Colonel|General)/i', $notes, $m)) {
            $service['rank'] = $m[1];
        }

        // Extract company and regiment
        if (preg_match('/Co(?:mpany)?\s+([A-Z])[,\s]+([^,\n]+Regiment[^,\n]*)/i', $notes, $m)) {
            $service['company'] = $m[1];
            $service['regiment'] = trim($m[2]);
            $service['unit'] = trim($m[2]);
        } elseif (preg_match('/(\d+(?:st|nd|rd|th)\s+(?:Regiment|Infantry|Cavalry)[^,\n]*)/i', $notes, $m)) {
            $service['regiment'] = trim($m[1]);
            $service['unit'] = trim($m[1]);
        }

        // Extract dates
        if (preg_match('/enlisted[:\s]+(\d{4})/i', $notes, $m)) {
            $service['enlisted'] = $m[1];
        }
        if (preg_match('/mustered[:\s]+(\d{4})/i', $notes, $m)) {
            $service['mustered'] = $m[1];
        }

        // Check for POW status
        if (preg_match('/(?:POW|Prisoner of War|captured)/i', $notes)) {
            $service['isPOW'] = true;
            if (preg_match('/captured[^,\n]*?(\d{1,2}\s+\w+\s+\d{4}|\d{4})/i', $notes, $m)) {
                $service['captured'] = $m[1];
            }
            if (preg_match('/(?:Camp\s+(?:Douglas|Chase)|prison[^,\n]{0,50})/i', $notes, $m)) {
                $service['prisonCamp'] = trim($m[0]);
            }
        }
    }

    return $service;
}

/**
 * Detect notable status from person data
 */
function isNotable(array $person): bool {
    $notes = implode(' ', $person['notes'] ?? []);

    // Check for notable indicators
    $notablePatterns = [
        '/notable/i',
        '/famous/i',
        '/prominent/i',
        '/patriarch/i',
        '/matriarch/i',
        '/feud/i',
        '/historical/i',
        '/well[- ]known/i'
    ];

    foreach ($notablePatterns as $pattern) {
        if (preg_match($pattern, $notes)) {
            return true;
        }
    }

    return false;
}

/**
 * Extract geographic locations from person events
 */
function extractLocations(array $person): array {
    $locations = [];

    foreach ($person['events'] as $eventType => $event) {
        $place = $event['place'] ?? '';
        if ($place !== '') {
            $locations[] = $place;
        }
    }

    return array_unique($locations);
}

/**
 * Generate WikiTree categories based on person data
 */
function generateCategories(array $person, array $families, array $individuals, array $config = []): array {
    $categories = [];
    $locations = extractLocations($person);
    $military = extractMilitaryService($person);
    $notes = implode(' ', $person['notes'] ?? []);

    // Geographic categories
    foreach ($locations as $place) {
        // Appalachia detection
        if (preg_match('/(?:Pike|Floyd|Perry|Letcher|Harlan|Bell)\s+County,\s*Kentucky/i', $place)) {
            $categories[] = 'Kentucky Appalachians';
            $categories[] = 'Appalachia';
        }

        // State categories
        if (preg_match('/,\s*([A-Z][a-z]+(?:\s+[A-Z][a-z]+)?)\s*$/i', $place, $m)) {
            $state = trim($m[1]);
            if (!in_array($state, ['USA', 'United States'])) {
                // Removed to avoid too generic categories, but can be enabled
                // $categories[] = $state;
            }
        }

        // City/town categories
        if (preg_match('/^([^,]+),/i', $place, $m)) {
            $city = trim($m[1]);
            // Add specific notable cities
            if (preg_match('/Pikeville/i', $city)) {
                $categories[] = 'Pikeville, Kentucky';
            }
        }

        // Cemetery categories
        if (preg_match('/([^,]+Cemetery)[^,]*/i', $place, $m)) {
            $cemetery = trim($m[1]);
            $categories[] = $cemetery . ', ' . $place;
        }
    }

    // Military service categories
    if ($military) {
        if ($military['war'] === 'United States Civil War') {
            $categories[] = 'United States Civil War';

            if ($military['side'] === 'CSA') {
                $categories[] = 'Confederate States of America, United States Civil War';
            } elseif ($military['side'] === 'USA') {
                $categories[] = 'Union Army, United States Civil War';
            }

            // POW categories
            if ($military['isPOW']) {
                $categories[] = 'Prisoners of War, ' . ($military['side'] === 'CSA' ? 'Confederate States of America' : 'Union Army') . ', United States Civil War';

                if ($military['prisonCamp']) {
                    if (preg_match('/Camp Douglas/i', $military['prisonCamp'])) {
                        $categories[] = 'Camp Douglas, Chicago, Illinois';
                    } elseif (preg_match('/Camp Chase/i', $military['prisonCamp'])) {
                        $categories[] = 'Camp Chase, Columbus, Ohio';
                    }
                }
            }
        }
    }

    // Family feud detection
    if (preg_match('/Hatfield.*McCoy|McCoy.*Hatfield/i', $notes)) {
        $categories[] = 'Hatfield and McCoy Family Feud';
        $categories[] = 'Famous Feuds';
    }

    // Notable person
    if (isNotable($person)) {
        $categories[] = 'Appalachia, Notables';
    }

    // Custom configuration categories
    if (!empty($config['categories'])) {
        foreach ($config['categories'] as $rule) {
            if (isset($rule['pattern']) && isset($rule['category'])) {
                if (preg_match($rule['pattern'], $notes)) {
                    $categories[] = $rule['category'];
                }
            }
        }
    }

    return array_unique($categories);
}

/**
 * Generate WikiTree stickers/templates based on person data
 */
function generateStickers(array $person, array $config = []): array {
    $stickers = [];
    $military = extractMilitaryService($person);

    // Appalachia sticker for notables
    if (isNotable($person)) {
        $locations = extractLocations($person);
        $isAppalachia = false;
        foreach ($locations as $place) {
            if (preg_match('/(?:Pike|Floyd|Perry|Letcher|Harlan|Bell)\s+County,\s*Kentucky/i', $place)) {
                $isAppalachia = true;
                break;
            }
        }
        if ($isAppalachia) {
            $stickers[] = [
                'template' => 'Appalachia',
                'params' => []
            ];
            $stickers[] = [
                'template' => 'Notables Sticker',
                'params' => ['Appalachia, Notables']
            ];
        }
    }

    // US Civil War template
    if ($military && $military['war'] === 'United States Civil War') {
        $params = array_filter([
            'side' => $military['side'],
            'enlisted' => $military['enlisted'],
            'mustered' => $military['mustered'],
            'regiment name' => $military['regiment'],
            'rank' => $military['rank'],
            'unit' => $military['unit']
        ]);

        $stickers[] = [
            'template' => 'US Civil War',
            'params' => $params
        ];

        // Roll of Honor for POW
        if ($military['isPOW']) {
            $stickers[] = [
                'template' => 'Roll of Honor',
                'params' => [
                    'category' => 'Prisoners of War, ' . ($military['side'] === 'CSA' ? 'Confederate States of America' : 'Union Army') . ', United States Civil War',
                    'image' => 'POW Camps-7.jpg',
                    'description' => 'a Prisoner of War',
                    'war' => 'United States Civil War'
                ]
            ];
        }
    }

    return $stickers;
}

/**
 * Format WikiTree category line
 */
function formatCategory(string $category): string {
    return "[[Category: {$category}]]";
}

/**
 * Format WikiTree template/sticker
 */
function formatSticker(array $sticker): string {
    $name = $sticker['template'];
    $params = $sticker['params'] ?? [];

    if (empty($params)) {
        return "{{" . $name . "}}";
    }

    $lines = ["{{" . $name];
    foreach ($params as $key => $value) {
        if (is_numeric($key)) {
            $lines[] = "|{$value}";
        } else {
            $lines[] = "|{$key} = {$value}";
        }
    }
    $lines[] = "}}";

    return implode("\n", $lines);
}

function usage(): void {
    $msg = <<<'TXT'
Usage: php gedcom_to_biography_enhanced.php --input <file.ged> [--person @I123@] [--out path] [OPTIONS]

Options:
  --input       Path to GEDCOM file (required)
  --person      Specific individual ID (e.g., @I135@). If omitted, all people are exported.
  --out         Output file/directory (default: sources)
  --json        Output JSON schema-compliant data instead of Markdown
  --categories  Path to categories configuration JSON file (optional)
  --help        Show this message

Examples:
  Single person with categories:
    php gedcom_to_biography_enhanced.php --input GEDs/Hargroves.ged --person @I123@ --out Hargroves.md
  All individuals with custom category rules:
    php gedcom_to_biography_enhanced.php --input GEDs/Hargroves.ged --out sources/Hargroves --categories rules.json
TXT;
    fwrite(STDERR, $msg . "\n");
    exit(1);
}

function parseArgs(array $argv): array {
    $args = ['input' => null, 'person' => null, 'out' => null, 'json' => false, 'categories' => null];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--input' && isset($argv[$i + 1])) {
            $args['input'] = $argv[++$i];
        } elseif ($arg === '--person' && isset($argv[$i + 1])) {
            $args['person'] = $argv[++$i];
        } elseif ($arg === '--out' && isset($argv[$i + 1])) {
            $args['out'] = $argv[++$i];
        } elseif ($arg === '--categories' && isset($argv[$i + 1])) {
            $args['categories'] = $argv[++$i];
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

function loadCategoryConfig(string $path): array {
    if (!file_exists($path)) {
        fwrite(STDERR, "Warning: Category config file not found: {$path}\n");
        return [];
    }
    $json = @file_get_contents($path);
    if ($json === false) {
        fwrite(STDERR, "Warning: Unable to read category config: {$path}\n");
        return [];
    }
    $config = json_decode($json, true);
    if (!is_array($config)) {
        fwrite(STDERR, "Warning: Invalid category config format: {$path}\n");
        return [];
    }
    return $config;
}

function parseGedcom(array $lines): array {
    $individuals = [];
    $families = [];

    $currentType = null;
    $currentId = null;
    $currentEvent = null;

    foreach ($lines as $line) {
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
                    'ids' => [],
                    'events' => [],
                    'fams' => [],
                    'famc' => null,
                    'notes' => [],
                    'currentNote' => null,
                    'bioSections' => [],
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
                        $ids = extractDatabaseIds($value);
                        foreach ($ids as $service => $id) {
                            $individuals[$currentId]['ids'][$service] = $id;
                        }
                        break;
                    case 'NOTE':
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

function parseBiographicalSections(array $person): array {
    $sections = [];

    foreach ($person['notes'] as $note) {
        $lines = explode("\n", $note);
        $currentSection = null;
        $currentContent = [];

        foreach ($lines as $line) {
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
    return preg_replace_callback(
        "/'''([^']+)'''/",
        function($matches) use ($mapping) {
            $name = $matches[1];
            foreach ($mapping as $gedcomId => $info) {
                if ($info['name'] === $name) {
                    return "'''[[" . $info['wikitreeId'] . "|" . $name . "]]'''";
                }
            }
            return $matches[0];
        },
        $text
    );
}

function buildBiography(array $person, array $families, array $individuals, array $mapping = [], array $config = []): string {
    $bioSections = parseBiographicalSections($person);

    // Generate categories and stickers
    $categories = generateCategories($person, $families, $individuals, $config);
    $stickers = generateStickers($person, $config);

    $out = [];

    // Add categories at the top
    foreach ($categories as $cat) {
        $out[] = formatCategory($cat);
    }

    if (!empty($categories)) {
        $out[] = '';
    }

    // Add stickers/templates before biography
    foreach ($stickers as $sticker) {
        $out[] = formatSticker($sticker);
    }

    if (!empty($stickers)) {
        $out[] = '';
    }

    // Add biography section
    if (!empty($bioSections) && (isset($bioSections['Biography']) || isset($bioSections['Sources']))) {
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
    } else {
        // Generate basic template
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
    }

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

    $wikitreeId = $person['ids']['wikitree'] ?? '';
    if ($wikitreeId !== '') {
        return rtrim($baseDir, '/') . "/{$wikitreeId}{$ext}";
    }

    $name = displayName($person);
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
    $safe = trim($safe, '-');
    if ($safe === '') {
        $safe = trim($person['id'], '@');
    }
    return rtrim($baseDir, '/') . "/{$safe}{$ext}";
}

function convertPersonToJsonSchema(array $person, array $individuals, array $families, array $config = []): array {
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

    if (!empty($person['ids'])) {
        $jsonPerson['externalIds'] = $person['ids'];
    }

    if ($birthEvent) {
        $jsonPerson['birth'] = array_filter([
            'date' => $birthEvent['date'] ?? null,
            'place' => $birthEvent['place'] ?? null
        ]);
    }

    if ($deathEvent) {
        $jsonPerson['death'] = array_filter([
            'date' => $deathEvent['date'] ?? null,
            'place' => $deathEvent['place'] ?? null
        ]);
    }

    if (!empty($person['fams'])) {
        $jsonPerson['familyAsSpouse'] = $person['fams'];
    }
    if ($person['famc']) {
        $jsonPerson['familyAsChild'] = $person['famc'];
    }

    $bioSections = parseBiographicalSections($person);
    if (!empty($bioSections)) {
        $jsonPerson['biographicalSections'] = $bioSections;
    }

    if (!empty($person['notes'])) {
        $jsonPerson['notes'] = implode("\n\n", $person['notes']);
    }

    // Add categories and stickers
    $categories = generateCategories($person, $families, [], $config);
    $stickers = generateStickers($person, $config);

    if (!empty($categories)) {
        $jsonPerson['categories'] = $categories;
    }
    if (!empty($stickers)) {
        $jsonPerson['stickers'] = $stickers;
    }

    return array_filter($jsonPerson);
}

function buildJsonOutput(array $individuals, array $families, array $config = []): array {
    $output = [
        'metadata' => [
            'generated' => date('c'),
            'source' => 'GEDCOM to Biography Converter (Enhanced with Categories)',
            'gedcomVersion' => '5.5.1'
        ],
        'people' => [],
        'families' => []
    ];

    foreach ($individuals as $person) {
        $output['people'][] = convertPersonToJsonSchema($person, $individuals, $families, $config);
    }

    foreach ($families as $family) {
        $famRecord = ['familyId' => $family['id']];
        if ($family['husb']) $famRecord['husband'] = $family['husb'];
        if ($family['wife']) $famRecord['wife'] = $family['wife'];
        if (!empty($family['children'])) $famRecord['children'] = $family['children'];
        if (!empty($family['events']['MARR'])) {
            $famRecord['marriage'] = array_filter($family['events']['MARR']);
        }
        $output['families'][] = array_filter($famRecord);
    }

    return $output;
}

function exportReferenceDatabase(string $outDir, array $individuals, array $families): void {
    $data = [
        'metadata' => [
            'generated' => date('c'),
            'source' => 'GEDCOM to Biography Converter (Enhanced)'
        ],
        'people' => [],
        'relationships' => []
    ];

    foreach ($individuals as $person) {
        $wikitreeId = $person['ids']['wikitree'] ?? '';
        if ($wikitreeId === '') continue;

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
$categoryConfigPath = $args['categories'] ?? null;

if (!file_exists($inputPath)) {
    fwrite(STDERR, "Error: Input file not found: {$inputPath}\n");
    exit(4);
}

$config = [];
if ($categoryConfigPath !== null) {
    $config = loadCategoryConfig($categoryConfigPath);
}

$lines = loadGedcom($inputPath);
[$individuals, $families] = parseGedcom($lines);

$mapping = buildWikiTreeMapping($individuals);

if ($personId !== null && !isset($individuals[$personId])) {
    fwrite(STDERR, "Error: Person ID not found in GEDCOM: {$personId}\n");
    exit(5);
}

if ($outputJson) {
    $jsonData = buildJsonOutput($individuals, $families, $config);
    $outDir = $outArg ? dirname($outArg) : 'sources';
    ensureDir($outDir);

    if ($personId !== null) {
        $target = $individuals[$personId];
        $personJson = convertPersonToJsonSchema($target, $individuals, $families, $config);
        $outFile = $outArg ?? outputPathForPerson($outDir, $target, true);
        $json = json_encode(['person' => $personJson], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    } else {
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
    $target = $individuals[$personId];
    $md = buildBiography($target, $families, $individuals, $mapping, $config);
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

$outDir = $outArg ?? 'sources';
ensureDir($outDir);
$count = 0;
foreach ($individuals as $person) {
    $md = buildBiography($person, $families, $individuals, $mapping, $config);
    $outFile = outputPathForPerson($outDir, $person, false);
    if (@file_put_contents($outFile, $md) === false) {
        fwrite(STDERR, "Error: Failed to write output to {$outFile}\n");
        exit(6);
    }
    $count++;
}
fwrite(STDOUT, "Wrote {$count} biographies to {$outDir}\n");

exportReferenceDatabase($outDir, $individuals, $families);

?>