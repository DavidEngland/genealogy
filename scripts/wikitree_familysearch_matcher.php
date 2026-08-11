<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree to FamilySearch ID Matcher
 * 
 * Matches WikiTree IDs to FamilySearch PIDs using fuzzy name/date matching
 * Fetches children from WikiTree API and matches against FamilySearch data
 * 
 * Usage:
 *   php wikitree_familysearch_matcher.php --wikitree Welch-185 --familysearch data/nicholas-welch-fs-children.json
 *   php wikitree_familysearch_matcher.php --wikitree Welch-185 --familysearch data/nicholas-welch-fs-children.json --output matches.json
 *   php wikitree_familysearch_matcher.php --wikitree Welch-185 Moore-51757 --familysearch data/nicholas-welch-fs-children.json --verbose
 * 
 * @author David England
 * @date 2026-03-05
 */

require_once __DIR__ . '/wikitree_api_client.php';

// Parse command line arguments
$options = getopt('', ['wikitree:', 'familysearch:', 'output:', 'verbose', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

if (empty($options['wikitree']) || empty($options['familysearch'])) {
    fwrite(STDERR, "Error: --wikitree and --familysearch are required\n\n");
    showHelp();
    exit(1);
}

// Handle multiple WikiTree IDs
$wikitreeIds = is_array($options['wikitree']) ? $options['wikitree'] : [$options['wikitree']];
$familysearchFile = $options['familysearch'];
$outputFile = $options['output'] ?? 'wikitree-familysearch-matches.json';
$verbose = isset($options['verbose']);

echo "=== WikiTree to FamilySearch ID Matcher ===\n\n";

// Step 1: Load FamilySearch data
echo "Step 1: Loading FamilySearch data...\n";
if (!file_exists($familysearchFile)) {
    fwrite(STDERR, "Error: FamilySearch file not found: $familysearchFile\n");
    exit(1);
}

$fsData = json_decode(file_get_contents($familysearchFile), true);
if (!$fsData) {
    fwrite(STDERR, "Error: Invalid JSON in FamilySearch file\n");
    exit(1);
}

echo "  Loaded " . count($fsData) . " FamilySearch records\n";

// Step 2: Fetch WikiTree data for all IDs
echo "\nStep 2: Fetching WikiTree data...\n";
$api = new WikiTreeAPI($verbose);
$allWikiTreePeople = [];

foreach ($wikitreeIds as $wikitreeId) {
    echo "  Fetching $wikitreeId...\n";
    
    $wtData = $api->fetchRelatives($wikitreeId);
    
    if ($wtData === false) {
        fwrite(STDERR, "  Warning: Failed to fetch $wikitreeId\n");
        continue;
    }
    
    // Extract person and children
    $itemWrapper = $wtData['items'][0] ?? null;
    if (!$itemWrapper || !isset($itemWrapper['person'])) {
        fwrite(STDERR, "  Warning: No data for $wikitreeId\n");
        continue;
    }
    
    $person = $itemWrapper['person'];
    
    // Add the principal person
    $allWikiTreePeople[] = [
        'name' => buildFullName($person),
        'wikitree_id' => $person['Name'] ?? null,
        'birth_year' => extractYear($person['BirthDate'] ?? null),
        'death_year' => extractYear($person['DeathDate'] ?? null),
        'gender' => $person['Gender'] ?? null,
        'type' => 'principal'
    ];
    
    // Add children
    if (!empty($person['Children'])) {
        foreach ($person['Children'] as $child) {
            $allWikiTreePeople[] = [
                'name' => buildFullName($child),
                'wikitree_id' => $child['Name'] ?? null,
                'birth_year' => extractYear($child['BirthDate'] ?? null),
                'death_year' => extractYear($child['DeathDate'] ?? null),
                'gender' => $child['Gender'] ?? null,
                'type' => 'child',
                'parent' => $wikitreeId
            ];
        }
        
        echo "    Found " . count($person['Children']) . " children\n";
    }
}

echo "  Total WikiTree records: " . count($allWikiTreePeople) . "\n";

// Step 3: Match WikiTree to FamilySearch
echo "\nStep 3: Matching WikiTree IDs to FamilySearch PIDs...\n";
$matches = [];
$unmatched_wt = [];
$unmatched_fs = [];
$matched_fs_pids = [];

foreach ($allWikiTreePeople as $wtPerson) {
    $bestMatch = null;
    $bestScore = 0;
    
    if ($verbose) {
        echo "  Matching: {$wtPerson['name']} ({$wtPerson['wikitree_id']})\n";
    }
    
    foreach ($fsData as $fsPerson) {
        // Skip if already matched
        if (in_array($fsPerson['pid'], $matched_fs_pids)) {
            continue;
        }
        
        $score = calculateMatchScore($wtPerson, $fsPerson, $verbose);
        
        if ($score > $bestScore) {
            $bestScore = $score;
            $bestMatch = $fsPerson;
        }
    }
    
    // Accept match if score is high enough
    if ($bestScore >= 60) {
        $confidence = $bestScore >= 90 ? 'high' : ($bestScore >= 75 ? 'medium' : 'low');
        
        $matches[] = [
            'wikitree_id' => $wtPerson['wikitree_id'],
            'wikitree_name' => $wtPerson['name'],
            'familysearch_pid' => $bestMatch['pid'],
            'familysearch_name' => $bestMatch['fullName'],
            'familysearch_lifespan' => $bestMatch['lifespan'],
            'match_score' => $bestScore,
            'confidence' => $confidence,
            'type' => $wtPerson['type'],
            'parent' => $wtPerson['parent'] ?? null
        ];
        
        $matched_fs_pids[] = $bestMatch['pid'];
        
        echo "  ✓ MATCH: {$wtPerson['wikitree_id']} → {$bestMatch['pid']} ({$confidence}, score: $bestScore)\n";
        echo "    WT: {$wtPerson['name']} ({$wtPerson['birth_year']}-{$wtPerson['death_year']})\n";
        echo "    FS: {$bestMatch['fullName']} ({$bestMatch['lifespan']})\n\n";
    } else {
        $unmatched_wt[] = $wtPerson;
        
        if ($verbose) {
            echo "  ✗ NO MATCH: {$wtPerson['wikitree_id']} ({$wtPerson['name']}) - best score: $bestScore\n\n";
        }
    }
}

// Find unmatched FamilySearch records
foreach ($fsData as $fsPerson) {
    if (!in_array($fsPerson['pid'], $matched_fs_pids)) {
        $unmatched_fs[] = $fsPerson;
    }
}

// Step 4: Generate summary
echo "\n=== MATCHING SUMMARY ===\n\n";
echo "Matched: " . count($matches) . " records\n";
echo "Unmatched WikiTree: " . count($unmatched_wt) . " records\n";
echo "Unmatched FamilySearch: " . count($unmatched_fs) . " records\n\n";

if (!empty($unmatched_wt)) {
    echo "UNMATCHED WIKITREE RECORDS:\n";
    foreach ($unmatched_wt as $person) {
        echo "  - {$person['wikitree_id']}: {$person['name']} ({$person['birth_year']}-{$person['death_year']})\n";
    }
    echo "\n";
}

if (!empty($unmatched_fs)) {
    echo "UNMATCHED FAMILYSEARCH RECORDS:\n";
    foreach ($unmatched_fs as $person) {
        echo "  - {$person['pid']}: {$person['fullName']} ({$person['lifespan']})\n";
    }
    echo "\n";
}

// Step 5: Save results
$output = [
    'metadata' => [
        'generated' => date('c'),
        'wikitree_ids' => $wikitreeIds,
        'familysearch_file' => $familysearchFile,
        'total_matches' => count($matches),
        'unmatched_wikitree' => count($unmatched_wt),
        'unmatched_familysearch' => count($unmatched_fs)
    ],
    'matches' => $matches,
    'unmatched_wikitree' => $unmatched_wt,
    'unmatched_familysearch' => $unmatched_fs
];

file_put_contents($outputFile, json_encode($output, JSON_PRETTY_PRINT));
echo "Results saved to: $outputFile\n";

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Build full name from WikiTree person data
 */
function buildFullName(array $person): string {
    $parts = [];
    
    // Try RealName first (includes full name)
    if (!empty($person['RealName'])) {
        return $person['RealName'];
    }
    
    // Otherwise construct from parts
    if (!empty($person['FirstName'])) {
        $parts[] = $person['FirstName'];
    }
    
    if (!empty($person['MiddleName'])) {
        $parts[] = $person['MiddleName'];
    }
    
    // Prefer LastNameAtBirth, fall back to LastNameCurrent
    $lastName = $person['LastNameAtBirth'] ?? $person['LastNameCurrent'] ?? null;
    if (!empty($lastName)) {
        $parts[] = $lastName;
    }
    
    return !empty($parts) ? implode(' ', $parts) : 'Unknown';
}

/**
 * Calculate match score between WikiTree and FamilySearch records
 */
function calculateMatchScore(array $wtPerson, array $fsPerson, bool $verbose): int {
    $score = 0;
    
    // Name similarity (0-50 points)
    $nameScore = calculateNameSimilarity($wtPerson['name'], $fsPerson['fullName']);
    $score += $nameScore;
    
    if ($verbose) {
        echo "    Name similarity: $nameScore / 50 ({$wtPerson['name']} vs {$fsPerson['fullName']})\n";
    }
    
    // Birth year match (0-25 points)
    if ($wtPerson['birth_year'] && $fsPerson['birth_year']) {
        $birthDiff = abs($wtPerson['birth_year'] - $fsPerson['birth_year']);
        if ($birthDiff === 0) {
            $score += 25;
            if ($verbose) echo "    Birth year: exact match → +25\n";
        } elseif ($birthDiff <= 2) {
            $score += 20;
            if ($verbose) echo "    Birth year: close match (±$birthDiff) → +20\n";
        } elseif ($birthDiff <= 5) {
            $score += 10;
            if ($verbose) echo "    Birth year: moderate match (±$birthDiff) → +10\n";
        } else {
            if ($verbose) echo "    Birth year: no match (±$birthDiff) → +0\n";
        }
    }
    
    // Death year match (0-25 points)
    if ($wtPerson['death_year'] && $fsPerson['death_year']) {
        $deathDiff = abs($wtPerson['death_year'] - $fsPerson['death_year']);
        if ($deathDiff === 0) {
            $score += 25;
            if ($verbose) echo "    Death year: exact match → +25\n";
        } elseif ($deathDiff <= 2) {
            $score += 20;
            if ($verbose) echo "    Death year: close match (±$deathDiff) → +20\n";
        } elseif ($deathDiff <= 5) {
            $score += 10;
            if ($verbose) echo "    Death year: moderate match (±$deathDiff) → +10\n";
        } else {
            if ($verbose) echo "    Death year: no match (±$deathDiff) → +0\n";
        }
    }
    
    if ($verbose) {
        echo "    TOTAL SCORE: $score / 100\n";
    }
    
    return $score;
}

/**
 * Calculate name similarity score (0-50)
 */
function calculateNameSimilarity(string $name1, string $name2): int {
    // Normalize names
    $name1 = normalizeName($name1);
    $name2 = normalizeName($name2);
    
    // Exact match
    if ($name1 === $name2) {
        return 50;
    }
    
    // Extract first and last names
    $parts1 = explode(' ', $name1);
    $parts2 = explode(' ', $name2);
    
    $first1 = $parts1[0] ?? '';
    $last1 = end($parts1);
    
    $first2 = $parts2[0] ?? '';
    $last2 = end($parts2);
    
    $score = 0;
    
    // First name match (0-25 points)
    if ($first1 === $first2) {
        $score += 25;
    } elseif (similar_text($first1, $first2) / max(strlen($first1), strlen($first2), 1) > 0.8) {
        $score += 20;
    } elseif (levenshtein($first1, $first2) <= 2) {
        $score += 15;
    }
    
    // Last name match (0-25 points)
    if ($last1 === $last2) {
        $score += 25;
    } elseif (similar_text($last1, $last2) / max(strlen($last1), strlen($last2), 1) > 0.8) {
        $score += 20;
    } elseif (levenshtein($last1, $last2) <= 2) {
        $score += 15;
    }
    
    return $score;
}

/**
 * Normalize name for comparison
 */
function normalizeName(string $name): string {
    // Remove titles, suffixes, parenthetical info
    $name = preg_replace('/\(.*?\)/', '', $name);
    $name = preg_replace('/\b(Jr\.?|Sr\.?|II|III|IV)\b/i', '', $name);
    
    // Convert to lowercase, remove extra spaces
    $name = strtolower(trim(preg_replace('/\s+/', ' ', $name)));
    
    return $name;
}

/**
 * Extract year from various date formats
 */
function extractYear(?string $date): ?int {
    if (!$date) {
        return null;
    }
    
    // Try various patterns
    if (preg_match('/\b(1[6-9]\d{2}|20\d{2})\b/', $date, $matches)) {
        return (int)$matches[1];
    }
    
    return null;
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
WikiTree to FamilySearch ID Matcher

Matches WikiTree IDs to FamilySearch PIDs using fuzzy name/date matching.
Fetches children from WikiTree API and matches against FamilySearch data.

USAGE:
    php wikitree_familysearch_matcher.php --wikitree WIKITREE_ID --familysearch FS_FILE [OPTIONS]

REQUIRED:
    --wikitree WIKITREE_ID          WikiTree ID(s) to fetch (can specify multiple)
                                    Examples: Welch-185, Moore-51757
    
    --familysearch FS_FILE          FamilySearch JSON file with children data
                                    Example: data/nicholas-welch-fs-children.json

OPTIONS:
    --output FILE                   Output file for matches (default: wikitree-familysearch-matches.json)
    --verbose                       Show detailed matching process
    --help                          Show this help message

EXAMPLES:
    # Match Nicholas Welch's children
    php wikitree_familysearch_matcher.php --wikitree Welch-185 --familysearch data/nicholas-welch-fs-children.json
    
    # Match both parents' children (if they have different WikiTree IDs)
    php wikitree_familysearch_matcher.php --wikitree Welch-185 Moore-51757 --familysearch data/nicholas-welch-fs-children.json --verbose
    
    # Save to custom output file
    php wikitree_familysearch_matcher.php --wikitree Welch-185 --familysearch data/nicholas-welch-fs-children.json --output welch-matches.json

OUTPUT:
    JSON file with three sections:
    - matches: Array of matched records with WikiTree ID → FamilySearch PID mappings
    - unmatched_wikitree: WikiTree records with no FamilySearch match
    - unmatched_familysearch: FamilySearch records with no WikiTree match

MATCHING LOGIC:
    - Name similarity: 0-50 points (first name + last name)
    - Birth year: 0-25 points (exact match = 25, ±2 years = 20, ±5 years = 10)
    - Death year: 0-25 points (exact match = 25, ±2 years = 20, ±5 years = 10)
    - Threshold: 60 points minimum for match
    - Confidence: high (90+), medium (75-89), low (60-74)

AUTHOR:
    David Edward England, PhD
    https://orcid.org/0009-0001-2095-6646

HELP;
}