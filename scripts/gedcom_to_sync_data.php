<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * GEDCOM to Sync Data Parser
 * 
 * Parses WikiTree GEDCOM files into structured JSON for sync tools
 * Extracts FindAGrave memorial IDs, URLs, and family relationships
 * Properly attributes children to correct mother (important for multiple spouses)
 * 
 * Usage:
 *   php gedcom_to_sync_data.php --input GEDs/Welch-185-depth2.ged
 *   php gedcom_to_sync_data.php --input GEDs/Welch-185-depth2.ged --output data/welch-185-wt.json
 * 
 * @author David England
 * @date 2026-03-04
 */

// Parse command line arguments
$options = getopt('', ['input:', 'output:', 'verbose', 'help']);

if (isset($options['help']) || empty($options['input'])) {
    showHelp();
    exit(0);
}

$inputFile = $options['input'];
$outputFile = $options['output'] ?? str_replace('.ged', '-parsed.json', $inputFile);
$outputFile = str_replace('GEDs/', 'data/', $outputFile);
$verbose = isset($options['verbose']);

if (!file_exists($inputFile)) {
    fwrite(STDERR, "Error: GEDCOM file not found: $inputFile\n");
    exit(1);
}

echo "=== GEDCOM to Sync Data Parser ===\n\n";
echo "Input: $inputFile\n";
echo "Output: $outputFile\n\n";

// Parse GEDCOM
echo "Parsing GEDCOM...\n";
$data = parseGedcom($inputFile, $verbose);

echo "  Individuals: " . count($data['individuals']) . "\n";
echo "  Families: " . count($data['families']) . "\n\n";

// Build family structures
echo "Building family relationships...\n";
$families = buildFamilyStructures($data, $verbose);

echo "  Built relationships for " . count($families) . " families\n\n";

// Create sync data structure
$syncData = [
    'metadata' => [
        'source' => 'WikiTree GEDCOM',
        'parsed' => date('c'),
        'gedcom_file' => basename($inputFile)
    ],
    'profiles' => [],
    'families' => $families
];

// Convert individuals to sync format
foreach ($data['individuals'] as $id => $person) {
    $syncData['profiles'][$id] = [
        'gedcom_id' => $id,
        'wikitree_id' => $person['wikitree_id'] ?? null,
        'familysearch_id' => $person['familysearch_id'] ?? null,
        'findagrave_id' => $person['findagrave_id'] ?? null,
        'ancestry_id' => $person['ancestry_id'] ?? null,
        'name' => $person['name'] ?? 'Unknown',
        'given_name' => $person['given_name'] ?? null,
        'surname' => $person['surname'] ?? null,
        'sex' => $person['sex'] ?? null,
        'birth_year' => extractYear($person['birth_date'] ?? null),
        'death_year' => extractYear($person['death_date'] ?? null),
        'birth_location' => $person['birth_location'] ?? null,
        'death_location' => $person['death_location'] ?? null,
        'url' => $person['url'] ?? null,
        'families_as_spouse' => $person['families_as_spouse'] ?? [],
        'family_as_child' => $person['family_as_child'] ?? null
    ];
}

// Save JSON
file_put_contents($outputFile, json_encode($syncData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "=== Parse Complete ===\n";
echo "Saved to: $outputFile\n\n";

// Display sample profiles with all IDs
displaySampleProfiles($syncData['profiles'], 5);

/* ========== FUNCTIONS ========== */

/**
 * Parse GEDCOM file
 */
function parseGedcom(string $filePath, bool $verbose): array {
    $content = file_get_contents($filePath);
    $lines = explode("\n", $content);
    
    $data = [
        'individuals' => [],
        'families' => []
    ];
    
    $currentRecord = null;
    $currentLevel = 0;
    $currentTag = null;
    $lastFieldTag = null;  // Track last level-1 tag for CONC/CONT
    $lastFieldValue = null;  // Track accumulated value
    
    foreach ($lines as $lineNum => $line) {
        $line = rtrim($line);
        if (empty($line)) continue;
        
        // Parse GEDCOM level, tag, and value
        if (!preg_match('/^(\d+)\s+(@[A-Z0-9]+@\s+)?([A-Z0-9_]+)\s*(.*)$/i', $line, $matches)) {
            continue;
        }
        
        $level = (int)$matches[1];
        $xref = trim($matches[2] ?? '');
        $tag = $matches[3];
        $value = trim($matches[4]);
        
        // Start of new record
        if ($level === 0) {
            // Process any pending field before starting new record
            if ($lastFieldTag && $lastFieldValue !== null && $currentRecord !== null) {
                $ids = extractDatabaseIds($lastFieldValue);
                foreach ($ids as $db => $id) {
                    if (!isset($currentRecord["{$db}_id"])) {
                        $currentRecord["{$db}_id"] = $id;
                    }
                }
            }
            $lastFieldTag = null;
            $lastFieldValue = null;
            
            if ($tag === 'INDI' && $xref) {
                $currentRecord = &$data['individuals'][$xref];
                $currentRecord = [
                    'gedcom_id' => $xref,
                    'name' => null,
                    'families_as_spouse' => [],
                    'family_as_child' => null
                ];
                $currentTag = 'INDI';
            } elseif ($tag === 'FAM' && $xref) {
                $currentRecord = &$data['families'][$xref];
                $currentRecord = [
                    'gedcom_id' => $xref,
                    'husband' => null,
                    'wife' => null,
                    'children' => []
                ];
                $currentTag = 'FAM';
            } else {
                $currentRecord = null;
                $currentTag = null;
            }
        }
        // Level 1 tags (direct children of record)
        elseif ($level === 1 && $currentRecord !== null) {
            // Process any pending field from previous level-1 tag
            if ($lastFieldTag && $lastFieldValue !== null) {
                $ids = extractDatabaseIds($lastFieldValue);
                foreach ($ids as $db => $id) {
                    if (!isset($currentRecord["{$db}_id"])) {
                        $currentRecord["{$db}_id"] = $id;
                    }
                }
            }
            $lastFieldTag = null;
            $lastFieldValue = null;
            
            if ($currentTag === 'INDI') {
                switch ($tag) {
                    case 'NAME':
                        $currentRecord['name'] = str_replace('/', '', $value);
                        // Extract given name and surname
                        if (preg_match('/^(.+?)\s+\/([^\/]+)\/$/', $value, $m)) {
                            $currentRecord['given_name'] = trim($m[1]);
                            $currentRecord['surname'] = trim($m[2]);
                        }
                        break;
                    case 'SEX':
                        $currentRecord['sex'] = $value;
                        break;
                    case 'BIRT':
                        $currentRecord['_in_birth'] = true;
                        break;
                    case 'DEAT':
                        $currentRecord['_in_death'] = true;
                        break;
                    case 'FAMS':
                        $currentRecord['families_as_spouse'][] = trim($value, '@');
                        break;
                    case 'FAMC':
                        $currentRecord['family_as_child'] = trim($value, '@');
                        break;
                    case 'WWW':
                        $currentRecord['url'] = $value;
                        // Save for potential CONC/CONT
                        $lastFieldTag = 'WWW';
                        $lastFieldValue = $value;
                        // Extract IDs from URL
                        $ids = extractDatabaseIds($value);
                        foreach ($ids as $db => $id) {
                            $currentRecord["{$db}_id"] = $id;
                        }
                        break;
                    case 'NOTE':
                        if (!isset($currentRecord['notes'])) {
                            $currentRecord['notes'] = [];
                        }
                        $currentRecord['notes'][] = $value;
                        // Save for potential CONC/CONT
                        $lastFieldTag = 'NOTE';
                        $lastFieldValue = $value;
                        // Extract IDs from notes
                        $ids = extractDatabaseIds($value);
                        foreach ($ids as $db => $id) {
                            if (!isset($currentRecord["{$db}_id"])) {
                                $currentRecord["{$db}_id"] = $id;
                            }
                        }
                        break;
                }
            } elseif ($currentTag === 'FAM') {
                switch ($tag) {
                    case 'HUSB':
                        $currentRecord['husband'] = trim($value, '@');
                        break;
                    case 'WIFE':
                        $currentRecord['wife'] = trim($value, '@');
                        break;
                    case 'CHIL':
                        $currentRecord['children'][] = trim($value, '@');
                        break;
                    case 'MARR':
                        $currentRecord['_in_marriage'] = true;
                        break;
                }
            }
        }
        // Level 2+ tags (grandchildren of record, or continuation lines)
        elseif ($level >= 2 && $currentRecord !== null) {
            // Handle CONC/CONT continuation lines
            if (($tag === 'CONC' || $tag === 'CONT') && $lastFieldTag && $lastFieldValue !== null) {
                if ($tag === 'CONC') {
                    // CONC = concatenate without space
                    $lastFieldValue .= $value;
                } else {
                    // CONT = new line
                    $lastFieldValue .= "\n" . $value;
                }
                // Try to extract IDs from accumulated value
                $ids = extractDatabaseIds($lastFieldValue);
                foreach ($ids as $db => $id) {
                    if (!isset($currentRecord["{$db}_id"])) {
                        $currentRecord["{$db}_id"] = $id;
                    }
                }
            }
            // Handle birth/death sublevel tags
            elseif ($level === 2 && $currentTag === 'INDI') {
                if (isset($currentRecord['_in_birth']) && $currentRecord['_in_birth']) {
                    if ($tag === 'DATE') {
                        $currentRecord['birth_date'] = $value;
                    } elseif ($tag === 'PLAC') {
                        $currentRecord['birth_location'] = $value;
                    }
                } elseif (isset($currentRecord['_in_death']) && $currentRecord['_in_death']) {
                    if ($tag === 'DATE') {
                        $currentRecord['death_date'] = $value;
                    } elseif ($tag === 'PLAC') {
                        $currentRecord['death_location'] = $value;
                    }
                }
            }
        }
        
        // Reset flags
        if ($level <= 1) {
            if (isset($currentRecord['_in_birth'])) unset($currentRecord['_in_birth']);
            if (isset($currentRecord['_in_death'])) unset($currentRecord['_in_death']);
            if (isset($currentRecord['_in_marriage'])) unset($currentRecord['_in_marriage']);
        }
    }
    
    return $data;
}

/**
 * Extract database IDs from text (URLs, notes, etc.)
 */
function extractDatabaseIds(string $text): array {
    $ids = [];
    
    // WikiTree: from wiki/ URLs or profile format
    if (preg_match('/wiki\/([A-Z][a-z]+-\d+)/i', $text, $m)) {
        $ids['wikitree'] = $m[1];
    }
    
    // FamilySearch: ark IDs or direct IDs
    if (preg_match('/ark:\/61903\/[^:]+:([A-Z0-9]{4,})|person\/([A-Z0-9]{4,}-[A-Z0-9]{3,})/i', $text, $m)) {
        $ids['familysearch'] = $m[1] ?? $m[2];
    }
    
    // FindAGrave: memorial IDs (URLs, text formats, and WikiTree template)
    if (preg_match('/findagrave\.com\/memorial\/(\d+)|FAG[:\s]+(\d+)|Find[\s-]?A[\s-]?Grave[:\s]+(\d+)|\{\{FindAGrave\|(\d+)\}\}/i', $text, $m)) {
        // Filter out empty strings from alternation groups
        $ids['findagrave'] = $m[1] ?: ($m[2] ?: ($m[3] ?: $m[4]));
    }
    
    // Ancestry: tree/person IDs
    if (preg_match('/ancestry\.com\/.*?\/(\d{9,})|Ancestry[:\s]+(\d{6,})/i', $text, $m)) {
        $ids['ancestry'] = $m[1] ?? $m[2];
    }
    
    return $ids;
}

/**
 * Build family structures with proper mother attribution
 */
function buildFamilyStructures(array $data, bool $verbose): array {
    $families = [];
    
    foreach ($data['families'] as $famId => $family) {
        $familyData = [
            'gedcom_id' => $famId,
            'husband' => null,
            'wife' => null,
            'children' => []
        ];
        
        // Get husband
        if ($family['husband'] && isset($data['individuals'][$family['husband']])) {
            $familyData['husband'] = [
                'gedcom_id' => $family['husband'],
                'name' => $data['individuals'][$family['husband']]['name'] ?? 'Unknown',
                'wikitree_id' => $data['individuals'][$family['husband']]['wikitree_id'] ?? null
            ];
        }
        
        // Get wife
        if ($family['wife'] && isset($data['individuals'][$family['wife']])) {
            $familyData['wife'] = [
                'gedcom_id' => $family['wife'],
                'name' => $data['individuals'][$family['wife']]['name'] ?? 'Unknown',
                'wikitree_id' => $data['individuals'][$family['wife']]['wikitree_id'] ?? null
            ];
        }
        
        // Get mother name safely (check if wife exists)
        $motherName = ($familyData['wife'] !== null) ? ($familyData['wife']['name'] ?? 'Unknown') : 'Unknown';
        
        // Get children with mother attribution
        foreach ($family['children'] ?? [] as $childId) {
            if (isset($data['individuals'][$childId])) {
                $child = $data['individuals'][$childId];
                $familyData['children'][] = [
                    'gedcom_id' => $childId,
                    'name' => $child['name'] ?? 'Unknown',
                    'wikitree_id' => $child['wikitree_id'] ?? null,
                    'birth_year' => extractYear($child['birth_date'] ?? null),
                    'mother' => $motherName
                ];
            }
        }
        
        $families[] = $familyData;
        
        if ($verbose && $familyData['wife']) {
            $wifeN = $familyData['wife']['name'];
            $childCount = count($familyData['children']);
            echo "  Family: {$wifeN} has {$childCount} children\n";
        }
    }
    
    return $families;
}

/**
 * Extract year from date string
 */
function extractYear(?string $date): ?int {
    if (!$date) return null;
    if (preg_match('/\b(\d{4})\b/', $date, $m)) {
        return (int)$m[1];
    }
    return null;
}

/**
 * Display sample profiles
 */
function displaySampleProfiles(array $profiles, int $limit = 5): void {
    echo "Sample Profiles with External IDs:\n\n";
    
    $count = 0;
    foreach ($profiles as $profile) {
        if ($count >= $limit) break;
        
        // Only show profiles with external IDs
        if (!$profile['wikitree_id'] && !$profile['familysearch_id'] && !$profile['findagrave_id']) {
            continue;
        }
        
        echo "  {$profile['name']}:\n";
        if ($profile['wikitree_id']) {
            echo "    WikiTree: {$profile['wikitree_id']}\n";
        }
        if ($profile['familysearch_id']) {
            echo "    FamilySearch: {$profile['familysearch_id']}\n";
        }
        if ($profile['findagrave_id']) {
            echo "    FindAGrave: {$profile['findagrave_id']}\n";
        }
        if ($profile['ancestry_id']) {
            echo "    Ancestry: {$profile['ancestry_id']}\n";
        }
        echo "\n";
        
        $count++;
    }
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
GEDCOM to Sync Data Parser

Parses WikiTree GEDCOM files into structured JSON for sync tools.
Extracts FindAGrave memorial IDs, URLs, and family relationships.

ID Extraction Formats:
  - FindAGrave: URLs, "FAG: 12345", "Find A Grave: 12345", {{FindAGrave|12345}}
  - WikiTree: wiki/Surname-123 format
  - FamilySearch: ark:/61903/... or person/ID format
  - Ancestry: URLs or "Ancestry: 123456" format

Usage:
  php gedcom_to_sync_data.php --input FILE [OPTIONS]

Required Options:
  --input FILE      Path to GEDCOM file

Optional Options:
  --output FILE     Output JSON file (default: auto-generated)
  --verbose         Show detailed processing information
  --help            Show this help message

Examples:
  php gedcom_to_sync_data.php --input GEDs/Welch-185-depth2.ged
  php gedcom_to_sync_data.php --input GEDs/Welch-185-depth2.ged --output data/welch-185-wt.json --verbose

Output:
  Creates JSON file with:
  - All profiles from GEDCOM
  - WikiTree IDs, FamilySearch IDs, FindAGrave memorial IDs
  - Birth/death years and locations
  - Family relationships with proper mother attribution
  - URLs to original WikiTree profiles

Use this output with genealogy_sync_enhanced.php for three-way sync.

HELP;
}
