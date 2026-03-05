<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * Genealogy Data Sync Tool
 * 
 * Syncs data between FamilySearch, WikiTree, and FindAGrave
 * Creates cross-reference mapping and identifies discrepancies
 * 
 * Usage:
 *   php genealogy_sync.php --wikitree Welch-185 --familysearch "Major Nicholas Welch 1740-1814 • L6ZC-3Q.md"
 *   php genealogy_sync.php --wikitree Welch-185 --familysearch nicholas-welch-family.json --output sync-report.json
 * 
 * @author David England
 * @date 2026-03-04
 */

require_once __DIR__ . '/wikitree_api_client.php';

// Parse command line arguments
$options = getopt('', ['wikitree:', 'familysearch:', 'output:', 'verbose', 'help']);

if (isset($options['help']) || empty($options['wikitree'])) {
    showHelp();
    exit(0);
}

$wikitreeId = $options['wikitree'];
$familysearchInput = $options['familysearch'] ?? null;
$outputFile = $options['output'] ?? 'genealogy-sync-' . $wikitreeId . '.json';
$verbose = isset($options['verbose']);

// Initialize sync data structure
$syncData = [
    'metadata' => [
        'principal_wikitree_id' => $wikitreeId,
        'sync_date' => date('c'),
        'version' => '1.0'
    ],
    'wikitree' => [],
    'familysearch' => [],
    'cross_references' => [],
    'discrepancies' => [],
    'recommendations' => []
];

echo "=== Genealogy Data Sync Tool ===\n\n";

// Step 1: Fetch WikiTree data
echo "Step 1: Fetching WikiTree data for $wikitreeId...\n";
$api = new WikiTreeAPI($verbose);
$wikitreeData = $api->fetchRelatives($wikitreeId);

if ($wikitreeData === false) {
    fwrite(STDERR, "Error: Failed to fetch WikiTree data\n");
    exit(1);
}

// Extract main profile and relatives
$syncData['wikitree'] = parseWikiTreeData($wikitreeData, $verbose);
echo "  Found: 1 principal, " . count($syncData['wikitree']['children']) . " children\n";

// Step 2: Parse FamilySearch data if provided
if ($familysearchInput) {
    echo "\nStep 2: Parsing FamilySearch data...\n";
    
    if (str_ends_with($familysearchInput, '.json')) {
        // Already parsed JSON
        $fsData = json_decode(file_get_contents($familysearchInput), true);
    } else {
        // Parse markdown file
        $cmd = "php " . __DIR__ . "/familysearch_parser.php --input " . escapeshellarg($familysearchInput);
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            fwrite(STDERR, "Error: Failed to parse FamilySearch data\n");
            exit(1);
        }
        
        // Load the generated JSON
        $jsonFile = str_replace('.md', '.json', basename($familysearchInput));
        $fsData = json_decode(file_get_contents($jsonFile), true);
    }
    
    $syncData['familysearch'] = $fsData;
    echo "  Found: 1 principal, " . count($fsData['children']) . " children\n";
    
    // Step 3: Create cross-references
    echo "\nStep 3: Creating cross-references...\n";
    $syncData['cross_references'] = createCrossReferences(
        $syncData['wikitree'], 
        $syncData['familysearch'],
        $verbose
    );
    echo "  Matched: " . count($syncData['cross_references']) . " records\n";
    
    // Step 4: Identify discrepancies
    echo "\nStep 4: Identifying discrepancies...\n";
    $syncData['discrepancies'] = findDiscrepancies(
        $syncData['wikitree'],
        $syncData['familysearch'],
        $syncData['cross_references'],
        $verbose
    );
    echo "  Found: " . count($syncData['discrepancies']) . " discrepancies\n";
    
    // Step 5: Generate recommendations
    echo "\nStep 5: Generating recommendations...\n";
    $syncData['recommendations'] = generateRecommendations(
        $syncData['discrepancies'],
        $syncData['cross_references']
    );
    echo "  Generated: " . count($syncData['recommendations']) . " recommendations\n";
}

// Save sync data
file_put_contents($outputFile, json_encode($syncData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\n=== Sync Complete ===\n";
echo "Report saved to: $outputFile\n";

// Display summary
displaySummary($syncData);

/* ========== FUNCTIONS ========== */

/**
 * Parse WikiTree API response into normalized format
 */
function parseWikiTreeData(array $apiResponse, bool $verbose): array {
    $data = [
        'principal' => null,
        'spouse' => null,
        'children' => []
    ];
    
    // Extract main person from first item
    $itemWrapper = $apiResponse['items'][0] ?? null;
    if (!$itemWrapper || !isset($itemWrapper['person'])) {
        return $data;
    }
    
    $person = $itemWrapper['person'];
    
    $data['principal'] = [
        'name' => $person['LongNamePrivate'] ?? $person['ShortName'] ?? 'Unknown',
        'wikitree_id' => $person['Name'] ?? null,
        'birth_year' => extractYear($person['BirthDate'] ?? null),
        'death_year' => extractYear($person['DeathDate'] ?? null),
        'birth_location' => $person['BirthLocation'] ?? null,
        'death_location' => $person['DeathLocation'] ?? null
    ];
    
    // Extract spouse(s)
    if (!empty($person['Spouses'])) {
        $spouse = reset($person['Spouses']);
        $data['spouse'] = [
            'name' => $spouse['LongNamePrivate'] ?? $spouse['ShortName'] ?? 'Unknown',
            'wikitree_id' => $spouse['Name'] ?? null,
            'birth_year' => extractYear($spouse['BirthDate'] ?? null),
            'death_year' => extractYear($spouse['DeathDate'] ?? null)
        ];
    }
    
    // Extract children
    if (!empty($person['Children'])) {
        foreach ($person['Children'] as $child) {
            $data['children'][] = [
                'name' => $child['LongNamePrivate'] ?? $child['ShortName'] ?? 'Unknown',
                'wikitree_id' => $child['Name'] ?? null,
                'birth_year' => extractYear($child['BirthDate'] ?? null),
                'death_year' => extractYear($child['DeathDate'] ?? null),
                'birth_location' => $child['BirthLocation'] ?? null,
                'death_location' => $child['DeathLocation'] ?? null
            ];
        }
    }
    
    return $data;
}

/**
 * Create cross-references between WikiTree and FamilySearch records
 */
function createCrossReferences(array $wtData, array $fsData, bool $verbose): array {
    $references = [];
    
    // Match principal
    if ($wtData['principal'] && $fsData['principal']) {
        $references[] = [
            'type' => 'principal',
            'wikitree_id' => $wtData['principal']['wikitree_id'],
            'familysearch_id' => $fsData['principal']['familysearch_id'],
            'name' => $wtData['principal']['name'],
            'confidence' => 'high'
        ];
    }
    
    // Match spouse
    if ($wtData['spouse'] && $fsData['spouse']) {
        $confidence = nameMatch($wtData['spouse']['name'], $fsData['spouse']['name']) > 0.8 ? 'high' : 'medium';
        $references[] = [
            'type' => 'spouse',
            'wikitree_id' => $wtData['spouse']['wikitree_id'],
            'familysearch_id' => $fsData['spouse']['familysearch_id'],
            'name' => $wtData['spouse']['name'],
            'confidence' => $confidence
        ];
    }
    
    // Match children - use fuzzy matching
    foreach ($wtData['children'] as $wtChild) {
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($fsData['children'] as $fsChild) {
            $score = matchPerson($wtChild, $fsChild);
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestMatch = $fsChild;
            }
        }
        
        if ($bestMatch && $bestScore > 0.6) {
            $confidence = $bestScore > 0.85 ? 'high' : ($bestScore > 0.7 ? 'medium' : 'low');
            $references[] = [
                'type' => 'child',
                'wikitree_id' => $wtChild['wikitree_id'],
                'familysearch_id' => $bestMatch['familysearch_id'],
                'wikitree_name' => $wtChild['name'],
                'familysearch_name' => $bestMatch['name'],
                'confidence' => $confidence,
                'match_score' => round($bestScore, 2)
            ];
            
            if ($verbose && $confidence !== 'high') {
                echo "  Low confidence match: {$wtChild['name']} <-> {$bestMatch['name']} (score: " . round($bestScore, 2) . ")\n";
            }
        }
    }
    
    return $references;
}

/**
 * Find discrepancies between WikiTree and FamilySearch data
 */
function findDiscrepancies(array $wtData, array $fsData, array $crossRefs, bool $verbose): array {
    $discrepancies = [];
    
    // Check for children in FamilySearch not in WikiTree
    $wtIds = array_column($crossRefs, 'wikitree_id');
    $fsIds = array_column($crossRefs, 'familysearch_id');
    
    foreach ($fsData['children'] as $fsChild) {
        if (!in_array($fsChild['familysearch_id'], $fsIds)) {
            $discrepancies[] = [
                'type' => 'missing_in_wikitree',
                'person' => $fsChild,
                'severity' => 'medium',
                'recommendation' => 'Add this person to WikiTree'
            ];
        }
    }
    
    // Check for children in WikiTree not in FamilySearch
    foreach ($wtData['children'] as $wtChild) {
        if (!in_array($wtChild['wikitree_id'], $wtIds)) {
            $discrepancies[] = [
                'type' => 'missing_in_familysearch',
                'person' => $wtChild,
                'severity' => 'low',
                'recommendation' => 'Verify this person exists in FamilySearch'
            ];
        }
    }
    
    // Check for date discrepancies in matched records
    foreach ($crossRefs as $ref) {
        if ($ref['type'] !== 'child') continue;
        
        $wtPerson = findPersonById($wtData['children'], $ref['wikitree_id']);
        $fsPerson = findPersonById($fsData['children'], $ref['familysearch_id'], 'familysearch_id');
        
        if ($wtPerson && $fsPerson) {
            // Check birth year mismatch
            if ($wtPerson['birth_year'] && $fsPerson['birth_year'] && 
                abs($wtPerson['birth_year'] - $fsPerson['birth_year']) > 2) {
                $discrepancies[] = [
                    'type' => 'birth_year_mismatch',
                    'wikitree_id' => $ref['wikitree_id'],
                    'familysearch_id' => $ref['familysearch_id'],
                    'name' => $wtPerson['name'],
                    'wikitree_year' => $wtPerson['birth_year'],
                    'familysearch_year' => $fsPerson['birth_year'],
                    'severity' => 'medium',
                    'recommendation' => 'Review primary sources to determine correct birth year'
                ];
            }
        }
    }
    
    return $discrepancies;
}

/**
 * Generate actionable recommendations
 */
function generateRecommendations(array $discrepancies, array $crossRefs): array {
    $recommendations = [];
    
    // Group by type
    $byType = [];
    foreach ($discrepancies as $disc) {
        $byType[$disc['type']][] = $disc;
    }
    
    // Priority 1: Missing people
    if (!empty($byType['missing_in_wikitree'])) {
        $recommendations[] = [
            'priority' => 'high',
            'action' => 'add_to_wikitree',
            'count' => count($byType['missing_in_wikitree']),
            'description' => 'Add ' . count($byType['missing_in_wikitree']) . ' missing children to WikiTree',
            'items' => array_column($byType['missing_in_wikitree'], 'person')
        ];
    }
    
    // Priority 2: Date mismatches
    if (!empty($byType['birth_year_mismatch'])) {
        $recommendations[] = [
            'priority' => 'medium',
            'action' => 'resolve_dates',
            'count' => count($byType['birth_year_mismatch']),
            'description' => 'Resolve ' . count($byType['birth_year_mismatch']) . ' birth year discrepancies',
            'items' => $byType['birth_year_mismatch']
        ];
    }
    
    // Priority 3: Add FamilySearch IDs to WikiTree
    $needsFsId = array_filter($crossRefs, fn($r) => $r['confidence'] === 'high');
    if (!empty($needsFsId)) {
        $recommendations[] = [
            'priority' => 'low',
            'action' => 'add_familysearch_ids',
            'count' => count($needsFsId),
            'description' => 'Add FamilySearch IDs to ' . count($needsFsId) . ' WikiTree profiles',
            'items' => $needsFsId
        ];
    }
    
    return $recommendations;
}

/**
 * Match score between two person records
 */
function matchPerson(array $person1, array $person2): float {
    $score = 0;
    $factors = 0;
    
    // Name similarity (most important)
    $nameSim = nameMatch($person1['name'], $person2['name']);
    $score += $nameSim * 0.5;
    $factors += 0.5;
    
    // Birth year (if available)
    if ($person1['birth_year'] && $person2['birth_year']) {
        $yearDiff = abs($person1['birth_year'] - $person2['birth_year']);
        $yearSim = max(0, 1 - ($yearDiff / 10)); // Penalize each year of difference
        $score += $yearSim * 0.3;
        $factors += 0.3;
    }
    
    // Death year (if available)
    if ($person1['death_year'] && $person2['death_year']) {
        $yearDiff = abs($person1['death_year'] - $person2['death_year']);
        $yearSim = max(0, 1 - ($yearDiff / 10));
        $score += $yearSim * 0.2;
        $factors += 0.2;
    }
    
    return $factors > 0 ? $score / $factors : 0;
}

/**
 * Calculate name similarity
 */
function nameMatch(string $name1, string $name2): float {
    // Normalize names
    $n1 = strtolower(trim($name1));
    $n2 = strtolower(trim($name2));
    
    // Exact match
    if ($n1 === $n2) return 1.0;
    
    // Use Levenshtein distance for similarity
    $maxLen = max(strlen($n1), strlen($n2));
    if ($maxLen === 0) return 0;
    
    $distance = levenshtein($n1, $n2);
    return 1 - ($distance / $maxLen);
}

/**
 * Extract year from date string
 */
function extractYear(?string $date): ?int {
    if (!$date) return null;
    if (preg_match('/\b(\d{4})\b/', $date, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

/**
 * Find person by ID in array
 */
function findPersonById(array $people, string $id, string $idField = 'wikitree_id'): ?array {
    foreach ($people as $person) {
        if (isset($person[$idField]) && $person[$idField] === $id) {
            return $person;
        }
    }
    return null;
}

/**
 * Display summary of sync results
 */
function displaySummary(array $syncData): void {
    echo "\n=== Summary ===\n";
    
    if (!empty($syncData['cross_references'])) {
        $highConf = count(array_filter($syncData['cross_references'], fn($r) => $r['confidence'] === 'high'));
        echo "Cross-references: " . count($syncData['cross_references']) . " total\n";
        echo "  High confidence: $highConf\n";
    }
    
    if (!empty($syncData['discrepancies'])) {
        echo "Discrepancies: " . count($syncData['discrepancies']) . "\n";
        $byType = [];
        foreach ($syncData['discrepancies'] as $disc) {
            $byType[$disc['type']] = ($byType[$disc['type']] ?? 0) + 1;
        }
        foreach ($byType as $type => $count) {
            echo "  " . str_replace('_', ' ', $type) . ": $count\n";
        }
    }
    
    if (!empty($syncData['recommendations'])) {
        echo "\nRecommendations:\n";
        foreach ($syncData['recommendations'] as $rec) {
            echo "  [{$rec['priority']}] {$rec['description']}\n";
        }
    }
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
Genealogy Data Sync Tool

Syncs data between FamilySearch, WikiTree, and FindAGrave.
Creates cross-reference mapping and identifies discrepancies.

Usage:
  php genealogy_sync.php --wikitree ID [--familysearch FILE] [OPTIONS]

Required Options:
  --wikitree ID           WikiTree profile ID (e.g., Welch-185)

Optional Options:
  --familysearch FILE     FamilySearch data file (.md or .json)
  --output FILE           Output JSON file (default: auto-generated)
  --verbose               Show detailed processing information
  --help                  Show this help message

Examples:
  # Fetch WikiTree data only
  php genealogy_sync.php --wikitree Welch-185
  
  # Compare WikiTree and FamilySearch
  php genealogy_sync.php --wikitree Welch-185 --familysearch "Major Nicholas Welch 1740-1814 • L6ZC-3Q.md"
  
  # Use pre-parsed FamilySearch JSON
  php genealogy_sync.php --wikitree Welch-185 --familysearch nicholas-welch-family.json --verbose

Output:
  Creates a JSON file with:
  - WikiTree family data
  - FamilySearch family data (if provided)
  - Cross-references between systems
  - Discrepancies and data conflicts
  - Actionable recommendations

HELP;
}
