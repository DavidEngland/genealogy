<?php
/**
 * Convert FamilySearch CSV search results to WikiTree sources Markdown
 * Usage: php csv_to_wikitree_sources.php --in search-results/Pigg-83.csv --out sources/Pigg-83.md
 */

$options = getopt('', ['in:', 'out:']);

if (!isset($options['in']) || !isset($options['out'])) {
    die("Usage: php csv_to_wikitree_sources.php --in <input.csv> --out <output.md>\n");
}

$inFile = $options['in'];
$outFile = $options['out'];

if (!file_exists($inFile)) {
    die("Error: Input file '$inFile' not found.\n");
}

// Parse CSV
$records = [];
$handle = fopen($inFile, 'r');
$header = null;
$seen = [];

while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
    if ($header === null) {
        $header = $row;
        continue;
    }
    
    // Skip rows that don't have the same column count as header
    if (count($row) !== count($header)) {
        continue;
    }
    
    $record = array_combine($header, $row);
    
    // Extract ARK ID and skip duplicates
    preg_match('/ark:\/61903\/1:1:([A-Z0-9-]+)/', $record['arkId'], $matches);
    if (!$matches) continue;
    
    $arkId = $matches[1];
    if (isset($seen[$arkId])) continue;
    $seen[$arkId] = true;
    
    $records[] = $record;
}
fclose($handle);

// Generate Markdown
$markdown = "== Sources ==\n";
$markdown .= "<references />\n\n";

foreach ($records as $rec) {
    $sourceTitle = trim($rec['collectionName']);
    $arkId = preg_match('/ark:\/61903\/1:1:([A-Z0-9-]+)/', $rec['arkId'], $m) ? $m[1] : '';
    $arkUrl = "https://www.familysearch.org/ark:/61903/1:1:$arkId";
    
    // Build citation
    $citation = "* \"$sourceTitle,\" database, [$arkUrl FamilySearch]";
    
    // Add event date if available
    $eventDate = $rec['marriageLikeDate'] ?: $rec['birthLikeDate'] ?: $rec['deathLikeDate'] ?: '';
    $eventPlace = $rec['marriageLikePlaceText'] ?: $rec['birthLikePlaceText'] ?: $rec['deathLikePlaceText'] ?: '';
    
    $details = [];
    if ($eventDate) $details[] = "Entry for " . trim($rec['fullName']) . ", $eventDate";
    if ($eventPlace) $details[] = $eventPlace;
    
    if ($details) {
        $citation .= ", (accessed " . date('d M Y') . "), " . implode(", ", $details) . ".";
    } else {
        $citation .= ", (accessed " . date('d M Y') . ").";
    }
    
    $markdown .= $citation . "\n\n";
}

// Write output
file_put_contents($outFile, $markdown);
echo "Generated: $outFile\n";
echo "Records processed: " . count($records) . "\n";
?>
