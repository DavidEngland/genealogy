<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * FamilySearch Data Parser
 * 
 * Parses FamilySearch family group sheets (from OCR/copy-paste) into structured JSON
 * Handles common OCR errors and formatting inconsistencies
 * 
 * Usage:
 *   php familysearch_parser.php --input "Major Nicholas Welch 1740-1814 • L6ZC-3Q.md" --output nicolas-welch-family.json
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
$outputFile = $options['output'] ?? str_replace('.md', '.json', basename($inputFile));
$verbose = isset($options['verbose']);

// Validate input file
if (!file_exists($inputFile)) {
    fwrite(STDERR, "Error: Input file not found: $inputFile\n");
    exit(1);
}

// Read and parse file
$content = file_get_contents($inputFile);
$lines = explode("\n", $content);

// Initialize data structure
$familyData = [
    'metadata' => [
        'source' => 'FamilySearch',
        'parsed' => date('c'),
        'sourceFile' => basename($inputFile)
    ],
    'principal' => null,
    'spouse' => null,
    'children' => []
];

$currentPerson = null;
$lineNum = 0;
$previousLine = null;

foreach ($lines as $line) {
    $lineNum++;
    $line = trim($line);
    
    if (empty($line)) continue;
    
    // Check if this is a spouse marker
    if (strtolower($line) === 'spouse' || preg_match('/^\(?spouse\)?$/i', $line)) {
        if ($verbose) echo "Spouse marker found\n";
        $previousLine = null;
        continue;
    }
    
    // Parse person record - may need previous line for name
    $person = parsePerson($line, $lineNum, $verbose, $previousLine);
    
    if ($person) {
        // First person is principal
        if ($familyData['principal'] === null) {
            $familyData['principal'] = $person;
            if ($verbose) echo "Principal: {$person['name']} ({$person['familysearch_id']})\n";
            $previousLine = null;
            continue;
        }
        
        // Check if spouse or child
        if ($familyData['spouse'] === null && isLikelySpouse($person, $familyData['principal'])) {
            $familyData['spouse'] = $person;
            if ($verbose) echo "Spouse: {$person['name']} ({$person['familysearch_id']})\n";
        } else {
            $familyData['children'][] = $person;
            if ($verbose) echo "Child: {$person['name']} ({$person['familysearch_id']})\n";
        }
        $previousLine = null;
    } else {
        // Line without FS ID might be name for next line
        $previousLine = $line;
    }
}

// Save to JSON
$json = json_encode($familyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents($outputFile, $json);

echo "Successfully parsed FamilySearch data\n";
echo "Principal: {$familyData['principal']['name']}\n";
if ($familyData['spouse']) {
    echo "Spouse: {$familyData['spouse']['name']}\n";
}
echo "Children: " . count($familyData['children']) . "\n";
echo "Output saved to: $outputFile\n";

/**
 * Parse a person record from a line
 */
function parsePerson(string $line, int $lineNum, bool $verbose, ?string $previousLine = null): ?array {
    // Skip spouse markers
    if (preg_match('/^\(?spouse\)?$/i', trim($line))) {
        return null;
    }
    
    // Skip unknown spouse markers
    if (preg_match('/^\(?unknown.*spouse/i', trim($line))) {
        return null;
    }
    
    // Pattern: Name [dates] • FamilySearch-ID
    // Example: Major Nicholas Welch 1740-1814 • L6ZC-3QY
    // Or multi-line: Elizabeth E Moore\n1740-1799 • L75M-7D6
    
    // First, try to extract FamilySearch ID (most reliable anchor)
    $fsId = null;
    if (preg_match('/•\s*([A-Z0-9]{4,5}-[A-Z0-9]{3,4})/i', $line, $matches)) {
        $fsId = strtoupper($matches[1]);
        // Fix common OCR errors
        $fsId = str_replace(['|', 'I', 'О'], ['1', '1', '0'], $fsId);
    }
    
    // If no FamilySearch ID, this might not be a person record
    if (!$fsId) {
        if ($verbose) echo "  Line $lineNum: No FamilySearch ID found, skipping\n";
        return null;
    }
    
    // Extract name (everything before dates or bullet)
    $name = null;
    if (preg_match('/^(.+?)(?:\s+\d{4}|\s+•)/i', $line, $matches)) {
        $name = trim($matches[1]);
        // Fix common OCR errors in names
        $name = fixOCRErrors($name);
    }
    
    // If name is missing or just dates, use previous line if available
    if ((!$name || strlen($name) < 2 || preg_match('/^\d{4}/', $name)) && $previousLine) {
        $name = fixOCRErrors(trim($previousLine));
        if ($verbose) echo "  Using previous line for name: $name\n";
    }
    
    // If still no name, mark as unknown
    if (!$name || strlen($name) < 2 || preg_match('/^\d{4}/', $name)) {
        $name = '[Unknown]';
    }
    
    // Extract dates
    $birthYear = null;
    $deathYear = null;
    $deathStatus = 'unknown';
    
    if (preg_match('/(\d{4})-(\d{4})/', $line, $matches)) {
        $birthYear = (int)$matches[1];
        $deathYear = (int)$matches[2];
        $deathStatus = 'deceased';
    } elseif (preg_match('/(\d{4})-Deceased/i', $line, $matches)) {
        $birthYear = (int)$matches[1];
        $deathStatus = 'deceased';
    } elseif (preg_match('/\b(\d{4})\b/', $line, $matches)) {
        $birthYear = (int)$matches[1];
    }
    
    return [
        'name' => $name,
        'familysearch_id' => $fsId,
        'birth_year' => $birthYear,
        'death_year' => $deathYear,
        'death_status' => $deathStatus,
        'wikitree_id' => null, // To be filled by sync script
        'findagrave_id' => null,
        'source_line' => $lineNum,
        'raw_line' => $line
    ];
}

/**
 * Fix common OCR errors
 */
function fixOCRErrors(string $text): string {
    $fixes = [
        'Kichard' => 'Richard',
        'Hesta' => 'Hester',
        'saran' => 'Sarah',
        'weld' => 'Welch',
        'welsh' => 'Welch'
    ];
    
    foreach ($fixes as $wrong => $right) {
        $text = preg_replace('/\b' . preg_quote($wrong, '/') . '\b/i', $right, $text);
    }
    
    return $text;
}

/**
 * Determine if person is likely a spouse vs child
 * Spouse typically has different surname or is listed right after principal
 */
function isLikelySpouse(array $person, array $principal): bool {
    // Extract surnames
    $principalSurname = extractSurname($principal['name']);
    $personSurname = extractSurname($person['name']);
    
    // Different surname likely indicates spouse
    if ($personSurname && $principalSurname && 
        strcasecmp($personSurname, $principalSurname) !== 0) {
        return true;
    }
    
    // Similar age range (within 10 years)
    if ($person['birth_year'] && $principal['birth_year']) {
        $ageDiff = abs($person['birth_year'] - $principal['birth_year']);
        if ($ageDiff <= 10) {
            return true;
        }
    }
    
    return false;
}

/**
 * Extract surname from full name
 */
function extractSurname(string $fullName): ?string {
    $parts = explode(' ', trim($fullName));
    return end($parts) ?: null;
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
FamilySearch Data Parser

Parses FamilySearch family group sheets into structured JSON.

Usage:
  php familysearch_parser.php --input FILE [--output FILE] [--verbose]

Options:
  --input FILE      Input markdown file from FamilySearch
  --output FILE     Output JSON file (default: input filename with .json extension)
  --verbose         Show detailed parsing information
  --help            Show this help message

Example:
  php familysearch_parser.php --input "Major Nicholas Welch 1740-1814 • L6ZC-3Q.md" --verbose

HELP;
}
