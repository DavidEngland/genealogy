<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * Rank FamilySearch CSV records for relevance.
 * Usage:
 *   php scripts/rank-sources.php --in search-results/JamesHartgrave.csv --out sources/JamesHartgrave-rank.json
 *   php scripts/rank-sources.php --in search-results/JamesHartgrave.csv --out-md sources/JamesHartgrave-rank.md --target-name "James Hartgrave"
 *
 * Options:
 *   --in <file.csv>          Input CSV (FamilySearch search results)
 *   --out <file.json>        Output JSON (ranked list)
 *   --out-md <file.md>       Output Markdown (ranked list)
 *   --target-name <name>     Target name for similarity scoring
 *   --config <file.json>     Optional ranking config (weights/keywords)
 *   --top <n>                Limit output to top N records
 *   --min-score <n>          Minimum score threshold
 */

declare(strict_types=1);

function usage(): void {
    fwrite(STDERR, "Usage: php scripts/rank-sources.php --in <input.csv> [--out <output.json>] [--out-md <output.md>] [--target-name <name>] [--config <file.json>] [--top <n>] [--min-score <n>]\n");
}

$options = getopt('', ['in:', 'out:', 'out-md:', 'target-name:', 'config:', 'top:', 'min-score:', 'help']);
if (isset($options['help'])) {
    usage();
    exit(0);
}

if (!isset($options['in'])) {
    usage();
    exit(1);
}

$inFile = $options['in'];
$outFile = $options['out'] ?? '';
$outMdFile = $options['out-md'] ?? '';
$targetName = isset($options['target-name']) ? trim((string)$options['target-name']) : '';
$topN = isset($options['top']) ? max(1, (int)$options['top']) : 0;
$minScore = isset($options['min-score']) ? (float)$options['min-score'] : null;

if (!file_exists($inFile)) {
    fwrite(STDERR, "Error: Input file not found: {$inFile}\n");
    exit(1);
}

$defaultConfig = [
    'weights' => [
        'roleInRecord' => [
            'Principal' => 5,
            'Groom' => 4,
            'Bride' => 4,
            'Mother' => 3,
            'Father' => 3,
            'Witness' => 1,
        ],
        'relationshipToHead' => [
            'Spouse' => 2,
            'Child' => 1,
            'Parent' => 1,
        ],
        'keywords' => [
            'census' => 3,
            'marriage' => 4,
            'birth' => 4,
            'death' => 4,
            'probate' => 3,
            'military' => 3,
            'index' => -3,
            'find a grave' => -4,
            'newspaper' => -2,
        ],
        'hasEventDate' => 1,
        'nameExact' => 10,
        'nameContains' => 6,
        'lastNameMatch' => 3,
    ],
];

function loadConfig(string $configPath, array $defaultConfig): array {
    if ($configPath === '' || !file_exists($configPath)) {
        return $defaultConfig;
    }

    $json = file_get_contents($configPath);
    $config = json_decode($json, true);
    if (!is_array($config)) {
        fwrite(STDERR, "Warning: Invalid JSON in config file: {$configPath}. Using defaults.\n");
        return $defaultConfig;
    }

    return array_replace_recursive($defaultConfig, $config);
}

function normalizeName(string $name): string {
    $name = strtolower(trim($name));
    $name = preg_replace('/[^a-z\s]/', ' ', $name);
    $name = preg_replace('/\s+/', ' ', $name);
    return trim($name);
}

function getLastName(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    return $parts ? strtolower(end($parts)) : '';
}

$config = loadConfig($options['config'] ?? '', $defaultConfig);
$weights = $config['weights'] ?? $defaultConfig['weights'];

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

    if (count($row) !== count($header)) {
        continue;
    }

    $record = array_combine($header, $row);

    preg_match('/ark:\/61903\/1:1:([A-Z0-9-]+)/', $record['arkId'] ?? '', $matches);
    if (!$matches) {
        continue;
    }
    $arkId = $matches[1];
    if (isset($seen[$arkId])) {
        continue;
    }
    $seen[$arkId] = true;

    $records[] = $record;
}

fclose($handle);

$ranked = [];
$normalizedTarget = $targetName !== '' ? normalizeName($targetName) : '';
$targetLastName = $normalizedTarget !== '' ? getLastName($normalizedTarget) : '';

foreach ($records as $rec) {
    $score = 0;
    $reasons = [];

    $fullName = trim($rec['fullName'] ?? '');
    $collectionName = trim($rec['collectionName'] ?? '');
    $roleInRecord = trim($rec['roleInRecord'] ?? '');
    $relationshipToHead = trim($rec['relationshipToHead'] ?? '');

    // Role weight
    if ($roleInRecord !== '' && isset($weights['roleInRecord'][$roleInRecord])) {
        $score += $weights['roleInRecord'][$roleInRecord];
        $reasons[] = "role={$roleInRecord}";
    }

    // Relationship weight
    if ($relationshipToHead !== '' && isset($weights['relationshipToHead'][$relationshipToHead])) {
        $score += $weights['relationshipToHead'][$relationshipToHead];
        $reasons[] = "relationship={$relationshipToHead}";
    }

    // Keyword weights
    if ($collectionName !== '') {
        foreach ($weights['keywords'] as $kw => $kwScore) {
            if ($kw !== '' && stripos($collectionName, (string)$kw) !== false) {
                $score += $kwScore;
                $reasons[] = "keyword={$kw}({$kwScore})";
            }
        }
    }

    // Name similarity
    if ($normalizedTarget !== '' && $fullName !== '') {
        $normalizedFull = normalizeName($fullName);
        if ($normalizedFull === $normalizedTarget) {
            $score += $weights['nameExact'];
            $reasons[] = 'name=exact';
        } elseif (stripos($normalizedFull, $normalizedTarget) !== false || stripos($normalizedTarget, $normalizedFull) !== false) {
            $score += $weights['nameContains'];
            $reasons[] = 'name=contains';
        }

        $lastName = getLastName($normalizedFull);
        if ($lastName !== '' && $lastName === $targetLastName) {
            $score += $weights['lastNameMatch'];
            $reasons[] = 'name=last';
        }
    }

    // Event date weight
    $eventDate = $rec['marriageLikeDate'] ?: $rec['birthLikeDate'] ?: $rec['deathLikeDate'] ?: $rec['residenceDate'] ?? '';
    if ($eventDate !== '' && isset($weights['hasEventDate'])) {
        $score += $weights['hasEventDate'];
        $reasons[] = 'hasEventDate';
    }

    $arkId = preg_match('/ark:\/61903\/1:1:([A-Z0-9-]+)/', $rec['arkId'] ?? '', $m) ? $m[1] : '';
    $arkUrl = $arkId !== '' ? "https://www.familysearch.org/ark:/61903/1:1:$arkId" : '';

    $eventPlace = $rec['marriageLikePlaceText'] ?: $rec['birthLikePlaceText'] ?: $rec['deathLikePlaceText'] ?: $rec['residencePlaceText'] ?? '';

    $ranked[] = [
        'score' => $score,
        'reasons' => $reasons,
        'collectionName' => $collectionName,
        'fullName' => $fullName,
        'roleInRecord' => $roleInRecord,
        'relationshipToHead' => $relationshipToHead,
        'eventDate' => $eventDate,
        'eventPlace' => $eventPlace,
        'arkId' => $arkId,
        'arkUrl' => $arkUrl,
    ];
}

usort($ranked, function ($a, $b) {
    if ($a['score'] === $b['score']) {
        return strcmp($a['collectionName'], $b['collectionName']);
    }
    return $b['score'] <=> $a['score'];
});

if ($minScore !== null) {
    $ranked = array_values(array_filter($ranked, function ($row) use ($minScore) {
        return $row['score'] >= $minScore;
    }));
}

if ($topN > 0) {
    $ranked = array_slice($ranked, 0, $topN);
}

// Add rank index
foreach ($ranked as $i => $row) {
    $ranked[$i]['rank'] = $i + 1;
}

if ($outFile !== '') {
    file_put_contents($outFile, json_encode($ranked, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "Wrote JSON: {$outFile}\n";
} else {
    echo json_encode($ranked, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

if ($outMdFile !== '') {
    $md = "== Ranked Sources ==\n\n";
    foreach ($ranked as $row) {
        $title = $row['collectionName'] ?: 'FamilySearch';
        $ark = $row['arkUrl'] ?: '';
        $details = [];
        if ($row['fullName'] !== '') {
            $details[] = "Entry for " . $row['fullName'];
        }
        if ($row['eventDate'] !== '') {
            $details[] = $row['eventDate'];
        }
        if ($row['eventPlace'] !== '') {
            $details[] = $row['eventPlace'];
        }
        $reasonText = !empty($row['reasons']) ? " (" . implode('; ', $row['reasons']) . ")" : '';

        $md .= "* ({$row['rank']}) [Score {$row['score']}] \"{$title},\" database, [{$ark} FamilySearch], " . implode(', ', $details) . ".{$reasonText}\n";
    }
    file_put_contents($outMdFile, $md);
    echo "Wrote Markdown: {$outMdFile}\n";
}

?>
