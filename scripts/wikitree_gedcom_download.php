<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree GEDCOM Downloader
 * 
 * Downloads GEDCOM files from WikiTree with optional depth parameter
 * Saves to GEDs/ directory for processing with other scripts
 * 
 * Usage:
 *   php wikitree_gedcom_download.php --profile Welch-185 --depth 2
 *   php wikitree_gedcom_download.php --profile Welch-185 --depth 3 --output GEDs/welch-family.ged
 * 
 * @author David England
 * @date 2026-03-04
 */

// Parse command line arguments
$options = getopt('', ['profile:', 'depth:', 'output:', 'verbose', 'help']);

if (isset($options['help']) || empty($options['profile'])) {
    showHelp();
    exit(0);
}

$profileId = $options['profile'];
$depth = (int)($options['depth'] ?? 2);
$verbose = isset($options['verbose']);
$gedDir = __DIR__ . '/../GEDs';
$outputFile = $options['output'] ?? "$gedDir/{$profileId}-depth{$depth}.ged";

// Ensure GEDs directory exists
if (!is_dir($gedDir)) {
    mkdir($gedDir, 0755, true);
    echo "Created directory: $gedDir\n";
}

echo "=== WikiTree GEDCOM Downloader ===\n\n";
echo "Profile: $profileId\n";
echo "Depth: $depth\n";
echo "Output: $outputFile\n\n";

// WikiTree GEDCOM export URL
$url = "https://www.wikitree.com/genealogy/" . urlencode($profileId) . 
       "?action=export&format=gedcom&download=1&depth=$depth";

if ($verbose) {
    echo "URL: $url\n\n";
}

echo "Downloading GEDCOM from WikiTree...\n";

// Initialize cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'WikiTree-GEDCOM-Downloader/1.0 (genealogy sync tool)');
curl_setopt($ch, CURLOPT_TIMEOUT, 120); // 2 minutes for large files
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

// Execute request
$gedcom = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);

// Check for errors
if ($gedcom === false) {
    fwrite(STDERR, "Error: cURL failed - $curlError\n");
    exit(1);
}

if ($httpCode !== 200) {
    fwrite(STDERR, "Error: HTTP $httpCode\n");
    fwrite(STDERR, "Response: " . substr($gedcom, 0, 500) . "\n");
    exit(1);
}

// Validate GEDCOM content
if (strpos($gedcom, '0 HEAD') === false) {
    fwrite(STDERR, "Error: Downloaded content is not valid GEDCOM\n");
    fwrite(STDERR, "Content preview: " . substr($gedcom, 0, 200) . "\n");
    exit(1);
}

// Save GEDCOM file
$bytesWritten = file_put_contents($outputFile, $gedcom);

if ($bytesWritten === false) {
    fwrite(STDERR, "Error: Failed to write GEDCOM file\n");
    exit(1);
}

echo "\n=== Download Complete ===\n";
echo "Size: " . formatBytes($bytesWritten) . "\n";
echo "Saved to: $outputFile\n\n";

// Parse and display summary
$stats = parseGedcomStats($gedcom);
echo "GEDCOM Summary:\n";
echo "  Individuals: {$stats['individuals']}\n";
echo "  Families: {$stats['families']}\n";
if ($stats['sources'] > 0) {
    echo "  Sources: {$stats['sources']}\n";
}
if ($stats['notes'] > 0) {
    echo "  Notes: {$stats['notes']}\n";
}

echo "\nNext Steps:\n";
echo "  1. Parse with enhanced sync:\n";
echo "     php scripts/gedcom_to_sync_data.php --input \"$outputFile\"\n\n";
echo "  2. Run three-way sync:\n";
echo "     php scripts/genealogy_sync_enhanced.php --wikitree-gedcom \"$outputFile\" --familysearch \"data/familysearch.json\"\n\n";

/* ========== FUNCTIONS ========== */

/**
 * Parse GEDCOM content and extract statistics
 */
function parseGedcomStats(string $gedcom): array {
    $lines = explode("\n", $gedcom);
    
    $stats = [
        'individuals' => 0,
        'families' => 0,
        'sources' => 0,
        'notes' => 0
    ];
    
    foreach ($lines as $line) {
        if (preg_match('/^0 (@I\d+@) INDI/', $line)) {
            $stats['individuals']++;
        } elseif (preg_match('/^0 (@F\d+@) FAM/', $line)) {
            $stats['families']++;
        } elseif (preg_match('/^0 (@S\d+@) SOUR/', $line)) {
            $stats['sources']++;
        } elseif (preg_match('/^0 (@N\d+@) NOTE/', $line)) {
            $stats['notes']++;
        }
    }
    
    return $stats;
}

/**
 * Format bytes as human-readable
 */
function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    
    return round($bytes, 2) . ' ' . $units[$i];
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
WikiTree GEDCOM Downloader

Downloads GEDCOM files from WikiTree for use in sync tools.

Usage:
  php wikitree_gedcom_download.php --profile PROFILE [OPTIONS]

Required Options:
  --profile ID      WikiTree profile ID (e.g., Welch-185)

Optional Options:
  --depth N         Number of generations to include (default: 2)
                    1 = profile only
                    2 = profile + parents/children/spouses
                    3 = profile + grandparents/grandchildren
                    4+ = extended family
  --output FILE     Output GEDCOM file path (default: GEDs/PROFILE-depthN.ged)
  --verbose         Show detailed processing information
  --help            Show this help message

Examples:
  # Download Nicholas Welch with 2 generations
  php wikitree_gedcom_download.php --profile Welch-185 --depth 2
  
  # Download extended family
  php wikitree_gedcom_download.php --profile Welch-185 --depth 4 --verbose
  
  # Custom output location
  php wikitree_gedcom_download.php --profile Welch-185 --depth 3 --output data/welch.ged

Output:
  - GEDCOM file with all profile data
  - Includes WikiTree URLs in WWW fields
  - Includes FindAGrave memorial IDs (if present)
  - Includes birth/death dates and locations
  - Includes family relationships with mother attribution
  
  The GEDCOM file can then be used with:
  - gedcom_to_sync_data.php (parse to JSON)
  - genealogy_sync_enhanced.php (three-way sync)
  - gedcom_to_biography_enhanced.php (generate bios)

HELP;
}
