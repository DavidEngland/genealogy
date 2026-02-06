#!/usr/bin/env php
<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree Bio Diff (API-based)
 *
 * Compares a local Markdown bio against WikiTree API data for a profile
 * and writes a Markdown errata report describing differences.
 *
 * Usage:
 *   php wikitree_bio_diff.php --profile England-1075
 *   php wikitree_bio_diff.php --profile England-1075 --bio /path/to/England-1075.md
 *   php wikitree_bio_diff.php --profile England-1075 --out ancestors/family/Englands/England-1075-errata.md
 *
 * Options:
 *   --profile ID       WikiTree profile ID (required)
 *   --bio FILE         Local bio markdown file (default: {repo}/{ID}.md)
 *   --out FILE         Output markdown file (default: ancestors/family/{FamilyName}/wikitree-id-errata.md)
 *   --verbose          Enable verbose API logging
 *   --help, -h         Show this help message
 */

require_once __DIR__ . '/wikitree_api_client.php';

function parseArgs(array $argv): array {
    $options = [
        'profile' => null,
        'bio' => null,
        'out' => null,
        'verbose' => false,
        'help' => false
    ];

    for ($i = 1; $i < count($argv); $i++) {
        switch ($argv[$i]) {
            case '--profile':
                $options['profile'] = $argv[++$i] ?? null;
                break;
            case '--bio':
                $options['bio'] = $argv[++$i] ?? null;
                break;
            case '--out':
                $options['out'] = $argv[++$i] ?? null;
                break;
            case '--verbose':
                $options['verbose'] = true;
                break;
            case '--help':
            case '-h':
                $options['help'] = true;
                break;
        }
    }

    return $options;
}

function showHelp(): void {
    echo "WikiTree Bio Diff (API-based)\n\n";
    echo "Usage:\n";
    echo "  php wikitree_bio_diff.php --profile WIKITREE-ID [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --profile ID       WikiTree ID (required)\n";
    echo "  --bio FILE         Local bio markdown file (default: {repo}/{ID}.md)\n";
    echo "  --out FILE         Output markdown file (default: ancestors/family/{FamilyName}/wikitree-id-errata.md)\n";
    echo "  --verbose          Enable verbose API logging\n";
    echo "  --help, -h         Show this help message\n";
}

function ensureDir(string $path): void {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

function readBioFile(string $path): string {
    $content = @file_get_contents($path);
    if ($content === false) {
        throw new RuntimeException("Failed to read bio file: $path");
    }
    return $content;
}

function extractBiographySection(string $text): string {
    $start = 0;
    if (preg_match('/==\s*\*?Biography\*?\s*==/i', $text, $match, PREG_OFFSET_CAPTURE)) {
        $start = $match[0][1] + strlen($match[0][0]);
    }

    $end = strlen($text);
    $endPatterns = [
        '/===\s*Research Notes\s*===/i',
        '/===\s*Family Information\b[^=]*===/i',
        '/==\s*Sources\s*==/i'
    ];
    foreach ($endPatterns as $pattern) {
        if (preg_match($pattern, $text, $match, PREG_OFFSET_CAPTURE)) {
            $end = min($end, $match[0][1]);
        }
    }

    if ($end <= $start) {
        return $text;
    }

    return substr($text, $start, $end - $start);
}

function normalizeWhitespace(string $value): string {
    $value = $value ?? '';
    $value = preg_replace('/\s+/', ' ', $value);
    return trim($value);
}

function extractWikiLinks(string $text): array {
    $links = [];
    if (preg_match_all('/\[\[([A-Za-z][a-z]+-\d+)\|([^\]]+)\]\]/', $text, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $match) {
            $links[] = ['id' => $match[1], 'name' => trim($match[2])];
        }
    }
    return $links;
}

function parseDateFromText(string $text): ?string {
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (preg_match('/\b(\d{4})-(\d{2})-(\d{2})\b/', $text, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }

    if (preg_match('/\b(\d{4})-(\d{2})\b/', $text, $m)) {
        return sprintf('%04d-%02d-00', (int)$m[1], (int)$m[2]);
    }

    if (preg_match('/\b(\d{4})\b/', $text, $m)) {
        return sprintf('%04d-00-00', (int)$m[1]);
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
        'dec' => 12, 'december' => 12
    ];

    if (preg_match('/\b(\d{1,2})\s+([A-Za-z]+)\s+(\d{4})\b/', $text, $m)) {
        $day = (int)$m[1];
        $monthKey = strtolower($m[2]);
        $year = (int)$m[3];
        if (isset($monthMap[$monthKey])) {
            return sprintf('%04d-%02d-%02d', $year, $monthMap[$monthKey], $day);
        }
    }

    if (preg_match('/\b([A-Za-z]+)\s+(\d{1,2}),?\s+(\d{4})\b/', $text, $m)) {
        $monthKey = strtolower($m[1]);
        $day = (int)$m[2];
        $year = (int)$m[3];
        if (isset($monthMap[$monthKey])) {
            return sprintf('%04d-%02d-%02d', $year, $monthMap[$monthKey], $day);
        }
    }

    return null;
}

function extractDateString(string $text): ?string {
    $text = trim($text);
    if ($text === '') {
        return null;
    }

    if (preg_match('/\b\d{4}-\d{2}-\d{2}\b/', $text, $m)) {
        return $m[0];
    }

    if (preg_match('/\b\d{4}-\d{2}\b/', $text, $m)) {
        return $m[0];
    }

    if (preg_match('/\b\d{1,2}\s+[A-Za-z]+\s+\d{4}\b/', $text, $m)) {
        return $m[0];
    }

    if (preg_match('/\b[A-Za-z]+\s+\d{1,2},?\s+\d{4}\b/', $text, $m)) {
        return $m[0];
    }

    if (preg_match('/\b\d{4}\b/', $text, $m)) {
        return $m[0];
    }

    return null;
}

function extractDateAndLocation(string $fragment): array {
    $fragment = normalizeWhitespace($fragment);
    $date = extractDateString($fragment);
    $location = null;

    if (preg_match('/\bin\s+([^\.]+)$/i', $fragment, $m)) {
        $location = normalizeWhitespace($m[1]);
    }

    return [$date, $location];
}

function parseLocalBioFacts(string $bioText): array {
    $bioSection = extractBiographySection($bioText);
    $facts = [
        'birthDate' => null,
        'birthLocation' => null,
        'deathDate' => null,
        'deathLocation' => null,
        'marriages' => [],
        'children' => []
    ];

    if (preg_match('/\bborn\b([^\.]+)/i', $bioSection, $m)) {
        [$date, $location] = extractDateAndLocation($m[1]);
        $facts['birthDate'] = $date;
        $facts['birthLocation'] = $location;
    }

    if (preg_match('/\bdied\b([^\.]+)/i', $bioSection, $m)) {
        [$date, $location] = extractDateAndLocation($m[1]);
        $facts['deathDate'] = $date;
        $facts['deathLocation'] = $location;
    }

    if (preg_match_all('/\bmarried\b([^\.]+)/i', $bioSection, $matches)) {
        foreach ($matches[1] as $fragment) {
            $links = extractWikiLinks($fragment);
            $spouseId = $links[0]['id'] ?? null;
            $spouseName = $links[0]['name'] ?? null;

            if (!$spouseName) {
                if (preg_match('/married\s+([^,\(]+?)(?:\s+on\b|\s+in\b|\.|$)/i', $fragment, $nameMatch)) {
                    $spouseName = normalizeWhitespace($nameMatch[1]);
                }
            }

            $date = null;
            if (preg_match('/\bon\s+([^\.]+)$/i', $fragment, $dateMatch)) {
                $date = extractDateString($dateMatch[1]);
            } else {
                $date = extractDateString($fragment);
            }

            $location = null;
            if (preg_match('/\bin\s+([^\.]+)$/i', $fragment, $locMatch)) {
                $location = normalizeWhitespace($locMatch[1]);
            }

            $facts['marriages'][] = [
                'spouseId' => $spouseId,
                'spouseName' => $spouseName,
                'date' => $date,
                'location' => $location
            ];
        }
    }

    $lines = preg_split('/\r?\n/', $bioText);
    $collectingChildren = false;

    foreach ($lines as $line) {
        if (preg_match('/^\s*[*-]\s*Children\b/i', $line)) {
            $collectingChildren = true;
            continue;
        }
        if ($collectingChildren) {
            if (preg_match('/^\s*[*-]\s*\S+/', $line) && !preg_match('/^\s{2,}[*-]/', $line)) {
                $collectingChildren = false;
            }
        }

        if ($collectingChildren) {
            if (preg_match('/^\s*[*-]\s*(.+)$/', $line, $m)) {
                $item = normalizeWhitespace($m[1]);
                $links = extractWikiLinks($item);
                if (!empty($links)) {
                    foreach ($links as $link) {
                        $facts['children'][] = $link;
                    }
                } else {
                    $name = preg_replace('/\s*\(.+\)$/', '', $item);
                    if ($name !== '') {
                        $facts['children'][] = ['id' => null, 'name' => $name];
                    }
                }
            }
        }
    }

    if (preg_match_all('/They had[^\.]+\./i', $bioSection, $sentences)) {
        foreach ($sentences[0] as $sentence) {
            $links = extractWikiLinks($sentence);
            foreach ($links as $link) {
                $facts['children'][] = $link;
            }
        }
    }

    $facts['children'] = uniquePeople($facts['children']);
    return $facts;
}

function uniquePeople(array $people): array {
    $seen = [];
    $unique = [];
    foreach ($people as $person) {
        $id = $person['id'] ?? null;
        $name = normalizeWhitespace($person['name'] ?? '');
        $key = $id ? strtolower($id) : strtolower($name);
        if ($key === '') {
            continue;
        }
        if (!isset($seen[$key])) {
            $seen[$key] = true;
            $unique[] = [
                'id' => $id,
                'name' => $name
            ];
        }
    }
    return $unique;
}

function normalizeDate(?string $date): ?string {
    if (!$date || $date === '0000-00-00') {
        return null;
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) {
        return sprintf('%04d-%02d-%02d', (int)$m[1], (int)$m[2], (int)$m[3]);
    }
    return parseDateFromText($date);
}

function normalizeLocation(?string $location): ?string {
    if (!$location) {
        return null;
    }
    $normalized = strtolower($location);
    $normalized = preg_replace('/[\.,;()\[\]]+/', ' ', $normalized);
    $normalized = preg_replace('/\s+/', ' ', $normalized);
    return trim($normalized);
}

function normalizeName(?string $name): ?string {
    if (!$name) {
        return null;
    }
    $name = strtolower($name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function extractFactsFromApi(array $profile): array {
    $facts = [
        'birthDate' => $profile['BirthDate'] ?? null,
        'birthLocation' => $profile['BirthLocation'] ?? null,
        'deathDate' => $profile['DeathDate'] ?? null,
        'deathLocation' => $profile['DeathLocation'] ?? null,
        'marriages' => [],
        'children' => []
    ];

    $spouses = $profile['Spouses'] ?? [];
    if (!empty($spouses)) {
        foreach ($spouses as $spouse) {
            if (!is_array($spouse)) {
                continue;
            }
            $facts['marriages'][] = [
                'spouseId' => $spouse['Name'] ?? null,
                'spouseName' => formatPersonName($spouse),
                'date' => $spouse['MarriageDate'] ?? $spouse['marriage_date'] ?? null,
                'location' => $spouse['MarriageLocation'] ?? $spouse['marriage_location'] ?? null
            ];
        }
    }

    $children = $profile['Children'] ?? [];
    if (!empty($children)) {
        foreach ($children as $child) {
            if (!is_array($child)) {
                continue;
            }
            $facts['children'][] = [
                'id' => $child['Name'] ?? null,
                'name' => formatPersonName($child)
            ];
        }
    }

    $facts['children'] = uniquePeople($facts['children']);
    return $facts;
}

function formatPersonName(array $profile): string {
    $parts = [];
    if (!empty($profile['FirstName'])) {
        $parts[] = $profile['FirstName'];
    }
    if (!empty($profile['MiddleName'])) {
        $parts[] = $profile['MiddleName'];
    }
    if (!empty($profile['LastNameAtBirth'])) {
        $parts[] = $profile['LastNameAtBirth'];
    } elseif (!empty($profile['LastNameCurrent'])) {
        $parts[] = $profile['LastNameCurrent'];
    }

    $name = trim(implode(' ', $parts));
    if ($name === '' && !empty($profile['RealName'])) {
        $name = $profile['RealName'];
    }

    return $name !== '' ? $name : 'Unknown';
}

function compareField(string $label, ?string $localValue, ?string $apiValue, array &$strict, array &$normalized, callable $normalizer): void {
    $localValue = $localValue ? trim($localValue) : null;
    $apiValue = $apiValue ? trim($apiValue) : null;

    if (!$localValue && !$apiValue) {
        return;
    }

    if (!$localValue && $apiValue) {
        $strict[] = "Missing in bio: $label → $apiValue";
    } elseif ($localValue && !$apiValue) {
        $strict[] = "Missing in WikiTree: $label → $localValue";
    } elseif ($localValue !== $apiValue) {
        $strict[] = "Mismatch: $label → bio '$localValue' vs WikiTree '$apiValue'";
    }

    $localNorm = $normalizer($localValue);
    $apiNorm = $normalizer($apiValue);

    if (!$localNorm && !$apiNorm) {
        return;
    }

    if (!$localNorm && $apiNorm) {
        $normalized[] = "Missing in bio (normalized): $label → $apiValue";
    } elseif ($localNorm && !$apiNorm) {
        $normalized[] = "Missing in WikiTree (normalized): $label → $localValue";
    } elseif ($localNorm !== $apiNorm) {
        $normalized[] = "Mismatch (normalized): $label → bio '$localValue' vs WikiTree '$apiValue'";
    }
}

function mapMarriages(array $marriages): array {
    $mapped = [];
    foreach ($marriages as $marriage) {
        $id = $marriage['spouseId'] ?? null;
        $name = normalizeName($marriage['spouseName'] ?? null);
        $key = $id ? strtolower($id) : $name;
        if ($key) {
            $mapped[$key] = $marriage;
        }
    }
    return $mapped;
}

function mapChildren(array $children): array {
    $mapped = [];
    foreach ($children as $child) {
        $id = $child['id'] ?? null;
        $name = normalizeName($child['name'] ?? null);
        $key = $id ? strtolower($id) : $name;
        if ($key) {
            $mapped[$key] = $child;
        }
    }
    return $mapped;
}

function compareChildren(array $localChildren, array $apiChildren, array &$strict, array &$normalized): void {
    $localMap = mapChildren($localChildren);
    $apiMap = mapChildren($apiChildren);

    foreach ($localMap as $key => $child) {
        if (!isset($apiMap[$key])) {
            $label = $child['id'] ?? $child['name'] ?? 'Unknown';
            $strict[] = "Child missing on WikiTree: $label";
            $normalized[] = "Child missing on WikiTree (normalized): $label";
        }
    }

    foreach ($apiMap as $key => $child) {
        if (!isset($localMap[$key])) {
            $label = $child['id'] ?? $child['name'] ?? 'Unknown';
            $strict[] = "Child missing in bio: $label";
            $normalized[] = "Child missing in bio (normalized): $label";
        }
    }
}

function compareMarriages(array $localMarriages, array $apiMarriages, array &$strict, array &$normalized): void {
    $localMap = mapMarriages($localMarriages);
    $apiMap = mapMarriages($apiMarriages);

    foreach ($localMap as $key => $marriage) {
        if (!isset($apiMap[$key])) {
            $label = $marriage['spouseId'] ?? $marriage['spouseName'] ?? 'Unknown spouse';
            $strict[] = "Marriage missing on WikiTree: $label";
            $normalized[] = "Marriage missing on WikiTree (normalized): $label";
            continue;
        }

        $api = $apiMap[$key];
        $label = $marriage['spouseId'] ?? $marriage['spouseName'] ?? $api['spouseId'] ?? $api['spouseName'] ?? 'Unknown spouse';
        compareField("Marriage date ($label)", $marriage['date'] ?? null, $api['date'] ?? null, $strict, $normalized, 'normalizeDate');
        compareField("Marriage location ($label)", $marriage['location'] ?? null, $api['location'] ?? null, $strict, $normalized, 'normalizeLocation');
    }

    foreach ($apiMap as $key => $marriage) {
        if (!isset($localMap[$key])) {
            $label = $marriage['spouseId'] ?? $marriage['spouseName'] ?? 'Unknown spouse';
            $strict[] = "Marriage missing in bio: $label";
            $normalized[] = "Marriage missing in bio (normalized): $label";
        }
    }
}

function resolveFamilyDirectory(string $baseDir, string $familyName): array {
    $familyName = trim($familyName);
    if ($familyName === '') {
        $familyName = 'Unknown';
    }

    $primary = $familyName;
    $alternate = $familyName;

    if (preg_match('/s$/i', $familyName)) {
        $alternate = substr($familyName, 0, -1);
    } else {
        $alternate = $familyName . 's';
    }

    $primaryPath = $baseDir . '/' . $primary;
    $alternatePath = $baseDir . '/' . $alternate;

    if (is_dir($primaryPath)) {
        return [$primaryPath, $primary, $alternate, is_dir($alternatePath)];
    }

    if (is_dir($alternatePath)) {
        return [$alternatePath, $alternate, $primary, false];
    }

    return [$primaryPath, $primary, $alternate, false];
}

function buildReport(string $profileId, string $bioPath, array $localFacts, array $apiFacts, array $strict, array $normalized, array $meta): string {
    $lines = [];
    $lines[] = "# WikiTree Bio Errata: $profileId";
    $lines[] = "";
    $lines[] = "- Generated: " . date('Y-m-d H:i:s');
    $lines[] = "- Bio file: $bioPath";
    $lines[] = "- WikiTree: https://www.wikitree.com/wiki/$profileId";
    $lines[] = "- Family directory: {$meta['familyDir']}";
    $lines[] = "- Family name resolved: {$meta['familyName']}";
    if ($meta['alternateFamilyName']) {
        $lines[] = "- Alternate family name candidate: {$meta['alternateFamilyName']}";
    }
    if ($meta['bothFamilyDirs']) {
        $lines[] = "- Note: both family-name directories exist.";
    }
    $lines[] = "";

    $lines[] = "## Differences (Normalized)";
    if (empty($normalized)) {
        $lines[] = "- (none)";
    } else {
        foreach ($normalized as $item) {
            $lines[] = "- $item";
        }
    }

    $lines[] = "";
    $lines[] = "## Differences (Strict)";
    if (empty($strict)) {
        $lines[] = "- (none)";
    } else {
        foreach ($strict as $item) {
            $lines[] = "- $item";
        }
    }

    $lines[] = "";
    $lines[] = "## Extracted Facts (Bio)";
    $lines[] = "- Birth date: " . ($localFacts['birthDate'] ?? '(missing)');
    $lines[] = "- Birth location: " . ($localFacts['birthLocation'] ?? '(missing)');
    $lines[] = "- Death date: " . ($localFacts['deathDate'] ?? '(missing)');
    $lines[] = "- Death location: " . ($localFacts['deathLocation'] ?? '(missing)');
    $lines[] = "- Marriages: " . count($localFacts['marriages']);
    $lines[] = "- Children: " . count($localFacts['children']);

    $lines[] = "";
    $lines[] = "## Extracted Facts (WikiTree)";
    $lines[] = "- Birth date: " . ($apiFacts['birthDate'] ?? '(missing)');
    $lines[] = "- Birth location: " . ($apiFacts['birthLocation'] ?? '(missing)');
    $lines[] = "- Death date: " . ($apiFacts['deathDate'] ?? '(missing)');
    $lines[] = "- Death location: " . ($apiFacts['deathLocation'] ?? '(missing)');
    $lines[] = "- Marriages: " . count($apiFacts['marriages']);
    $lines[] = "- Children: " . count($apiFacts['children']);

    return rtrim(implode("\n", $lines)) . "\n";
}

$options = parseArgs($argv);
if ($options['help'] || empty($options['profile'])) {
    showHelp();
    exit($options['help'] ? 0 : 1);
}

$profileId = $options['profile'];
if (!WikiTreeAPI::validateID($profileId)) {
    fwrite(STDERR, "Error: Invalid WikiTree ID format: $profileId\n");
    exit(1);
}

$repoRoot = realpath(__DIR__ . '/..');
if (!$repoRoot) {
    fwrite(STDERR, "Error: Could not resolve repository root.\n");
    exit(1);
}

$bioPath = $options['bio'] ?: ($repoRoot . '/' . $profileId . '.md');
if (!is_file($bioPath)) {
    fwrite(STDERR, "Error: Bio file not found: $bioPath\n");
    exit(1);
}

$logFile = $repoRoot . '/logs/wikitree_api_errors.log';
$api = new WikiTreeAPI($options['verbose'], $logFile);

$profileResponse = $api->fetchProfile($profileId);
if ($profileResponse === false || empty($profileResponse['profile'])) {
    fwrite(STDERR, "Error: Failed to fetch WikiTree profile: $profileId\n");
    exit(1);
}

$relativesResponse = $api->fetchRelatives($profileId, ['Parents', 'Children', 'Spouses', 'Siblings']);
$profile = $profileResponse['profile'];
if ($relativesResponse !== false && !empty($relativesResponse['items'][0]['person'])) {
    $profile = $relativesResponse['items'][0]['person'];
}

$bioText = readBioFile($bioPath);
$localFacts = parseLocalBioFacts($bioText);
$apiFacts = extractFactsFromApi($profile);

$strict = [];
$normalized = [];

compareField('Birth date', $localFacts['birthDate'], $apiFacts['birthDate'], $strict, $normalized, 'normalizeDate');
compareField('Birth location', $localFacts['birthLocation'], $apiFacts['birthLocation'], $strict, $normalized, 'normalizeLocation');
compareField('Death date', $localFacts['deathDate'], $apiFacts['deathDate'], $strict, $normalized, 'normalizeDate');
compareField('Death location', $localFacts['deathLocation'], $apiFacts['deathLocation'], $strict, $normalized, 'normalizeLocation');
compareMarriages($localFacts['marriages'], $apiFacts['marriages'], $strict, $normalized);
compareChildren($localFacts['children'], $apiFacts['children'], $strict, $normalized);

$familyName = $profile['LastNameAtBirth'] ?? $profile['LastNameCurrent'] ?? 'Unknown';
$familyBase = $repoRoot . '/ancestors/family';
[$familyDir, $resolvedFamilyName, $alternateFamilyName, $bothDirs] = resolveFamilyDirectory($familyBase, $familyName);
ensureDir($familyDir);

$outPath = $options['out'] ?: ($familyDir . '/wikitree-id-errata.md');

$report = buildReport(
    $profileId,
    $bioPath,
    $localFacts,
    $apiFacts,
    $strict,
    $normalized,
    [
        'familyDir' => $familyDir,
        'familyName' => $resolvedFamilyName,
        'alternateFamilyName' => $alternateFamilyName,
        'bothFamilyDirs' => $bothDirs
    ]
);

if (@file_put_contents($outPath, $report) === false) {
    fwrite(STDERR, "Error: Failed to write report: $outPath\n");
    exit(1);
}

echo "Wrote errata report to $outPath\n";
exit(0);
