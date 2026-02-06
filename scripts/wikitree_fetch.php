<?php
/**
 * WikiTree Profile Fetcher (API-based)
 * 
 * Fetches WikiTree profiles via the WikiTree API and stores as JSON.
 * Supports single profile, relatives, or ancestors mode.
 * 
 * Usage:
 *   php wikitree_fetch.php --profile Lewis-8883
 *   php wikitree_fetch.php --profile Lewis-8883 --relatives
 *   php wikitree_fetch.php --profile Lewis-8883 --relatives --verbose
 *   php wikitree_fetch.php --profile Brewer-1601 --ancestors --depth 5
 * 
 * @author David England
 * @date 2026-02-01
 */

require_once __DIR__ . '/wikitree_api_client.php';

// Parse command line arguments
$options = getopt('', ['profile:', 'relatives', 'ancestors', 'depth:', 'verbose', 'help']);

// Show help if requested or no arguments
if (isset($options['help']) || empty($options['profile'])) {
    showHelp();
    exit(0);
}

// Configuration
$profileId = $options['profile'];
$fetchRelatives = isset($options['relatives']);
$fetchAncestors = isset($options['ancestors']);
$depth = 5;
$depthOption = $options['depth'] ?? null;
$verbose = isset($options['verbose']);
$logFile = __DIR__ . '/../logs/wikitree_api_errors.log';
$profilesDir = __DIR__ . '/../profiles';

// Validate mode selection
if ($fetchRelatives && $fetchAncestors) {
    fwrite(STDERR, "Error: Please choose either --relatives or --ancestors, not both.\n");
    exit(1);
}

if ($fetchAncestors) {
    if ($depthOption !== null) {
        $depth = (int)$depthOption;
        if ($depth < 1) {
            fwrite(STDERR, "Error: Invalid depth. Depth must be a positive integer.\n");
            exit(1);
        }
    }
}

// Validate WikiTree ID format
if (!WikiTreeAPI::validateID($profileId)) {
    fwrite(STDERR, "Error: Invalid WikiTree ID format. Expected format: Surname-Number (e.g., Lewis-8883)\n");
    exit(1);
}

// Initialize API client
$api = new WikiTreeAPI($verbose, $logFile);

// Fetch profile data
if ($fetchRelatives) {
    echo "Fetching profile $profileId with relatives...\n";
    $data = $api->fetchRelatives($profileId);
} elseif ($fetchAncestors) {
    echo "Fetching profile $profileId with ancestors (depth $depth)...\n";
    $data = $api->fetchAncestors($profileId, $depth);
} else {
    echo "Fetching profile $profileId...\n";
    $data = $api->fetchProfile($profileId);
}

// Check for errors
if ($data === false) {
    fwrite(STDERR, "Error: Failed to fetch profile $profileId\n");
    exit(1);
}

// Save main profile
if ($fetchRelatives) {
    // In relatives mode, the response has items array with person nested inside
    $itemWrapper = $data['items'][0] ?? null;
    if ($itemWrapper && isset($itemWrapper['person'])) {
        $mainProfile = $itemWrapper['person'];
        saveProfile($mainProfile, $profilesDir);
        echo "Saved profile: {$mainProfile['Name']}\n";
        
        // Save each relative as separate JSON file
        $relativesSaved = 0;
        
        // Process parents
        if (!empty($mainProfile['Parents'])) {
            foreach ($mainProfile['Parents'] as $parent) {
                if (saveProfile($parent, $profilesDir)) {
                    $relativesSaved++;
                }
            }
        }
        
        // Process siblings
        if (!empty($mainProfile['Siblings'])) {
            foreach ($mainProfile['Siblings'] as $sibling) {
                if (saveProfile($sibling, $profilesDir)) {
                    $relativesSaved++;
                }
            }
        }
        
        // Process spouses
        if (!empty($mainProfile['Spouses'])) {
            foreach ($mainProfile['Spouses'] as $spouse) {
                if (saveProfile($spouse, $profilesDir)) {
                    $relativesSaved++;
                }
            }
        }
        
        // Process children
        if (!empty($mainProfile['Children'])) {
            foreach ($mainProfile['Children'] as $child) {
                if (saveProfile($child, $profilesDir)) {
                    $relativesSaved++;
                }
            }
        }
        
        echo "Saved $relativesSaved relative profile(s)\n";
    }
} elseif ($fetchAncestors) {
    $mainProfile = extractMainProfile($data);
    if ($mainProfile && saveProfile($mainProfile, $profilesDir)) {
        echo "Saved profile: {$mainProfile['Name']}\n";
    }

    $ancestorProfiles = extractAncestorProfiles($data);
    $ancestorsSaved = 0;
    foreach ($ancestorProfiles as $ancestor) {
        if (saveProfile($ancestor, $profilesDir)) {
            $ancestorsSaved++;
        }
    }

    if (saveAncestorsResponse($data, $profilesDir, $profileId, $depth)) {
        echo "Saved ancestors response for $profileId (depth $depth)\n";
    } else {
        fwrite(STDERR, "Warning: Failed to save ancestors response\n");
    }

    echo "Saved $ancestorsSaved ancestor profile(s)\n";
} else {
    // Single profile mode
    $profile = $data['profile'];
    if (saveProfile($profile, $profilesDir)) {
        echo "Saved profile: {$profile['Name']}\n";
    } else {
        fwrite(STDERR, "Error: Failed to save profile\n");
        exit(1);
    }
}

echo "Done.\n";
exit(0);

/**
 * Save profile data as JSON file
 * 
 * @param array $profile Profile data from API
 * @param string $baseDir Base profiles directory
 * @return bool True on success, false on failure
 */
function saveProfile(array $profile, string $baseDir): bool {
    // Extract WikiTree ID
    $wikitreeId = $profile['Name'] ?? null;
    if (!$wikitreeId) {
        fwrite(STDERR, "Warning: Profile missing Name field, skipping\n");
        return false;
    }
    
    // Get first letter for directory
    $firstLetter = strtoupper(substr($wikitreeId, 0, 1));
    $letterDir = "$baseDir/$firstLetter";
    
    // Create directory if needed
    if (!is_dir($letterDir)) {
        if (!@mkdir($letterDir, 0755, true)) {
            fwrite(STDERR, "Error: Failed to create directory $letterDir\n");
            return false;
        }
    }
    
    // Build full file path
    $filePath = "$letterDir/$wikitreeId.json";
    
    // Wrap with metadata
    $output = [
        'metadata' => [
            'wikitreeId' => $wikitreeId,
            'source' => 'WikiTree API',
            'appId' => 'genealogy_parser_v1',
            'fetched' => date('Y-m-d H:i:s'),
            'timestamp' => time()
        ],
        'profile' => $profile
    ];
    
    // Write JSON file
    $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (@file_put_contents($filePath, $json) === false) {
        fwrite(STDERR, "Error: Failed to write file $filePath\n");
        return false;
    }
    
    return true;
}

/**
 * Save ancestors response as JSON file
 *
 * @param array $data Raw ancestors response data
 * @param string $baseDir Base profiles directory
 * @param string $wikitreeId WikiTree ID used for the request
 * @param int $depth Ancestor depth
 * @return bool True on success, false on failure
 */
function saveAncestorsResponse(array $data, string $baseDir, string $wikitreeId, int $depth): bool {
    $ancestorsDir = "$baseDir/ancestors";

    if (!is_dir($ancestorsDir)) {
        if (!@mkdir($ancestorsDir, 0755, true)) {
            fwrite(STDERR, "Error: Failed to create directory $ancestorsDir\n");
            return false;
        }
    }

    $filePath = "$ancestorsDir/{$wikitreeId}-depth{$depth}.json";

    $output = [
        'metadata' => [
            'wikitreeId' => $wikitreeId,
            'source' => 'WikiTree API',
            'appId' => 'genealogy_parser_v1',
            'fetched' => date('Y-m-d H:i:s'),
            'timestamp' => time(),
            'depth' => $depth
        ],
        'ancestors' => $data
    ];

    $json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (@file_put_contents($filePath, $json) === false) {
        fwrite(STDERR, "Error: Failed to write file $filePath\n");
        return false;
    }

    return true;
}

/**
 * Extract main profile from an API response
 *
 * @param array $data API response data
 * @return array|null
 */
function extractMainProfile(array $data): ?array {
    if (isset($data['profile']) && is_array($data['profile'])) {
        return $data['profile'];
    }

    if (isset($data['person']) && is_array($data['person'])) {
        return $data['person'];
    }

    if (isset($data['items'][0]['person']) && is_array($data['items'][0]['person'])) {
        return $data['items'][0]['person'];
    }

    if (isset($data['items'][0]) && is_array($data['items'][0]) && isset($data['items'][0]['Name'])) {
        return $data['items'][0];
    }

    return null;
}

/**
 * Extract ancestor profiles from an API response
 *
 * @param array $data API response data
 * @return array
 */
function extractAncestorProfiles(array $data): array {
    $profiles = [];
    collectProfilesRecursive($data, $profiles);
    return array_values($profiles);
}

/**
 * Recursively collect profile-like arrays
 *
 * @param mixed $value
 * @param array $profiles
 */
function collectProfilesRecursive($value, array &$profiles): void {
    if (!is_array($value)) {
        return;
    }

    if (isset($value['Name']) && is_string($value['Name'])) {
        $hasProfileFields = isset($value['Id']) || isset($value['FirstName']) || isset($value['LastNameAtBirth']) || isset($value['BirthDate']);
        if ($hasProfileFields) {
            $profiles[$value['Name']] = $value;
        }
    }

    foreach ($value as $item) {
        if (is_array($item)) {
            collectProfilesRecursive($item, $profiles);
        }
    }
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
WikiTree Profile Fetcher (API-based)

Fetches WikiTree profiles via the WikiTree API and stores as JSON files.

USAGE:
    php wikitree_fetch.php --profile WIKITREE-ID [OPTIONS]

OPTIONS:
    --profile ID      WikiTree ID to fetch (required, e.g., Lewis-8883)
    --relatives       Fetch profile with all relatives (parents, siblings, spouses, children)
    --ancestors       Fetch profile with ancestors using getAncestors
    --depth N         Ancestor depth (default: 5)
    --verbose         Enable verbose output and show API debug information
    --help            Show this help message

EXAMPLES:
    # Fetch single profile
    php wikitree_fetch.php --profile Lewis-8883
    
    # Fetch profile with all relatives
    php wikitree_fetch.php --profile Lewis-8883 --relatives
    
    # Fetch with verbose logging
    php wikitree_fetch.php --profile Lewis-8883 --relatives --verbose

    # Fetch ancestors with default depth
    php wikitree_fetch.php --profile Brewer-1601 --ancestors

    # Fetch ancestors with custom depth
    php wikitree_fetch.php --profile Brewer-1601 --ancestors --depth 8

OUTPUT:
    JSON files are stored in: profiles/{FirstLetter}/{WikiTreeID}.json
    Ancestors responses stored in: profiles/ancestors/{WikiTreeID}-depth{N}.json
    Error logs are written to: logs/wikitree_api_errors.log

HELP;
}
