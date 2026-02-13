<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree Search from Find a Grave Source (API-based)
 *
 * Parses a Find a Grave citation line from a source file and runs
 * a WikiTree searchPerson query.
 *
 * Usage:
 *   php scripts/wikitree_search_from_findagrave.php --source sources/Mark\ Lafayette\ White.md
 *   php scripts/wikitree_search_from_findagrave.php --source sources/Mark\ Lafayette\ White.md --limit 200 --verbose
 *   php scripts/scripts/wikitree_search_from_findagrave.php --source sources/Mark\ Lafayette\ White.md --out search-results/Mark-Lafayette-White.md
 *
 * @author David England
 * @date 2026-02-09
 */

require_once __DIR__ . '/wikitree_api_client.php';

$options = getopt('', [
    'source:',
    'memorial:',
    'url:',
    'card-dir:',
    'no-card',
    'limit:',
    'out:',
    'json:',
    'verbose',
    'help',
    'first:',
    'middle:',
    'last:',
    'birth-date:',
    'death-date:',
    'birth-location:',
    'death-location:'
]);

if (isset($options['help']) || (empty($options['source']) && empty($options['memorial']) && empty($options['url']))) {
    showHelp();
    exit(0);
}

$verbose = isset($options['verbose']);
$limit = isset($options['limit']) ? max(1, (int)$options['limit']) : 100;
$logFile = __DIR__ . '/../logs/wikitree_api_errors.log';
$resultsDir = __DIR__ . '/../search-results';
$cardDir = $options['card-dir'] ?? (__DIR__ . '/../cards/FaG');
$writeCard = !isset($options['no-card']);

$sourcePath = $options['source'] ?? null;
$sourceText = null;
$parsed = null;
if ($sourcePath) {
    if (!is_file($sourcePath)) {
        fwrite(STDERR, "Error: Source file not found: {$sourcePath}\n");
        exit(1);
    }
    $sourceText = file_get_contents($sourcePath);
    if ($sourceText === false || trim($sourceText) === '') {
        fwrite(STDERR, "Error: Source file is empty or unreadable.\n");
        exit(1);
    }
    $parsed = parseFindAGraveCitation($sourceText);
    if ($parsed === null) {
        fwrite(STDERR, "Error: Unable to parse Find a Grave citation in source.\n");
        exit(1);
    }
} else {
    $parsed = parseFromMemorialOrUrl($options['memorial'] ?? null, $options['url'] ?? null);
}

// Apply overrides from CLI
$parsed['FirstName'] = $options['first'] ?? $parsed['FirstName'];
$parsed['MiddleName'] = $options['middle'] ?? $parsed['MiddleName'];
$parsed['LastName'] = $options['last'] ?? $parsed['LastName'];
$parsed['BirthDate'] = $options['birth-date'] ?? $parsed['BirthDate'];
$parsed['DeathDate'] = $options['death-date'] ?? $parsed['DeathDate'];
$parsed['BirthLocation'] = $options['birth-location'] ?? $parsed['BirthLocation'];
$parsed['DeathLocation'] = $options['death-location'] ?? $parsed['DeathLocation'];

$hasName = trim(($parsed['FirstName'] ?? '') . ($parsed['LastName'] ?? '')) !== '';
if (!$hasName) {
    fwrite(STDERR, "Error: Missing name fields. Provide --source with a citation, or supply --first/--last.\n");
    exit(1);
}

$api = new WikiTreeAPI($verbose, $logFile);
[$response, $results, $attempts] = runSearchWithFallbacks($api, $parsed, $limit, $verbose);

ensureDir($resultsDir);
if ($writeCard) {
    ensureDir($cardDir);
}

$base = $sourcePath ? pathinfo($sourcePath, PATHINFO_FILENAME) : buildMemorialBaseName($parsed);
$defaultOut = "{$resultsDir}/{$base}-wikitree-search.md";
$defaultJson = "{$resultsDir}/{$base}-wikitree-search.json";

$outPath = $options['out'] ?? $defaultOut;
$jsonPath = $options['json'] ?? $defaultJson;

file_put_contents($jsonPath, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

$lines = [];
$lines[] = "# WikiTree searchPerson from Find a Grave";
$lines[] = "";
    $lines[] = "**Source**: " . ($sourcePath ?? '(memorial/url input)');
$lines[] = "**Generated**: " . date('Y-m-d H:i:s');
$lines[] = "";
$lines[] = "## Parsed Find a Grave";
foreach (formatParsedSummary($parsed) as $line) {
    $lines[] = $line;
}
$lines[] = "";
$lines[] = "## Search Parameters (Attempts)";
foreach ($attempts as $i => $params) {
    $lines[] = "* Attempt " . ($i + 1) . ":";
    foreach ($params as $key => $value) {
        $lines[] = "  - {$key}: {$value}";
    }
}
$lines[] = "";
$lines[] = "## Results";
if (empty($results)) {
    $lines[] = "* No results returned.";
} else {
    foreach ($results as $person) {
        if (is_array($person)) {
            $lines[] = "* " . formatLeadLine($person);
        }
    }
}

file_put_contents($outPath, implode("\n", $lines) . "\n");

echo "Saved results: {$outPath}\n";
echo "Saved raw JSON: {$jsonPath}\n";

if ($writeCard) {
    $cardPath = buildCardPath($cardDir, $parsed, $base);
    file_put_contents($cardPath, buildIndexCardMarkdown($parsed, $sourcePath));
    echo "Saved index card: {$cardPath}\n";
}

function showHelp(): void {
    $help = <<<TXT
WikiTree Search from Find a Grave Source

Usage:
  php wikitree_search_from_findagrave.php --source FILE [OPTIONS]

Options:
  --source FILE         Source markdown file containing Find a Grave citation
  --memorial ID         Find a Grave Memorial ID (e.g., 73079429)
  --url URL             Find a Grave memorial URL
  --card-dir DIR        Output directory for index cards (default: cards/FaG)
  --no-card             Skip writing the index card
  --limit N             Max results (default: 100)
  --out FILE            Output markdown file (default: search-results/{basename}-wikitree-search.md)
  --json FILE           Output JSON file (default: search-results/{basename}-wikitree-search.json)
  --first NAME          Override first name
  --middle NAME         Override middle name
  --last NAME           Override last name
  --birth-date DATE     Override birth date (YYYY-MM-DD)
  --death-date DATE     Override death date (YYYY-MM-DD)
  --birth-location TEXT Override birth location
  --death-location TEXT Override death location
  --verbose             Verbose logging
  --help                Show this help
TXT;
    echo $help . "\n";
}

function parseFromMemorialOrUrl(?string $memorialId, ?string $url): array {
    $memorialId = $memorialId !== null ? trim($memorialId) : null;
    $url = $url !== null ? trim($url) : null;

    if ($url && preg_match('/findagrave\.com\/memorial\/(\d+)/i', $url, $m)) {
        $memorialId = $memorialId ?? $m[1];
    }

    if ($memorialId && !$url) {
        $url = "https://www.findagrave.com/memorial/{$memorialId}/";
    }

    return [
        'Name' => null,
        'FirstName' => null,
        'MiddleName' => null,
        'LastName' => null,
        'BirthDate' => null,
        'DeathDate' => null,
        'BirthLocation' => null,
        'DeathLocation' => null,
        'Cemetery' => null,
        'Location' => null,
        'MemorialId' => $memorialId,
        'Url' => $url,
        'Accessed' => null,
        'Maintainer' => null,
        'ContributorId' => null,
        'RawBirth' => null,
        'RawDeath' => null
    ];
}

function buildMemorialBaseName(array $parsed): string {
    if (!empty($parsed['MemorialId'])) {
        return 'FindAGrave-' . $parsed['MemorialId'];
    }
    return 'FindAGrave-search';
}

function buildCardPath(string $dir, array $parsed, string $fallbackBase): string {
    $name = $parsed['Name'] ?? '';
    $slug = slugify($name);
    if ($slug === '') {
        $slug = slugify($fallbackBase);
    }
    return rtrim($dir, '/') . '/' . $slug . '.md';
}

function slugify(string $text): string {
    $text = trim($text);
    if ($text === '') {
        return '';
    }
    $text = preg_replace('/[^A-Za-z0-9]+/', '-', $text);
    $text = trim($text, '-');
    return $text;
}

function buildIndexCardMarkdown(array $parsed, ?string $sourcePath): string {
    $lines = [];
    $name = $parsed['Name'] ?? '';
    $lines[] = "# {$name}";
    $lines[] = "";
    $lines[] = "## Index Card (4x5)";
    $lines[] = "";
    $lines[] = "* Birth: " . formatVital($parsed['BirthDate'] ?? '', $parsed['BirthLocation'] ?? '');
    $lines[] = "* Death: " . formatVital($parsed['DeathDate'] ?? '', $parsed['DeathLocation'] ?? '');
    if (!empty($parsed['Cemetery'])) {
        $lines[] = "* Cemetery: " . $parsed['Cemetery'];
    }
    if (!empty($parsed['Location'])) {
        $lines[] = "* Location: " . $parsed['Location'];
    }
    if (!empty($parsed['MemorialId'])) {
        $lines[] = "* Find a Grave Memorial ID: " . $parsed['MemorialId'];
    }
    if (!empty($parsed['Url'])) {
        $lines[] = "* Find a Grave URL: " . $parsed['Url'];
    }
    if (!empty($parsed['Accessed'])) {
        $lines[] = "* Accessed: " . $parsed['Accessed'];
    }
    if (!empty($parsed['Maintainer'])) {
        $lines[] = "* Maintainer: " . $parsed['Maintainer'];
    }
    if (!empty($parsed['ContributorId'])) {
        $lines[] = "* Contributor ID: " . $parsed['ContributorId'];
    }
    if ($sourcePath) {
        $lines[] = "* Source File: " . $sourcePath;
    }
    return implode("\n", $lines) . "\n";
}

function parseFindAGraveCitation(string $text): ?array {
    $text = trim(preg_replace('/\s+/', ' ', $text));

    $url = null;
    $accessed = null;
    if (preg_match('/\((https?:\/\/www\.findagrave\.com\/memorial\/[^)\s:]+).*?accessed\s+([^)]+)\)/i', $text, $m)) {
        $url = $m[1];
        $accessed = trim($m[2]);
    } elseif (preg_match('/\((https?:\/\/www\.findagrave\.com\/memorial\/[^)\s]+)\)/i', $text, $m)) {
        $url = $m[1];
    }

    $name = null;
    $birthRaw = null;
    $deathRaw = null;
    if (preg_match('/memorial page for\s+(.+?)\s*\(([^)]+)\)/i', $text, $m)) {
        $name = trim($m[1]);
        $dates = $m[2];
        if (preg_match('/(.+?)[–-](.+)/u', $dates, $d)) {
            $birthRaw = trim($d[1]);
            $deathRaw = trim($d[2]);
        }
    }

    $memorialId = null;
    if (preg_match('/Memorial ID\s+(\d+)/i', $text, $m)) {
        $memorialId = $m[1];
    }

    $cemetery = null;
    $location = null;
    if (preg_match('/citing\s+([^;]+);/i', $text, $m)) {
        $cemeteryBlock = trim($m[1]);
        $parts = array_map('trim', explode(',', $cemeteryBlock));
        if (!empty($parts)) {
            $cemetery = array_shift($parts);
            if (!empty($parts)) {
                $location = implode(', ', $parts);
            }
        }
    }

    $maintainer = null;
    $contributorId = null;
    if (preg_match('/Maintained by\s+(.+?)\s*\(contributor\s+(\d+)\)/i', $text, $m)) {
        $maintainer = trim($m[1]);
        $contributorId = $m[2];
    }

    if ($name === null && $memorialId === null && $url === null) {
        return null;
    }

    [$first, $middle, $last] = splitName($name);

    $birthDate = $birthRaw ? normalizeDate($birthRaw) : null;
    $deathDate = $deathRaw ? normalizeDate($deathRaw) : null;

    return [
        'Name' => $name,
        'FirstName' => $first,
        'MiddleName' => $middle,
        'LastName' => $last,
        'BirthDate' => $birthDate,
        'DeathDate' => $deathDate,
        'BirthLocation' => $location,
        'DeathLocation' => $location,
        'Cemetery' => $cemetery,
        'Location' => $location,
        'MemorialId' => $memorialId,
        'Url' => $url,
        'Accessed' => $accessed,
        'Maintainer' => $maintainer,
        'ContributorId' => $contributorId,
        'RawBirth' => $birthRaw,
        'RawDeath' => $deathRaw
    ];
}

function splitName(?string $name): array {
    if ($name === null) {
        return ['', '', ''];
    }
    $parts = preg_split('/\s+/', trim($name));
    if (!$parts) {
        return ['', '', ''];
    }
    $suffixes = ['Jr', 'Sr', 'II', 'III', 'IV'];
    $suffix = '';
    if (count($parts) > 1) {
        $lastToken = rtrim($parts[count($parts) - 1], '.');
        if (in_array($lastToken, $suffixes, true)) {
            $suffix = array_pop($parts);
        }
    }
    $first = array_shift($parts);
    $last = count($parts) ? array_pop($parts) : '';
    $middle = count($parts) ? implode(' ', $parts) : '';
    if ($suffix !== '') {
        $last = trim($last . ' ' . $suffix);
    }
    return [$first ?? '', $middle, $last];
}

function normalizeDate(string $text): ?string {
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
        return $text;
    }

    $monthMap = [
        'jan' => 1, 'january' => 1,
        'feb' => 2, 'february' => 2,
        'mar' => 3, 'march' => 3,
        'apr' => 4, 'april' => 4,
        'may' => 5,
        'jun' => 6, 'june' => 6,
        'jul' => 7, 'july' => 7,
        'aug' => 8, 'august' => 8,
        'sep' => 9, 'sept' => 9, 'september' => 9,
        'oct' => 10, 'october' => 10,
        'nov' => 11, 'november' => 11,
        'dec' => 12, 'december' => 12,
    ];

    if (preg_match('/(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})/', $text, $m)) {
        $day = (int)$m[1];
        $monthName = strtolower($m[2]);
        $year = (int)$m[3];
        if (isset($monthMap[$monthName])) {
            $month = $monthMap[$monthName];
            return sprintf('%04d-%02d-%02d', $year, $month, $day);
        }
    }

    if (preg_match('/([A-Za-z]+)\s+(\d{4})/', $text, $m)) {
        $monthName = strtolower($m[1]);
        $year = (int)$m[2];
        if (isset($monthMap[$monthName])) {
            $month = $monthMap[$monthName];
            return sprintf('%04d-%02d-00', $year, $month);
        }
    }

    if (preg_match('/(\d{4})/', $text, $m)) {
        return sprintf('%04d-00-00', (int)$m[1]);
    }

    return null;
}

function buildSearchParams(array $parsed, int $limit): array {
    $params = [
        'FirstName' => $parsed['FirstName'] ?? null,
        'MiddleName' => $parsed['MiddleName'] ?? null,
        'LastName' => $parsed['LastName'] ?? null,
        'BirthDate' => $parsed['BirthDate'] ?? null,
        'DeathDate' => $parsed['DeathDate'] ?? null,
        'BirthLocation' => $parsed['BirthLocation'] ?? null,
        'DeathLocation' => $parsed['DeathLocation'] ?? null,
        'limit' => $limit
    ];

    return array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    });
}

function runSearchWithFallbacks(WikiTreeAPI $api, array $parsed, int $limit, bool $verbose): array {
    $attempts = buildSearchAttempts($parsed, $limit);
    $allResults = [];
    $firstResponse = null;

    foreach ($attempts as $index => $params) {
        if ($verbose) {
            fwrite(STDERR, "[DEBUG] searchPerson attempt " . ($index + 1) . "\n");
        }
        $response = $api->searchPerson($params);
        if ($response === false) {
            fwrite(STDERR, "Error: searchPerson failed\n");
            exit(1);
        }
        if ($firstResponse === null) {
            $firstResponse = $response;
        }
        $results = extractSearchResults($response);
        foreach ($results as $person) {
            if (is_array($person)) {
                $key = $person['Name'] ?? $person['WikiTreeId'] ?? $person['Id'] ?? null;
                if ($key === null) {
                    $allResults[] = $person;
                } else {
                    $allResults[$key] = $person;
                }
            }
        }
        if (!empty($allResults)) {
            break;
        }
    }

    if (is_array($allResults) && array_keys($allResults) !== range(0, count($allResults) - 1)) {
        $allResults = array_values($allResults);
    }

    return [$firstResponse ?? [], $allResults, $attempts];
}

function buildSearchAttempts(array $parsed, int $limit): array {
    $attempts = [];

    $base = buildSearchParams($parsed, $limit);
    if (!empty($base)) {
        $attempts[] = $base;
    }

    $relaxed = $base;
    unset($relaxed['MiddleName']);
    if (!empty($relaxed)) {
        $attempts[] = $relaxed;
    }

    $yearOnly = $base;
    if (!empty($yearOnly['BirthDate'])) {
        $year = substr($yearOnly['BirthDate'], 0, 4);
        if (preg_match('/^\d{4}$/', $year)) {
            $yearOnly['BirthDate'] = $year . '-00-00';
        }
    }
    if (!empty($yearOnly['DeathDate'])) {
        $year = substr($yearOnly['DeathDate'], 0, 4);
        if (preg_match('/^\d{4}$/', $year)) {
            $yearOnly['DeathDate'] = $year . '-00-00';
        }
    }
    if (!empty($yearOnly)) {
        $attempts[] = $yearOnly;
    }

    $nameOnly = [];
    if (!empty($parsed['FirstName'])) {
        $nameOnly['FirstName'] = $parsed['FirstName'];
    }
    if (!empty($parsed['LastName'])) {
        $nameOnly['LastName'] = $parsed['LastName'];
    }
    if (!empty($nameOnly)) {
        $nameOnly['limit'] = $limit;
        $attempts[] = $nameOnly;
    }

    $locationOnly = $nameOnly;
    if (!empty($parsed['BirthLocation'])) {
        $locationOnly['BirthLocation'] = $parsed['BirthLocation'];
    } elseif (!empty($parsed['DeathLocation'])) {
        $locationOnly['DeathLocation'] = $parsed['DeathLocation'];
    }
    if (count($locationOnly) > count($nameOnly)) {
        $attempts[] = $locationOnly;
    }

    return $attempts;
}

function extractSearchResults(array $response): array {
    $root = $response;

    if (isset($root[0]) && is_array($root[0])) {
        $root = $root[0];
    }

    if (isset($root['people']) && is_array($root['people'])) {
        return $root['people'];
    }
    if (isset($root['matches']) && is_array($root['matches'])) {
        return $root['matches'];
    }
    if (isset($root['items']) && is_array($root['items'])) {
        return $root['items'];
    }
    if (isset($root['results']) && is_array($root['results'])) {
        return $root['results'];
    }

    return is_array($root) ? $root : [];
}

function formatLeadLine(array $person): string {
    $id = $person['Name'] ?? $person['WikiTreeId'] ?? $person['Id'] ?? null;

    $first = $person['FirstName'] ?? '';
    $middle = $person['MiddleName'] ?? '';
    $last = $person['LastNameAtBirth'] ?? $person['LastNameCurrent'] ?? '';
    $real = $person['RealName'] ?? '';
    $nameParts = trim($real) !== '' ? $real : trim(implode(' ', array_filter([$first, $middle, $last])));

    $label = $nameParts !== '' ? $nameParts : 'Unknown';
    if ($id && $label) {
        $label = "[[{$id}|{$label}]]";
    }

    $birth = formatVital($person['BirthDate'] ?? '', $person['BirthLocation'] ?? '');
    $death = formatVital($person['DeathDate'] ?? '', $person['DeathLocation'] ?? '');

    $parts = [];
    if ($birth !== '') {
        $parts[] = "b. {$birth}";
    }
    if ($death !== '') {
        $parts[] = "d. {$death}";
    }
    $suffix = $parts ? ' — ' . implode('; ', $parts) : '';

    return $label . $suffix;
}

function formatVital(string $date = '', string $location = ''): string {
    $bits = [];
    if ($date !== '' && $date !== '0000-00-00') {
        $bits[] = $date;
    }
    if ($location !== '') {
        $bits[] = $location;
    }
    return implode(' — ', $bits);
}

function formatParsedSummary(array $parsed): array {
    $lines = [];
    $lines[] = "* Name: " . ($parsed['Name'] ?? '');
    $lines[] = "* Birth: " . formatVital($parsed['BirthDate'] ?? '', $parsed['BirthLocation'] ?? '');
    $lines[] = "* Death: " . formatVital($parsed['DeathDate'] ?? '', $parsed['DeathLocation'] ?? '');
    if (!empty($parsed['Cemetery'])) {
        $lines[] = "* Cemetery: " . $parsed['Cemetery'];
    }
    if (!empty($parsed['Location'])) {
        $lines[] = "* Location: " . $parsed['Location'];
    }
    if (!empty($parsed['MemorialId'])) {
        $lines[] = "* Find a Grave Memorial ID: " . $parsed['MemorialId'];
    }
    if (!empty($parsed['Url'])) {
        $lines[] = "* Find a Grave URL: " . $parsed['Url'];
    }
    if (!empty($parsed['Accessed'])) {
        $lines[] = "* Accessed: " . $parsed['Accessed'];
    }
    if (!empty($parsed['Maintainer'])) {
        $lines[] = "* Maintainer: " . $parsed['Maintainer'];
    }
    if (!empty($parsed['ContributorId'])) {
        $lines[] = "* Contributor ID: " . $parsed['ContributorId'];
    }
    return $lines;
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
}
