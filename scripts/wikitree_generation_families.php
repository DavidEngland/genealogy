<?php
/**
 * WikiTree Generation Family Exporter (API-based)
 *
 * Builds generation-by-generation folders (oldest ancestor first),
 * including immediate family (parents, spouses, children) for each person.
 * Outputs JSON and Markdown per generation.
 *
 * Usage:
 *   php wikitree_generation_families.php --profile Brewer-708 --depth 6
 *   php wikitree_generation_families.php --profile Brewer-708,Lanier-56 --out generation-families
 *
 * @author David England
 * @date 2026-02-05
 */

require_once __DIR__ . '/wikitree_api_client.php';

$options = getopt('', ['profile:', 'depth:', 'out:', 'verbose', 'help']);

if (isset($options['help']) || empty($options['profile'])) {
    showHelp();
    exit(0);
}

$profileArg = $options['profile'];
$profileIds = array_filter(array_map('trim', explode(',', $profileArg)));
$depth = isset($options['depth']) ? (int)$options['depth'] : 6;
$outBase = $options['out'] ?? (__DIR__ . '/../generation-families');
$verbose = isset($options['verbose']);
$logFile = __DIR__ . '/../logs/wikitree_api_errors.log';

if ($depth < 1) {
    fwrite(STDERR, "Error: Invalid depth. Depth must be a positive integer.\n");
    exit(1);
}

foreach ($profileIds as $profileId) {
    if (!WikiTreeAPI::validateID($profileId)) {
        fwrite(STDERR, "Error: Invalid WikiTree ID format: $profileId\n");
        exit(1);
    }
}

$api = new WikiTreeAPI($verbose, $logFile);

foreach ($profileIds as $profileId) {
    echo "Processing $profileId (depth $depth)...\n";

    $ancestorsData = $api->fetchAncestors($profileId, $depth);
    if ($ancestorsData === false) {
        fwrite(STDERR, "Error: Failed to fetch ancestors for $profileId\n");
        continue;
    }

    $ancestorList = [];
    if (!empty($ancestorsData['ancestors']['ancestors']) && is_array($ancestorsData['ancestors']['ancestors'])) {
        $ancestorList = $ancestorsData['ancestors']['ancestors'];
    } elseif (!empty($ancestorsData['ancestors']) && is_array($ancestorsData['ancestors'])) {
        $ancestorList = $ancestorsData['ancestors'];
    }
    if (empty($ancestorList)) {
        fwrite(STDERR, "Error: No ancestor data returned for $profileId\n");
        continue;
    }

    $byId = [];
    foreach ($ancestorList as $person) {
        if (!empty($person['Id'])) {
            $byId[(int)$person['Id']] = $person;
        }
    }

    $rootId = findRootNumericId($ancestorList, $profileId);
    if ($rootId === null) {
        fwrite(STDERR, "Error: Could not locate root profile in ancestors list: $profileId\n");
        continue;
    }

    $genById = buildGenerationMap($byId, $rootId);
    if (empty($genById)) {
        fwrite(STDERR, "Error: Could not build generation map for $profileId\n");
        continue;
    }

    $maxGen = max($genById);
    $peopleByGen = groupPeopleByGeneration($genById);

    $rootDir = rtrim($outBase, '/');
    if (!is_dir($rootDir) && !@mkdir($rootDir, 0755, true)) {
        fwrite(STDERR, "Error: Failed to create output base directory: $rootDir\n");
        exit(1);
    }

    $profileDir = $rootDir . '/' . $profileId;
    if (!is_dir($profileDir) && !@mkdir($profileDir, 0755, true)) {
        fwrite(STDERR, "Error: Failed to create profile directory: $profileDir\n");
        exit(1);
    }

    $relativesCache = [];

    for ($gen = $maxGen; $gen >= 0; $gen--) {
        $genDir = $profileDir . '/gen-' . str_pad((string)$gen, 2, '0', STR_PAD_LEFT);
        if (!is_dir($genDir) && !@mkdir($genDir, 0755, true)) {
            fwrite(STDERR, "Error: Failed to create generation directory: $genDir\n");
            continue;
        }

        $peopleIds = $peopleByGen[$gen] ?? [];
        $peopleData = [];
        $mdLines = [];
        $mdLines[] = "# Generation $gen";
        $mdLines[] = "";
        $mdLines[] = "**Root:** [[$profileId]]";
        $mdLines[] = "";

        foreach ($peopleIds as $personId) {
            $person = $byId[$personId] ?? null;
            if (!$person) {
                continue;
            }

            $wikitreeId = $person['Name'] ?? null;
            if (!$wikitreeId) {
                continue;
            }

            $immediate = fetchImmediateFamily($api, $wikitreeId, $relativesCache);
            $profile = $immediate['profile'] ?? $person;

            $entry = [
                'wikitreeId' => $wikitreeId,
                'name' => formatPersonName($profile),
                'profile' => $profile,
                'parents' => $immediate['parents'] ?? [],
                'spouses' => $immediate['spouses'] ?? [],
                'children' => $immediate['children'] ?? []
            ];

            $peopleData[] = $entry;

            $personJsonPath = $genDir . '/' . $wikitreeId . '.json';
            $personJson = json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            @file_put_contents($personJsonPath, $personJson);

            $mdLines[] = "## " . formatPersonHeading($profile, $wikitreeId);
            $mdLines[] = "";

            $lifeLine = formatLifeLine($profile);
            if ($lifeLine) {
                $mdLines[] = $lifeLine;
                $mdLines[] = "";
            }

            $mdLines[] = formatRelativeSection("Parents", $entry['parents']);
            $mdLines[] = "";
            $mdLines[] = formatRelativeSection("Spouses", $entry['spouses']);
            $mdLines[] = "";
            $mdLines[] = formatRelativeSection("Children", $entry['children']);
            $mdLines[] = "";
        }

        $generationPayload = [
            'metadata' => [
                'rootId' => $profileId,
                'generation' => $gen,
                'depth' => $depth,
                'generated' => date('Y-m-d H:i:s'),
                'count' => count($peopleData)
            ],
            'people' => $peopleData
        ];

        $generationJsonPath = $genDir . '/generation.json';
        $generationJson = json_encode($generationPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        @file_put_contents($generationJsonPath, $generationJson);

        $generationMdPath = $genDir . '/generation.md';
        @file_put_contents($generationMdPath, rtrim(implode("\n", $mdLines)) . "\n");

        echo "  Wrote generation $gen to $genDir\n";
    }
}

echo "Done.\n";
exit(0);

function showHelp(): void {
    echo "WikiTree Generation Family Exporter\n\n";
    echo "Usage:\n";
    echo "  php wikitree_generation_families.php --profile WIKITREE-ID[,WIKITREE-ID] [OPTIONS]\n\n";
    echo "Options:\n";
    echo "  --profile ID      WikiTree ID(s), comma-separated (required)\n";
    echo "  --depth N         Ancestor depth (default: 6)\n";
    echo "  --out DIR         Output base directory (default: generation-families)\n";
    echo "  --verbose         Enable verbose API logging\n";
    echo "  --help            Show this help message\n";
}

function findRootNumericId(array $ancestorList, string $profileId): ?int {
    foreach ($ancestorList as $person) {
        if (($person['Name'] ?? null) === $profileId && !empty($person['Id'])) {
            return (int)$person['Id'];
        }
    }

    $first = $ancestorList[0] ?? null;
    if ($first && !empty($first['Id'])) {
        return (int)$first['Id'];
    }

    return null;
}

function buildGenerationMap(array $byId, int $rootId): array {
    if (!isset($byId[$rootId])) {
        return [];
    }

    $genById = [$rootId => 0];
    $queue = [[$rootId, 0]];

    while (!empty($queue)) {
        [$currentId, $currentGen] = array_shift($queue);
        $person = $byId[$currentId] ?? null;
        if (!$person) {
            continue;
        }

        $parents = [];
        if (!empty($person['Father'])) {
            $parents[] = (int)$person['Father'];
        }
        if (!empty($person['Mother'])) {
            $parents[] = (int)$person['Mother'];
        }

        foreach ($parents as $parentId) {
            if (!isset($byId[$parentId])) {
                continue;
            }
            if (!isset($genById[$parentId])) {
                $genById[$parentId] = $currentGen + 1;
                $queue[] = [$parentId, $currentGen + 1];
            }
        }
    }

    return $genById;
}

function groupPeopleByGeneration(array $genById): array {
    $grouped = [];
    foreach ($genById as $personId => $gen) {
        if (!isset($grouped[$gen])) {
            $grouped[$gen] = [];
        }
        $grouped[$gen][] = $personId;
    }

    foreach ($grouped as $gen => $people) {
        sort($grouped[$gen]);
    }

    return $grouped;
}

function fetchImmediateFamily(WikiTreeAPI $api, string $wikitreeId, array &$cache): array {
    if (isset($cache[$wikitreeId])) {
        return $cache[$wikitreeId];
    }

    $response = $api->fetchRelatives($wikitreeId, ['Parents', 'Children', 'Spouses']);
    if ($response === false || empty($response['items'][0]['person'])) {
        $profileResponse = $api->fetchProfile($wikitreeId);
        $profile = $profileResponse['profile'] ?? [];
        $cache[$wikitreeId] = [
            'profile' => $profile,
            'parents' => $profile['Parents'] ?? [],
            'spouses' => $profile['Spouses'] ?? [],
            'children' => $profile['Children'] ?? []
        ];
        return $cache[$wikitreeId];
    }

    $person = $response['items'][0]['person'];

    $cache[$wikitreeId] = [
        'profile' => $person,
        'parents' => $person['Parents'] ?? [],
        'spouses' => $person['Spouses'] ?? [],
        'children' => $person['Children'] ?? []
    ];

    return $cache[$wikitreeId];
}

function formatPersonHeading(array $profile, string $wikitreeId): string {
    $name = formatPersonName($profile);
    return $name . " ([[$wikitreeId]])";
}

function formatLifeLine(array $profile): ?string {
    $birth = [];
    if (!empty($profile['BirthDate']) && $profile['BirthDate'] !== '0000-00-00') {
        $birth[] = formatDate($profile['BirthDate']);
    }
    if (!empty($profile['BirthLocation'])) {
        $birth[] = $profile['BirthLocation'];
    }

    $death = [];
    if (!empty($profile['DeathDate']) && $profile['DeathDate'] !== '0000-00-00') {
        $death[] = formatDate($profile['DeathDate']);
    }
    if (!empty($profile['DeathLocation'])) {
        $death[] = $profile['DeathLocation'];
    }

    if (empty($birth) && empty($death)) {
        return null;
    }

    $parts = [];
    if (!empty($birth)) {
        $parts[] = "Born: " . implode(', ', $birth);
    }
    if (!empty($death)) {
        $parts[] = "Died: " . implode(', ', $death);
    }

    return '**' . implode(' | ', $parts) . '**';
}

function formatRelativeSection(string $label, array $relatives): string {
    $lines = [];
    $lines[] = "### $label";

    if (empty($relatives)) {
        $lines[] = "- (none listed)";
        return implode("\n", $lines);
    }

    foreach ($relatives as $relative) {
        $name = formatPersonName($relative);
        $wikitreeId = $relative['Name'] ?? null;
        if ($wikitreeId) {
            $lines[] = "- [[$wikitreeId|$name]]";
        } else {
            $lines[] = "- $name";
        }
    }

    return implode("\n", $lines);
}

function formatPersonName(array $person): string {
    $parts = [];

    if (!empty($person['FirstName'])) {
        $parts[] = $person['FirstName'];
    }
    if (!empty($person['MiddleName'])) {
        $parts[] = $person['MiddleName'];
    }

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

function formatDate(string $date): string {
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }

    $parts = explode('-', $date);
    $year = $parts[0] ?? '';
    $month = $parts[1] ?? '00';
    $day = $parts[2] ?? '00';

    if ($month === '00') {
        return $year;
    }

    if ($day === '00') {
        return $year . '-' . $month;
    }

    return $date;
}
