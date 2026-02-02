<?php
/**
 * WikiTree JSON to Markdown Converter
 * 
 * Reads stored WikiTree API JSON files and converts to markdown format.
 * Supports filtering by section (parents, siblings, spouses, children, all).
 * 
 * Usage:
 *   php wikitree_convert.php --profile Lewis-8883
 *   php wikitree_convert.php --profile Lewis-8883 --section parents
 *   php wikitree_convert.php --profile Lewis-8883 --output Lewis-8883-family.md
 * 
 * @author David England
 * @date 2026-02-01
 */

require_once __DIR__ . '/wikitree_api_client.php';

// Parse command line arguments
$options = getopt('', ['profile:', 'output:', 'section:', 'help']);

// Show help if requested or no arguments
if (isset($options['help']) || empty($options['profile'])) {
    showHelp();
    exit(0);
}

// Configuration
$profileId = $options['profile'];
$outputFile = $options['output'] ?? null;
$section = $options['section'] ?? 'all';
$profilesDir = __DIR__ . '/../profiles';

// Validate WikiTree ID format
if (!WikiTreeAPI::validateID($profileId)) {
    fwrite(STDERR, "Error: Invalid WikiTree ID format. Expected format: Surname-Number (e.g., Lewis-8883)\n");
    exit(1);
}

// Validate section parameter
$validSections = ['parents', 'siblings', 'spouses', 'children', 'all'];
if (!in_array($section, $validSections)) {
    fwrite(STDERR, "Error: Invalid section. Must be one of: " . implode(', ', $validSections) . "\n");
    exit(1);
}

// Load profile JSON
$profile = loadProfile($profileId, $profilesDir);
if ($profile === false) {
    fwrite(STDERR, "Error: Profile $profileId not found. Run wikitree_fetch.php first.\n");
    exit(1);
}

// Generate markdown
$markdown = convertToMarkdown($profile, $section);

// Output results
if ($outputFile) {
    if (@file_put_contents($outputFile, $markdown) === false) {
        fwrite(STDERR, "Error: Failed to write to $outputFile\n");
        exit(1);
    }
    echo "Written to $outputFile\n";
} else {
    echo "\n" . str_repeat('=', 60) . "\n";
    echo $markdown;
}

exit(0);

/**
 * Load profile from JSON file
 * 
 * @param string $wikitreeId WikiTree ID
 * @param string $baseDir Base profiles directory
 * @return array|false Profile data or false if not found
 */
function loadProfile(string $wikitreeId, string $baseDir) {
    $firstLetter = strtoupper(substr($wikitreeId, 0, 1));
    $filePath = "$baseDir/$firstLetter/$wikitreeId.json";
    
    if (!file_exists($filePath)) {
        return false;
    }
    
    $json = @file_get_contents($filePath);
    if ($json === false) {
        return false;
    }
    
    $data = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return false;
    }
    
    return $data['profile'] ?? false;
}

/**
 * Convert profile to markdown format
 * 
 * @param array $profile Profile data
 * @param string $section Section to output
 * @return string Markdown output
 */
function convertToMarkdown(array $profile, string $section): string {
    $output = [];
    
    // Person information
    if ($section === 'all') {
        $output[] = convertPerson($profile);
    }
    
    // Parents
    if ($section === 'parents' || $section === 'all') {
        $parentsMarkdown = convertParents($profile);
        if ($parentsMarkdown) {
            $output[] = $parentsMarkdown;
        }
    }
    
    // Siblings
    if ($section === 'siblings' || $section === 'all') {
        $siblingsMarkdown = convertSiblings($profile);
        if ($siblingsMarkdown) {
            $output[] = $siblingsMarkdown;
        }
    }
    
    // Spouses
    if ($section === 'spouses' || $section === 'all') {
        $spousesMarkdown = convertSpouses($profile);
        if ($spousesMarkdown) {
            $output[] = $spousesMarkdown;
        }
    }
    
    // Children
    if ($section === 'children' || $section === 'all') {
        $childrenMarkdown = convertChildren($profile);
        if ($childrenMarkdown) {
            $output[] = $childrenMarkdown;
        }
    }
    
    return implode("\n\n", array_filter($output));
}

/**
 * Convert person information to markdown
 * 
 * @param array $profile Profile data
 * @return string Markdown output
 */
function convertPerson(array $profile): string {
    $name = formatPersonName($profile);
    $wikitreeId = $profile['Name'] ?? 'Unknown';
    
    $lines = [];
    $lines[] = "# $name";
    $lines[] = "**WikiTree ID:** [[$wikitreeId]]";
    
    // Birth information
    if (!empty($profile['BirthDate']) || !empty($profile['BirthLocation'])) {
        $birth = [];
        if (!empty($profile['BirthDate'])) {
            $birth[] = formatDate($profile['BirthDate']);
        }
        if (!empty($profile['BirthLocation'])) {
            $birth[] = $profile['BirthLocation'];
        }
        $lines[] = "**Born:** " . implode(', ', $birth);
    }
    
    // Death information
    if (!empty($profile['DeathDate']) || !empty($profile['DeathLocation'])) {
        $death = [];
        if (!empty($profile['DeathDate'])) {
            $death[] = formatDate($profile['DeathDate']);
        }
        if (!empty($profile['DeathLocation'])) {
            $death[] = $profile['DeathLocation'];
        }
        $lines[] = "**Died:** " . implode(', ', $death);
    }
    
    return implode("\n", $lines);
}

/**
 * Convert parents to markdown
 * 
 * @param array $profile Profile data
 * @return string|null Markdown output or null if no parents
 */
function convertParents(array $profile): ?string {
    $parents = $profile['Parents'] ?? [];
    if (empty($parents)) {
        return null;
    }
    
    $lines = [];
    $lines[] = "## Parents";
    
    foreach ($parents as $parent) {
        $name = formatPersonName($parent);
        $wikitreeId = $parent['Name'] ?? null;
        
        if ($wikitreeId) {
            $lines[] = "- [[$wikitreeId|$name]]";
        } else {
            $lines[] = "- $name";
        }
    }
    
    return implode("\n", $lines);
}

/**
 * Convert siblings to markdown
 * 
 * @param array $profile Profile data
 * @return string|null Markdown output or null if no siblings
 */
function convertSiblings(array $profile): ?string {
    $siblings = $profile['Siblings'] ?? [];
    if (empty($siblings)) {
        return null;
    }
    
    $lines = [];
    $lines[] = "## Siblings";
    
    foreach ($siblings as $sibling) {
        $name = formatPersonName($sibling);
        $wikitreeId = $sibling['Name'] ?? null;
        
        if ($wikitreeId) {
            $lines[] = "- [[$wikitreeId|$name]]";
        } else {
            $lines[] = "- $name";
        }
    }
    
    return implode("\n", $lines);
}

/**
 * Convert spouses to markdown
 * 
 * @param array $profile Profile data
 * @return string|null Markdown output or null if no spouses
 */
function convertSpouses(array $profile): ?string {
    $spouses = $profile['Spouses'] ?? [];
    if (empty($spouses)) {
        return null;
    }
    
    $lines = [];
    $lines[] = "## Spouses";
    
    foreach ($spouses as $spouse) {
        $name = formatPersonName($spouse);
        $wikitreeId = $spouse['Name'] ?? null;
        
        $spouseLine = $wikitreeId ? "[[$wikitreeId|$name]]" : $name;
        
        // Add marriage information if available
        $marriageInfo = [];
        if (!empty($spouse['marriage_date'])) {
            $marriageInfo[] = formatDate($spouse['marriage_date']);
        }
        if (!empty($spouse['marriage_location'])) {
            $marriageInfo[] = $spouse['marriage_location'];
        }
        
        if (!empty($marriageInfo)) {
            $spouseLine .= " (m. " . implode(', ', $marriageInfo) . ")";
        }
        
        $lines[] = "- $spouseLine";
    }
    
    return implode("\n", $lines);
}

/**
 * Convert children to markdown
 * 
 * @param array $profile Profile data
 * @return string|null Markdown output or null if no children
 */
function convertChildren(array $profile): ?string {
    $children = $profile['Children'] ?? [];
    if (empty($children)) {
        return null;
    }
    
    $lines = [];
    $lines[] = "## Children";
    
    foreach ($children as $child) {
        $name = formatPersonName($child);
        $wikitreeId = $child['Name'] ?? null;
        
        if ($wikitreeId) {
            $lines[] = "- [[$wikitreeId|$name]]";
        } else {
            $lines[] = "- $name";
        }
    }
    
    return implode("\n", $lines);
}

/**
 * Format person name from profile data
 * 
 * @param array $person Person data
 * @return string Formatted name
 */
function formatPersonName(array $person): string {
    $parts = [];
    
    if (!empty($person['FirstName'])) {
        $parts[] = $person['FirstName'];
    }
    
    if (!empty($person['MiddleName'])) {
        $parts[] = $person['MiddleName'];
    }
    
    // Use LastNameCurrent if available, otherwise LastNameAtBirth
    if (!empty($person['LastNameCurrent'])) {
        $parts[] = $person['LastNameCurrent'];
    } elseif (!empty($person['LastNameAtBirth'])) {
        $parts[] = $person['LastNameAtBirth'];
    }
    
    if (empty($parts) && !empty($person['RealName'])) {
        return $person['RealName'];
    }
    
    return !empty($parts) ? implode(' ', $parts) : 'Unknown';
}

/**
 * Format date from API format (YYYY-MM-DD or YYYY-MM-00 or YYYY-00-00)
 * 
 * @param string $date Date string
 * @return string Formatted date
 */
function formatDate(string $date): string {
    // Handle "0000-00-00" or empty dates
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    
    // Replace -00 with empty for partial dates
    $date = preg_replace('/-00/', '', $date);
    
    return $date;
}

/**
 * Convert biography to markdown (future extensibility)
 * 
 * @param array $profile Profile data
 * @return string|null Biography markdown or null
 */
function convertBio(array $profile): ?string {
    // Placeholder for future implementation
    // Will extract and format Bio field when bio fetching is enabled
    return null;
}

/**
 * Convert photos to markdown (future extensibility)
 * 
 * @param array $profile Profile data
 * @return string|null Photos markdown or null
 */
function convertPhotos(array $profile): ?string {
    // Placeholder for future implementation
    // Will extract and format Photo/PhotoData fields when photo fetching is enabled
    return null;
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
WikiTree JSON to Markdown Converter

Reads stored WikiTree API JSON files and converts to markdown format.

USAGE:
    php wikitree_convert.php --profile WIKITREE-ID [OPTIONS]

OPTIONS:
    --profile ID      WikiTree ID to convert (required, e.g., Lewis-8883)
    --output FILE     Output file path (optional, outputs to STDOUT if omitted)
    --section NAME    Section to output: parents, siblings, spouses, children, all (default: all)
    --help            Show this help message

EXAMPLES:
    # Convert to markdown (output to STDOUT)
    php wikitree_convert.php --profile Lewis-8883
    
    # Convert to file
    php wikitree_convert.php --profile Lewis-8883 --output Lewis-8883-family.md
    
    # Convert only parents section
    php wikitree_convert.php --profile Lewis-8883 --section parents

INPUT:
    Reads from: profiles/{FirstLetter}/{WikiTreeID}.json
    
OUTPUT:
    Markdown formatted family information

HELP;
}
