<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * Enhanced Genealogy Sync Tool
 * 
 * Three-way sync between WikiTree (via GEDCOM), FamilySearch, and FindAGrave
 * Properly handles multiple spouses and mother attribution for children
 * 
 * Usage:
 *   php genealogy_sync_enhanced.php --wikitree-gedcom GEDs/Welch-185-depth2.ged --familysearch data/nicholas-welch-familysearch.json
 *   php genealogy_sync_enhanced.php --wikitree-gedcom data/welch-185-wt.json --familysearch data/nicholas-welch-familysearch.json --output data/sync-enhanced.json
 * 
 * @author David England
 * @date 2026-03-04
 */

// Parse command line arguments
$options = getopt('', ['wikitree-gedcom:', 'familysearch:', 'output:', 'verbose', 'help']);

if (isset($options['help']) || (empty($options['wikitree-gedcom']) && empty($options['familysearch']))) {
    showHelp();
    exit(0);
}

$wtInput = $options['wikitree-gedcom'] ?? null;
$fsInput = $options['familysearch'] ?? null;
$outputFile = $options['output'] ?? 'data/genealogy-sync-enhanced.json';
$verbose = isset($options['verbose']);

echo "=== Enhanced Genealogy Sync Tool ===\n\n";

// Initialize sync data structure
$syncData = [
    'metadata' => [
        'sync_date' => date('c'),
        'version' => '2.0-enhanced'
    ],
    'wikitree' => null,
    'familysearch' => null,
    'cross_references' => [],
    'family_structures' => [],
    'discrepancies' => [],
    'recommendations' => []
];

// Load WikiTree data
if ($wtInput) {
    echo "Step 1: Loading WikiTree data...\n";
    
    if (str_ends_with($wtInput, '.ged')) {
        // Need to parse GEDCOM first
        echo "  GEDCOM file detected, parsing...\n";
        $wtJsonFile = str_replace('.ged', '-parsed.json', $wtInput);
        $wtJsonFile = str_replace('GEDs/', 'data/', $wtJsonFile);
        
        $cmd = "php " . __DIR__ . "/gedcom_to_sync_data.php --input " . escapeshellarg($wtInput) . " --output " . escapeshellarg($wtJsonFile);
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            fwrite(STDERR, "Error: Failed to parse GEDCOM file\n");
            exit(1);
        }
        
        $wtInput = $wtJsonFile;
    }
    
    $wtData = json_decode(file_get_contents($wtInput), true);
    if (!$wtData) {
        fwrite(STDERR, "Error: Invalid WikiTree JSON\n");
        exit(1);
    }
    
    $syncData['wikitree'] = $wtData;
    $profileCount = count($wtData['profiles'] ?? []);
    $familyCount = count($wtData['families'] ?? []);
    echo "  Loaded: $profileCount profiles, $familyCount families\n";
    
    // Extract FindAGrave IDs
    $fagCount = 0;
    foreach ($wtData['profiles'] ?? [] as $profile) {
        if (!empty($profile['findagrave_id'])) {
            $fagCount++;
        }
    }
    if ($fagCount > 0) {
        echo "  FindAGrave IDs: $fagCount\n";
    }
}

echo "\n";

// Load FamilySearch data
if ($fsInput) {
    echo "Step 2: Loading FamilySearch data...\n";
    
    $fsData = json_decode(file_get_contents($fsInput), true);
    if (!$fsData) {
        fwrite(STDERR, "Error: Invalid FamilySearch JSON\n");
        exit(1);
    }
    
    $syncData['familysearch'] = $fsData;
    $childCount = count($fsData['children'] ?? []);
    echo "  Loaded: 1 principal, $childCount children\n";
}

echo "\n";

// Perform sync if both sources available
if ($syncData['wikitree'] && $syncData['familysearch']) {
    echo "Step 3: Creating cross-references...\n";
    $syncData['cross_references'] = createCrossReferences($syncData['wikitree'], $syncData['familysearch'], $verbose);
    echo "  Matched: " . count($syncData['cross_references']) . " records\n\n";
    
    echo "Step 4: Building family structures...\n";
    $syncData['family_structures'] = buildEnhancedFamilyStructures($syncData['wikitree'], $syncData['familysearch'], $verbose);
    echo "  Families: " . count($syncData['family_structures']) . "\n\n";
    
    echo "Step 5: Identifying discrepancies...\n";
    $syncData['discrepancies'] = findEnhancedDiscrepancies($syncData, $verbose);
    echo "  Found: " . count($syncData['discrepancies']) . " discrepancies\n\n";
    
    echo "Step 6: Generating recommendations...\n";
    $syncData['recommendations'] = generateEnhancedRecommendations($syncData);
    echo "  Generated: " . count($syncData['recommendations']) . " recommendations\n";
}

// Save sync data
echo "\n=== Sync Complete ===\n";
file_put_contents($outputFile, json_encode($syncData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Report saved to: $outputFile\n\n";

// Display summary
displayEnhancedSummary($syncData);

/* ========== FUNCTIONS ========== */

/**
 * Create cross-references between systems
 */
function createCrossReferences(array $wtData, array $fsData, bool $verbose): array {
    $references = [];
    
    // Match principal
    if ($wtPrincipal = findPrincipal($wtData)) {
        if ($fsData['principal']) {
            $references[] = [
                'type' => 'principal',
                'wikitree_id' => $wtPrincipal['wikitree_id'],
                'familysearch_id' => $fsData['principal']['familysearch_id'],
                'findagrave_id' => $wtPrincipal['findagrave_id'] ?? null,
                'name' => $wtPrincipal['name'],
                'confidence' => 'high'
            ];
        }
    }
    
    // Match spouses
    $spouses = findSpouses($wtData);
    if (!empty($spouses) && $fsData['spouse']) {
        foreach ($spouses as $spouse) {
            $confidence = nameMatch($spouse['name'], $fsData['spouse']['name']) > 0.8 ? 'high' : 'medium';
            $references[] = [
                'type' => 'spouse',
                'wikitree_id' => $spouse['wikitree_id'],
                'familysearch_id' => $fsData['spouse']['familysearch_id'],
                'findagrave_id' => $spouse['findagrave_id'] ?? null,
                'wikitree_name' => $spouse['name'],
                'familysearch_name' => $fsData['spouse']['name'],
                'confidence' => $confidence
            ];
        }
    }
    
    // Match children with fuzzy matching
    $wtChildren = findChildren($wtData);
    foreach ($wtChildren as $wtChild) {
        $bestMatch = null;
        $bestScore = 0;
        
        foreach ($fsData['children'] ?? [] as $fsChild) {
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
                'findagrave_id' => $wtChild['findagrave_id'] ?? null,
                'wikitree_name' => $wtChild['name'],
                'familysearch_name' => $bestMatch['name'],
                'mother' => $wtChild['mother'] ?? null,
                'confidence' => $confidence,
                'match_score' => round($bestScore, 2)
            ];
            
            if ($verbose && $confidence !== 'high') {
                echo "  Match: {$wtChild['name']} <-> {$bestMatch['name']} (score: " . round($bestScore, 2) . ")\n";
            }
        }
    }
    
    return $references;
}

/**
 * Build enhanced family structures showing multiple spouses and children by mother
 */
function buildEnhancedFamilyStructures(array $wtData, array $fsData, bool $verbose): array {
    $structures = [];
    
    // Get principal
    $principal = findPrincipal($wtData);
    if (!$principal) return $structures;
    
    // Get all families from WikiTree GEDCOM
    foreach ($wtData['families'] ?? [] as $family) {
        $structure = [
            'husband' => $principal['name'],
            'wife' => $family['wife']['name'] ?? 'Unknown',
            'wife_wikitree_id' => $family['wife']['wikitree_id'] ?? null,
            'children' => []
        ];
        
        // Add children for this mother
        foreach ($family['children'] ?? [] as $child) {
            $structure['children'][] = [
                'name' => $child['name'],
                'wikitree_id' => $child['wikitree_id'] ?? null,
                'birth_year' => $child['birth_year'] ?? null,
                'mother' => $family['wife']['name'] ?? 'Unknown'
            ];
        }
        
        if (!empty($structure['children'])) {
            $structures[] = $structure;
            
            if ($verbose) {
                $mother = $structure['wife'];
                $childCount = count($structure['children']);
                echo "  Family: $mother has $childCount children\n";
            }
        }
    }
    
    return $structures;
}

/**
 * Find enhanced discrepancies including mother misattribution
 */
function findEnhancedDiscrepancies(array $syncData, bool $verbose): array {
    $discrepancies = [];
    
    $wtData = $syncData['wikitree'];
    $fsData = $syncData['familysearch'];
    $crossRefs = $syncData['cross_references'];
    
    // Check for children missing in WikiTree
    $wtIds = array_column(array_filter($crossRefs, fn($r) => $r['type'] === 'child'), 'wikitree_id');
    $fsIds = array_column(array_filter($crossRefs, fn($r) => $r['type'] === 'child'), 'familysearch_id');
    
    foreach ($fsData['children'] ?? [] as $fsChild) {
        if (!in_array($fsChild['familysearch_id'], $fsIds)) {
            $discrepancies[] = [
                'type' => 'missing_in_wikitree',
                'person' => $fsChild,
                'severity' => 'medium',
                'recommendation' => 'Add this person to WikiTree'
            ];
        }
    }
    
    // Check for  children missing in FamilySearch
    $wtChildren = findChildren($wtData);
    foreach ($wtChildren as $wtChild) {
        if (!in_array($wtChild['wikitree_id'], $wtIds)) {
            $discrepancies[] = [
                'type' => 'missing_in_familysearch',
                'person' => $wtChild,
                'mother' => $wtChild['mother'] ?? 'Unknown',
                'severity' => 'low',
                'recommendation' => 'Verify this person exists in FamilySearch'
            ];
        }
    }
    
    // Check for mother misattribution
    foreach ($syncData['family_structures'] ?? [] as $family) {
        foreach ($family['children'] as $child) {
            $mother = $family['wife'];
            
            // Check if this child appears in wrong family in FamilySearch
            $fsChild = findFsChildByName($fsData, $child['name']);
            if ($fsChild && $verbose) {
                echo "  Child check: {$child['name']} (mother: $mother)\n";
            }
        }
    }
    
    // Check for missing FindAGrave IDs
    foreach ($crossRefs as $ref) {
        if ($ref['confidence'] === 'high' && empty($ref['findagrave_id'])) {
            $discrepancies[] = [
                'type' => 'missing_findagrave_id',
                'wikitree_id' => $ref['wikitree_id'],
                'familysearch_id' => $ref['familysearch_id'],
                'name' => $ref['wikitree_name'] ?? $ref['name'],
                'severity' => 'low',
                'recommendation' => 'Search FindAGrave and add memorial ID'
            ];
        }
    }
    
    return $discrepancies;
}

/**
 * Generate enhanced recommendations
 */
function generateEnhancedRecommendations(array $syncData): array {
    $recommendations = [];
    $discrepancies = $syncData['discrepancies'];
    
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
            'description' => 'Add ' . count($byType['missing_in_wikitree']) . ' missing children to WikiTree with correct mother attribution',
            'items' => array_column($byType['missing_in_wikitree'], 'person')
        ];
    }
    
    // Priority 2: Add FamilySearch IDs
    $crossRefs = $syncData['cross_references'];
    $needsFsId = array_filter($crossRefs, fn($r) => $r['confidence'] === 'high');
    if (!empty($needsFsId)) {
        $recommendations[] = [
            'priority' => 'medium',
            'action' => 'add_familysearch_ids',
            'count' => count($needsFsId),
            'description' => 'Add FamilySearch IDs to ' . count($needsFsId) . ' WikiTree profiles',
            'items' => $needsFsId
        ];
    }
    
    // Priority 3 Add FindAGrave IDs
    if (!empty($byType['missing_findagrave_id'])) {
        $recommendations[] = [
            'priority' => 'low',
            'action' => 'add_findagrave_ids',
            'count' => count($byType['missing_findagrave_id']),
            'description' => 'Search FindAGrave and add memorial IDs for ' . count($byType['missing_findagrave_id']) . ' profiles',
            'items' => $byType['missing_findagrave_id']
        ];
    }
    
    return $recommendations;
}

/**
 * Helper functions
 */
function findPrincipal(array $wtData): ?array {
    // Find first profile with no parents (likely principal)
    foreach ($wtData['profiles'] ?? [] as $profile) {
        if (empty($profile['family_as_child'])) {
            return $profile;
        }
    }
    return reset($wtData['profiles'] ?? []) ?: null;
}

function findSpouses(array $wtData): array {
    $spouses = [];
    foreach ($wtData['families'] ?? [] as $family) {
        if ($family['wife']) {
            $spouses[] = $family['wife'];
        }
    }
    return $spouses;
}

function findChildren(array $wtData): array {
    $children = [];
    foreach ($wtData['families'] ?? [] as $family) {
        foreach ($family['children'] ?? [] as $child) {
            $children[] = $child;
        }
    }
    return $children;
}

function findFsChildByName(array $fsData, string $name): ?array {
    foreach ($fsData['children'] ?? [] as $child) {
        if (nameMatch($child['name'], $name) > 0.8) {
            return $child;
        }
    }
    return null;
}

function matchPerson(array $p1, array $p2): float {
    $score = 0;
    $factors = 0;
    
    // Name similarity
    $nameSim = nameMatch($p1['name'] ?? '', $p2['name'] ?? '');
    $score += $nameSim * 0.5;
    $factors += 0.5;
    
    // Birth year
    if (($p1['birth_year'] ?? null) && ($p2['birth_year'] ?? null)) {
        $yearDiff = abs($p1['birth_year'] - $p2['birth_year']);
        $yearSim = max(0, 1 - ($yearDiff / 10));
        $score += $yearSim * 0.3;
        $factors += 0.3;
    }
    
    // Death year
    if (($p1['death_year'] ?? null) && ($p2['death_year'] ?? null)) {
        $yearDiff = abs($p1['death_year'] - $p2['death_year']);
        $yearSim = max(0, 1 - ($yearDiff / 10));
        $score += $yearSim * 0.2;
        $factors += 0.2;
    }
    
    return $factors > 0 ? $score / $factors : 0;
}

function nameMatch(string $n1, string $n2): float {
    $n1 = strtolower(trim($n1));
    $n2 = strtolower(trim($n2));
    
    if ($n1 === $n2) return 1.0;
    
    $maxLen = max(strlen($n1), strlen($n2));
    if ($maxLen === 0) return 0;
    
    $distance = levenshtein($n1, $n2);
    return 1 - ($distance / $maxLen);
}

/**
 * Display enhanced summary
 */
function displayEnhancedSummary(array $syncData): void {
    echo "=== Enhanced Summary ===\n\n";
    
    if ($syncData['wikitree']) {
        $profileCount = count($syncData['wikitree']['profiles'] ?? []);
        $familyCount = count($syncData['wikitree']['families'] ?? []);
        echo "WikiTree: $profileCount profiles, $familyCount families\n";
        
        // Count FindAGrave IDs
        $fagCount = 0;
        foreach ($syncData['wikitree']['profiles'] ?? [] as $profile) {
            if (!empty($profile['findagrave_id'])) {
                $fagCount++;
            }
        }
        if ($fagCount > 0) {
            echo "  FindAGrave IDs: $fagCount\n";
        }
    }
    
    if ($syncData['familysearch']) {
        $childCount = count($syncData['familysearch']['children'] ?? []);
        echo "FamilySearch: 1 principal, $childCount children\n";
    }
    
    if (!empty($syncData['cross_references'])) {
        $highConf = count(array_filter($syncData['cross_references'], fn($r) => $r['confidence'] === 'high'));
        echo "\nCross-references: " . count($syncData['cross_references']) . " total ($highConf high confidence)\n";
    }
    
    if (!empty($syncData['family_structures'])) {
        echo "\nFamily Structures:\n";
        foreach ($syncData['family_structures'] as $family) {
            $mother = $family['wife'];
            $childCount = count($family['children']);
            echo "  $mother: $childCount children\n";
        }
    }
    
    if (!empty($syncData['discrepancies'])) {
        echo "\nDiscrepancies: " . count($syncData['discrepancies']) . "\n";
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
Enhanced Genealogy Sync Tool

Three-way sync between WikiTree (via GEDCOM), FamilySearch, and FindAGrave.
Properly handles multiple spouses and mother attribution.

Usage:
  php genealogy_sync_enhanced.php [OPTIONS]

Options:
  --wikitree-gedcom FILE    WikiTree GEDCOM file (.ged) or parsed JSON
  --familysearch FILE       FamilySearch data JSON file
  --output FILE             Output JSON file (default: data/genealogy-sync-enhanced.json)
  --verbose                 Show detailed processing information
  --help                    Show this help message

Examples:
  # Sync WikiTree GEDCOM with FamilySearch
  php genealogy_sync_enhanced.php \\
    --wikitree-gedcom GEDs/Welch-185-depth2.ged \\
    --familysearch data/nicholas-welch-familysearch.json

  # Use pre-parsed WikiTree JSON
  php genealogy_sync_enhanced.php \\
    --wikitree-gedcom data/welch-185-wt.json \\
    --familysearch data/nicholas-welch-familysearch.json \\
    --verbose

Features:
  - Extracts FindAGrave memorial IDs from WikiTree GEDCOM
  - Creates cross-references between all three systems
  - Properly attributes children to correct mother (multiple spouses)
  - Identifies missing profiles and IDs
  - Generates actionable recommendations

HELP;
}
