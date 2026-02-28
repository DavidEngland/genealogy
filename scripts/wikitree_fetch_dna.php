<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree DNA Data Fetcher
 * 
 * Fetches DNA test information for a profile and their descendants
 * to help resolve genealogical questions through genetic analysis.
 * 
 * Usage:
 *   php wikitree_fetch_dna.php --profile Welch-185 [OPTIONS]
 * 
 * Options:
 *   --profile ID          WikiTree profile ID (required)
 *   --descendants         Fetch DNA data for all descendants
 *   --depth N            Descendant depth (default: 3)
 *   --output FILE        Output JSON file (default: PROFILE_dna_data.json)
 *   --verbose            Show detailed progress
 *   --help               Show this help message
 * 
 * Example:
 *   php wikitree_fetch_dna.php --profile Welch-185 --descendants --depth 4 --verbose
 * 
 * @author David England
 * @date 2026-02-27
 */

require_once __DIR__ . '/wikitree_api_client.php';

// Parse command-line arguments
$options = getopt('', ['profile:', 'descendants', 'depth:', 'output:', 'verbose', 'help']);

if (isset($options['help']) || !isset($options['profile'])) {
    showUsage();
    exit(0);
}

$profileId = $options['profile'];
$fetchDescendants = isset($options['descendants']);
$depth = isset($options['depth']) ? (int)$options['depth'] : 3;
$outputFile = $options['output'] ?? "{$profileId}_dna_data.json";
$verbose = isset($options['verbose']);

// Validate profile ID
if (!WikiTreeAPI::validateID($profileId)) {
    fwrite(STDERR, "Error: Invalid WikiTree ID format: $profileId\n");
    exit(1);
}

// Initialize API client
$logFile = __DIR__ . '/../logs/wikitree_dna_fetch.log';
$api = new WikiTreeAPI($verbose, $logFile);

echo "WikiTree DNA Data Fetcher\n";
echo "Profile: $profileId\n";
if ($fetchDescendants) {
    echo "Mode: Profile + Descendants (depth $depth)\n";
} else {
    echo "Mode: Profile only\n";
}
echo "Output: $outputFile\n\n";

// DNA-related fields to fetch
$dnaFields = [
    'Id', 'PageId', 'Name', 'FirstName', 'MiddleName', 'LastNameAtBirth',
    'LastNameCurrent', 'BirthDate', 'DeathDate', 'BirthLocation', 'DeathLocation',
    'Gender', 'Father', 'Mother',
    'Privacy', 'Manager'
    // Note: HasDNATest, mtDNA, yDNA fields may not be available via API
    // These are often in DataStatus or require special permissions
];

// Fetch primary profile
echo "Fetching profile $profileId...\n";
$profileData = $api->fetchProfile($profileId, $dnaFields);

if ($profileData === false) {
    fwrite(STDERR, "Error: Failed to fetch profile $profileId\n");
    exit(1);
}

$dnaData = [
    'profile_id' => $profileId,
    'fetched_at' => date('Y-m-d H:i:s'),
    'root_profile' => extractDNAInfo($profileData),
    'descendants' => []
];

// If descendants requested, fetch them
if ($fetchDescendants) {
    echo "Fetching descendants (depth $depth)...\n";
    $descendants = fetchDescendantsRecursive($api, $profileId, $depth, $verbose);
    
    echo "Processing " . count($descendants) . " descendants...\n";
    foreach ($descendants as $descendantId => $descendantData) {
        $dnaData['descendants'][$descendantId] = extractDNAInfo($descendantData);
    }
}

// Save output
echo "\nSaving DNA data to $outputFile...\n";
if (file_put_contents($outputFile, json_encode($dnaData, JSON_PRETTY_PRINT))) {
    echo "Success! DNA data saved.\n";
    
    // Print summary
    printSummary($dnaData);
} else {
    fwrite(STDERR, "Error: Failed to write output file\n");
    exit(1);
}

/**
 * Extract DNA-relevant information from profile data
 */
function extractDNAInfo(array $profile): array {
    $info = [
        'wikitree_id' => $profile['Name'] ?? null,
        'full_name' => formatName($profile),
        'birth_date' => $profile['BirthDate'] ?? null,
        'death_date' => $profile['DeathDate'] ?? null,
        'gender' => $profile['Gender'] ?? null,
        'father_id' => $profile['Father'] ?? null,
        'mother_id' => $profile['Mother'] ?? null,
        'privacy_level' => $profile['Privacy'] ?? null,
        
        // DNA-specific fields (may not be available)
        'has_dna_test' => null,
        'mtdna_haplogroup' => null,
        'ydna_haplogroup' => null,
        'tests_taken' => null
    ];
    
    // Check for DataStatus DNA information
    if (isset($profile['DataStatus']['DNA'])) {
        $info['dna_status'] = $profile['DataStatus']['DNA'];
    }
    
    // Check profile Bio for DNA mentions (requires Biography field)
    if (isset($profile['Bio'])) {
        $info['bio_mentions_dna'] = (
            stripos($profile['Bio'], 'dna') !== false ||
            stripos($profile['Bio'], 'y-dna') !== false ||
            stripos($profile['Bio'], 'mtdna') !== false ||
            stripos($profile['Bio'], 'autosomal') !== false ||
            stripos($profile['Bio'], 'gedmatch') !== false
        );
    }
    
    return $info;
}

/**
 * Recursively fetch descendants
 */
function fetchDescendantsRecursive(WikiTreeAPI $api, string $profileId, int $depth, bool $verbose, int $currentDepth = 1): array {
    if ($currentDepth > $depth) {
        return [];
    }
    
    $descendants = [];
    
    // Fetch relatives to get children
    $relatives = $api->fetchRelatives($profileId, ['Children']);
    
    if ($relatives === false || !isset($relatives['items'])) {
        return [];
    }
    
    $profile = $relatives['items'][0]['person'] ?? null;
    $children = $profile['Children'] ?? [];
    
    if ($verbose) {
        echo "  Depth $currentDepth: Found " . count($children) . " children of $profileId\n";
    }
    
    foreach ($children as $child) {
        $childId = $child['Name'] ?? null;
        if (!$childId) continue;
        
        $descendants[$childId] = $child;
        
        // Recursively fetch this child's descendants
        if ($currentDepth < $depth) {
            $grandchildren = fetchDescendantsRecursive($api, $childId, $depth, $verbose, $currentDepth + 1);
            $descendants = array_merge($descendants, $grandchildren);
        }
    }
    
    return $descendants;
}

/**
 * Format person name
 */
function formatName(array $profile): string {
    $parts = [];
    
    if (!empty($profile['FirstName'])) {
        $parts[] = $profile['FirstName'];
    }
    if (!empty($profile['MiddleName'])) {
        $parts[] = $profile['MiddleName'];
    }
    if (!empty($profile['LastNameAtBirth'])) {
        $parts[] = $profile['LastNameAtBirth'];
    }
    
    return implode(' ', $parts) ?: ($profile['Name'] ?? 'Unknown');
}

/**
 * Print summary statistics
 */
function printSummary(array $dnaData): void {
    echo "\n=== DNA Data Summary ===\n";
    echo "Root Profile: {$dnaData['root_profile']['wikitree_id']} - {$dnaData['root_profile']['full_name']}\n";
    echo "Total Descendants: " . count($dnaData['descendants']) . "\n";
    
    $byGender = ['M' => 0, 'F' => 0, 'U' => 0];
    $living = 0;
    $dnaStatusCount = 0;
    
    foreach ($dnaData['descendants'] as $desc) {
        $gender = $desc['gender'] ?? 'U';
        $byGender[$gender] = ($byGender[$gender] ?? 0) + 1;
        
        if (empty($desc['death_date'])) {
            $living++;
        }
        
        if (!empty($desc['dna_status'])) {
            $dnaStatusCount++;
        }
    }
    
    echo "\nGender Distribution:\n";
    echo "  Male: {$byGender['M']}\n";
    echo "  Female: {$byGender['F']}\n";
    echo "  Unknown: {$byGender['U']}\n";
    
    echo "\nPotentially Living: $living\n";
    echo "Profiles with DNA Status: $dnaStatusCount\n";
    
    echo "\n=== Next Steps ===\n";
    echo "1. Review $outputFile for detailed DNA information\n";
    echo "2. Contact WikiTree managers of descendants for DNA testing\n";
    echo "3. Check WikiTree profiles directly for DNA test information in biography\n";
    echo "4. Visit https://www.wikitree.com/wiki/$profileId for DNA descendants tree\n";
}

/**
 * Show usage information
 */
function showUsage(): void {
    echo "WikiTree DNA Data Fetcher\n\n";
    echo "Usage:\n";
    echo "  php wikitree_fetch_dna.php --profile WIKITREE-ID [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --profile ID          WikiTree profile ID (required)\n";
    echo "  --descendants         Fetch DNA data for all descendants\n";
    echo "  --depth N            Descendant depth (default: 3)\n";
    echo "  --output FILE        Output JSON file (default: PROFILE_dna_data.json)\n";
    echo "  --verbose            Show detailed progress\n";
    echo "  --help               Show this help message\n\n";
    echo "Examples:\n";
    echo "  # Fetch DNA data for Nicholas Welch only\n";
    echo "  php wikitree_fetch_dna.php --profile Welch-185\n\n";
    echo "  # Fetch Nicholas Welch and 4 generations of descendants\n";
    echo "  php wikitree_fetch_dna.php --profile Welch-185 --descendants --depth 4 --verbose\n\n";
    echo "  # Fetch and save to specific file\n";
    echo "  php wikitree_fetch_dna.php --profile Welch-185 --descendants --output welch_dna_analysis.json\n\n";
}
