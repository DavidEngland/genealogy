<?php
// CSV to WikiTree Sources Markdown Converter
// Usage: php scripts/csv_to_wikitree_sources.php --in <input.csv> [--out output.md]
//        php scripts/csv_to_wikitree_sources.php <input.csv> [output.md]
// Examples:
//   php scripts/csv_to_wikitree_sources.php --in search-results/search-results-11.csv --out sources/Welch-1878.md
//   php scripts/csv_to_wikitree_sources.php search-results/search-results-11.csv sources/Welch-1878.md

declare(strict_types=1);

function usage(): void {
    fwrite(STDERR, "Usage: php scripts/csv_to_wikitree_sources.php --in <input.csv> [--out output.md] [OPTIONS]\n");
    fwrite(STDERR, "   or: php scripts/csv_to_wikitree_sources.php <input.csv> [output.md] [OPTIONS]\n");
    fwrite(STDERR, "\nOptions:\n");
    fwrite(STDERR, "  --filter-role <role>       Only include rows with roleInRecord matching <role> (case-insensitive)\n");
    fwrite(STDERR, "  --filter-name <name>       Only include rows where fullName matches <name> (case-insensitive)\n");
    fwrite(STDERR, "  --skip-keywords <csv>      Skip rows where collectionName contains any keyword (comma-separated, case-insensitive)\n");
    fwrite(STDERR, "  --only-principal           Only include rows where roleInRecord=Principal\n");
    fwrite(STDERR, "  --exclude-families         Skip rows with relationshipToHead values (family members)\n");
    exit(1);
}

function parseArgs(array $argv): array {
    $input = null;
    $output = null;
    $filters = [
        'role' => null,
        'name' => null,
        'skip_keywords' => [],
        'only_principal' => false,
        'exclude_families' => false,
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if (($arg === '--in' || $arg === '--input') && isset($argv[$i + 1])) {
            $input = $argv[++$i];
        } elseif ($arg === '--out' && isset($argv[$i + 1])) {
            $output = $argv[++$i];
        } elseif ($arg === '--filter-role' && isset($argv[$i + 1])) {
            $filters['role'] = $argv[++$i];
        } elseif ($arg === '--filter-name' && isset($argv[$i + 1])) {
            $filters['name'] = $argv[++$i];
        } elseif ($arg === '--skip-keywords' && isset($argv[$i + 1])) {
            $keywords = $argv[++$i];
            $filters['skip_keywords'] = array_map('trim', explode(',', $keywords));
        } elseif ($arg === '--only-principal') {
            $filters['only_principal'] = true;
        } elseif ($arg === '--exclude-families') {
            $filters['exclude_families'] = true;
        } elseif ($arg !== '' && $arg[0] !== '-') {
            // Positional fallbacks: first non-flag = input, second = output
            if ($input === null) {
                $input = $arg;
            } elseif ($output === null) {
                $output = $arg;
            }
        }
    }
    if ($input === null) {
        usage();
    }
    return ['input' => $input, 'output' => $output, 'filters' => $filters];
}

function openCsv(string $path) {
    $fh = @fopen($path, 'r');
    if (!$fh) {
        fwrite(STDERR, "Error: Unable to open CSV file: {$path}\n");
        exit(2);
    }
    return $fh;
}

function readHeader($fh): array {
    $header = fgetcsv($fh, 0, ',', '"', '\\');
    if (!$header) {
        fwrite(STDERR, "Error: Empty CSV or unable to read header.\n");
        exit(3);
    }
    return $header;
}

function readRowsAssoc($fh, array $header): array {
    $rows = [];
    while (($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
        // Pad or trim row to match header length
        if (count($row) < count($header)) {
            $row = array_pad($row, count($header), '');
        } elseif (count($row) > count($header)) {
            $row = array_slice($row, 0, count($header));
        }
        $assoc = [];
        foreach ($header as $i => $key) {
            $assoc[$key] = $row[$i] ?? '';
        }
        $rows[] = $assoc;
    }
    return $rows;
}

function extractHyperlink(?string $cell): array {
    // Expected format: =HYPERLINK("URL","LABEL")
    $cell = $cell ?? '';
    $cell = trim($cell);
    if ($cell === '') {
        return ['url' => '', 'label' => ''];
    }
    // Strip leading = and spaces
    if ($cell[0] === '=') {
        // Use regex to capture URL and label
        if (preg_match('/HYPERLINK\("([^"]+)",\s*"([^"]+)"\)/i', $cell, $m)) {
            return ['url' => $m[1], 'label' => $m[2]];
        }
    }
    // If not a formula, maybe the cell itself is a URL
    return ['url' => $cell, 'label' => $cell];
}

function pickDateAndPlace(array $r): array {
    // Prefer residence for census-like records
    $date = trim((string)($r['residenceDate'] ?? ''));
    $place = trim((string)($r['residencePlaceText'] ?? ''));
    if ($date !== '' || $place !== '') {
        return [$date, $place];
    }
    // Fallback to marriage-like
    $date = trim((string)($r['marriageLikeDate'] ?? ''));
    $place = trim((string)($r['marriageLikePlaceText'] ?? ''));
    if ($date !== '' || $place !== '') {
        return [$date, $place];
    }
    // Fallback to birth-like
    $date = trim((string)($r['birthLikeDate'] ?? ''));
    $place = trim((string)($r['birthLikePlaceText'] ?? ''));
    if ($date !== '' || $place !== '') {
        return [$date, $place];
    }
    // Fallback to death-like
    $date = trim((string)($r['deathLikeDate'] ?? ''));
    $place = trim((string)($r['deathLikePlaceText'] ?? ''));
    if ($date !== '' || $place !== '') {
        return [$date, $place];
    }
    // Parse otherEvents like TYPE/DATE//PLACE
    $other = trim((string)($r['otherEvents'] ?? ''));
    if ($other !== '' && strpos($other, '/') !== false) {
        $parts = explode('/', $other);
        $type = $parts[0] ?? '';
        $date = $parts[1] ?? '';
        $place = $parts[3] ?? ($parts[2] ?? '');
        $date = trim($date);
        $place = trim($place);
        if ($date !== '' || $place !== '') {
            return [$date, $place];
        }
    }
    return ['', ''];
}

function cleanList(?string $s): string {
    if (!$s) return '';
    $s = trim($s);
    if ($s === '') return '';
    // Split by semicolon and trim items
    $items = array_map(function($x) {
        return trim($x);
    }, preg_split('/\s*;\s*/', $s));
    // Remove empties and duplicates
    $items = array_values(array_unique(array_filter($items, fn($x) => $x !== '')));
    return implode(', ', $items);
}

function buildHousehold(array $r): string {
    $parts = [];
    $spouse = trim((string)($r['spouseFullName'] ?? ''));
    $children = cleanList($r['childrenFullNames'] ?? '');
    $father = trim((string)($r['fatherFullName'] ?? ''));
    $mother = trim((string)($r['motherFullName'] ?? ''));
    $others = cleanList($r['otherFullNames'] ?? '');
    if ($spouse !== '') $parts[] = "Spouse: {$spouse}";
    if ($children !== '') $parts[] = "Children: {$children}";
    if ($father !== '') $parts[] = "Father: {$father}";
    if ($mother !== '') $parts[] = "Mother: {$mother}";
    if ($others !== '') $parts[] = "Others: {$others}";
    return implode('; ', $parts);
}

function formatBullet(array $r): ?string {
    $collection = trim((string)($r['collectionName'] ?? ''));
    $subcollection = trim((string)($r['subcollectionName'] ?? ''));
    if ($collection === '' && $subcollection === '') {
        return null;
    }

    [$date, $place] = pickDateAndPlace($r);
    $role = trim((string)($r['roleInRecord'] ?? ''));
    $name = trim((string)($r['fullName'] ?? ''));
    $relationshipToHead = trim((string)($r['relationshipToHead'] ?? ''));
    $household = buildHousehold($r);

    $link = extractHyperlink($r['arkId'] ?? '');
    $url = $link['url'];
    $label = $link['label'];

    $parts = [];

    // Title (collection and subcollection)
    $title = $collection;
    if ($subcollection !== '' && $subcollection !== $collection) {
        $title .= " — {$subcollection}";
    }
    $parts[] = "\"{$title},\" database";

    // FamilySearch link
    if ($url !== '') {
        $fsLink = "[{$url} FamilySearch]";
        $parts[] = $fsLink;
    }

    // Access date (use current date if not available)
    $accessDate = trim((string)($r['accessDate'] ?? ''));
    if ($accessDate === '') {
        $accessDate = date('j M Y');
    }
    $parts[] = "(accessed {$accessDate})";

    // Entry details: role/name, relationship, date/place
    $entryDetails = [];
    $personRef = trim(($role !== '' ? $role . ' for ' : '') . $name);
    if ($relationshipToHead !== '') {
        $personRef .= " ({$relationshipToHead})";
    }
    if ($personRef !== '') {
        $entryDetails[] = "entry for {$personRef}";
    }

    // Add date and place
    if ($date !== '') {
        $entryDetails[] = $date;
    }
    if ($place !== '') {
        $entryDetails[] = $place;
    }

    // Household info
    if ($household !== '') {
        $entryDetails[] = "Household: {$household}";
    }

    // Combine everything
    $text = implode(', ', $parts);
    if (!empty($entryDetails)) {
        $text .= ', ' . implode(', ', $entryDetails);
    }
    $text = '* ' . $text . '.';

    return $text;
}

function convertCsvToMarkdown(string $inputCsv, array $filters = []): string {
    $fh = openCsv($inputCsv);
    $header = readHeader($fh);
    $rows = readRowsAssoc($fh, $header);
    fclose($fh);

    // Apply filters
    $filteredRows = [];
    foreach ($rows as $r) {
        if (!shouldIncludeRow($r, $filters)) {
            continue;
        }
        $filteredRows[] = $r;
    }

    $out = [];
    $out[] = '== Sources ==';
    foreach ($filteredRows as $r) {
        $line = formatBullet($r);
        if ($line !== null) {
            $out[] = $line;
        }
    }
    return implode("\n", $out) . "\n";
}

function shouldIncludeRow(array $row, array $filters = []): bool {
    // --only-principal: skip if roleInRecord is not "Principal"
    if ($filters['only_principal'] ?? false) {
        $role = trim((string)($row['roleInRecord'] ?? ''));
        if (strtolower($role) !== 'principal') {
            return false;
        }
    }

    // --filter-role: skip if roleInRecord doesn't match
    if ($filters['role'] ?? null) {
        $role = trim((string)($row['roleInRecord'] ?? ''));
        if (strtolower($role) !== strtolower($filters['role'])) {
            return false;
        }
    }

    // --filter-name: skip if fullName doesn't match
    if ($filters['name'] ?? null) {
        $name = trim((string)($row['fullName'] ?? ''));
        if (strtolower($name) !== strtolower($filters['name'])) {
            return false;
        }
    }

    // --skip-keywords: skip if collectionName contains any keyword
    if (!empty($filters['skip_keywords'] ?? [])) {
        $collectionName = strtolower((string)($row['collectionName'] ?? ''));
        foreach ($filters['skip_keywords'] as $keyword) {
            if (strpos($collectionName, strtolower($keyword)) !== false) {
                return false;
            }
        }
    }

    // --exclude-families: skip if relationshipToHead is not empty
    if ($filters['exclude_families'] ?? false) {
        $relationship = trim((string)($row['relationshipToHead'] ?? ''));
        if ($relationship !== '') {
            return false;
        }
    }

    return true;
}

// Create output directory if needed
function ensureDir(string $dir): void {
    if ($dir === '' || $dir === '.' ) return;
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

// Try resolving the input path against common bases (CWD, repo root, script dir)
function resolveInputPath(string $input): string {
    $candidates = [];
    // As provided (relative to current working directory or absolute)
    $candidates[] = $input;
    // Relative to repo root (one level up from scripts dir)
    $repoRoot = dirname(__DIR__);
    $candidates[] = $repoRoot . DIRECTORY_SEPARATOR . ltrim($input, DIRECTORY_SEPARATOR);
    // Relative to scripts directory
    $scriptDir = __DIR__;
    $candidates[] = $scriptDir . DIRECTORY_SEPARATOR . ltrim($input, DIRECTORY_SEPARATOR);

    foreach ($candidates as $p) {
        if (file_exists($p)) {
            return $p;
        }
    }

    // Helpful error with attempted paths
    fwrite(STDERR, "Error: Input file not found: {$input}\n");
    fwrite(STDERR, "Tried:\n");
    foreach ($candidates as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    exit(4);
}

// Main
$args = parseArgs($argv);
$inputCsv = resolveInputPath($args['input']);
$outputPath = $args['output'];
$filters = $args['filters'] ?? [];

$md = convertCsvToMarkdown($inputCsv, $filters);
if ($outputPath) {
    ensureDir(dirname($outputPath));
    if (@file_put_contents($outputPath, $md) === false) {
        fwrite(STDERR, "Error: Failed to write output to {$outputPath}\n");
        exit(5);
    }
} else {
    echo $md;
}

?>
