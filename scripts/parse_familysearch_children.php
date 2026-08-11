#!/usr/bin/env php
<?php
/**
 * Parse FamilySearch HTML to extract children data
 * 
 * Extracts fullName, lifespan, and pid (person ID) from FamilySearch HTML export
 * containing children list with data-testid attributes.
 * 
 * Usage:
 *   php parse_familysearch_children.php --input FILE [--output FILE] [--format json|csv]
 */

// Parse command-line arguments
$options = getopt('', ['input:', 'output:', 'format:', 'help']);

if (isset($options['help']) || !isset($options['input'])) {
    showHelp();
    exit(0);
}

$inputFile = $options['input'];
$format = $options['format'] ?? 'json';
$outputFile = $options['output'] ?? null;

if (!file_exists($inputFile)) {
    echo "Error: Input file not found: $inputFile\n";
    exit(1);
}

// Read HTML content
$html = file_get_contents($inputFile);

// Extract children data
$children = parseChildren($html);

echo "Found " . count($children) . " children\n\n";

// Output based on format
if ($format === 'csv') {
    $output = generateCSV($children);
} else {
    $output = json_encode($children, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Display or save
if ($outputFile) {
    file_put_contents($outputFile, $output);
    echo "Saved to: $outputFile\n";
} else {
    echo $output . "\n";
}

/**
 * Parse HTML to extract children data
 */
function parseChildren(string $html): array {
    $children = [];
    
    // Use DOMDocument to parse HTML
    // Convert to HTML-ENTITIES to handle UTF-8 properly
    $dom = new DOMDocument();
    @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($dom);
    
    // Find all child-person list items
    $childItems = $xpath->query('//li[@data-testid="child-person"]');
    
    foreach ($childItems as $childItem) {
        $child = [];
        
        // Extract fullName
        $fullNameNodes = $xpath->query('.//span[@data-testid="fullName"]', $childItem);
        if ($fullNameNodes->length > 0) {
            $child['fullName'] = trim($fullNameNodes->item(0)->textContent);
        }
        
        // Extract lifespan
        $lifespanNodes = $xpath->query('.//span[@data-testid="lifespan"]', $childItem);
        if ($lifespanNodes->length > 0) {
            $child['lifespan'] = trim($lifespanNodes->item(0)->textContent);
            
            // Parse birth and death years
            $years = parseLifespan($child['lifespan']);
            $child['birth_year'] = $years['birth'];
            $child['death_year'] = $years['death'];
        }
        
        // Extract pid (person ID)
        $pidNodes = $xpath->query('.//button[@data-testid="pid"] | .//span[@data-testid="pid"]', $childItem);
        if ($pidNodes->length > 0) {
            $child['pid'] = trim($pidNodes->item(0)->textContent);
        }
        
        // Only add if we found at least a name
        if (!empty($child['fullName'])) {
            $children[] = $child;
        }
    }
    
    return $children;
}

/**
 * Parse lifespan string to extract birth and death years
 */
function parseLifespan(string $lifespan): array {
    $result = ['birth' => null, 'death' => null];
    
    // Handle formats like "1760–1833", "1760-1833", "1760–", "Living", etc.
    // Note: en-dash (–) is U+2013, may appear as multi-byte character
    if (preg_match('/(\d{4})\s*[\x{2013}\x{2014}-]\s*(\d{4})/u', $lifespan, $matches)) {
        // Birth–Death format
        $result['birth'] = (int)$matches[1];
        $result['death'] = (int)$matches[2];
    } elseif (preg_match('/(\d{4})\s*[\x{2013}\x{2014}-]\s*(?:Deceased|Living|$)/u', $lifespan, $matches)) {
        // Birth–Deceased or Birth– format
        $result['birth'] = (int)$matches[1];
    } elseif (preg_match('/^(\d{4})$/', $lifespan, $matches)) {
        // Just a year
        $result['birth'] = (int)$matches[1];
    }
    
    return $result;
}

/**
 * Generate CSV output
 */
function generateCSV(array $children): string {
    $csv = "fullName,lifespan,birth_year,death_year,pid\n";
    
    foreach ($children as $child) {
        $csv .= sprintf(
            '"%s","%s",%s,%s,"%s"' . "\n",
            $child['fullName'] ?? '',
            $child['lifespan'] ?? '',
            $child['birth_year'] ?? '',
            $child['death_year'] ?? '',
            $child['pid'] ?? ''
        );
    }
    
    return $csv;
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
Parse FamilySearch HTML to Extract Children Data

Extracts fullName, lifespan, and pid (person ID) from FamilySearch HTML
containing children list with data-testid attributes.

Usage:
  php parse_familysearch_children.php --input FILE [OPTIONS]

Required Options:
  --input FILE      Path to FamilySearch HTML file

Optional Options:
  --output FILE     Output file (default: stdout)
  --format FORMAT   Output format: json (default) or csv
  --help            Show this help message

Examples:
  # Extract to JSON (stdout)
  php parse_familysearch_children.php --input ElizabethEMooreChildren-NicholasWelchPerFS.html

  # Extract to JSON file
  php parse_familysearch_children.php --input ElizabethEMooreChildren-NicholasWelchPerFS.html --output children.json

  # Extract to CSV
  php parse_familysearch_children.php --input ElizabethEMooreChildren-NicholasWelchPerFS.html --format csv --output children.csv

Output Fields:
  - fullName: Full name of the child
  - lifespan: Birth-death years (e.g., "1760–1833")
  - birth_year: Extracted birth year (integer)
  - death_year: Extracted death year (integer)
  - pid: FamilySearch person ID (e.g., "L84F-S9W")

HELP;
}
