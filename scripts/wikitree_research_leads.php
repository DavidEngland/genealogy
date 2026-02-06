#!/usr/bin/env php
<?php
/**
 * WikiTree Research Leads (API-based)
 *
 * Uses searchPerson to generate candidate leads (e.g., possible children)
 * for a given WikiTree profile. Saves markdown and raw JSON.
 *
 * Usage:
 *   php wikitree_research_leads.php --profile Brewer-708
 *   php wikitree_research_leads.php --profile Brewer-708 --out research-leads/Brewer-708.md
 *   php wikitree_research_leads.php --profile Brewer-708 --limit 100 --min-age 15 --max-age 60
 *   php wikitree_research_leads.php --profile Brewer-708 --location "Moore, North Carolina, United States"
 *
 * Options:
 *   --profile ID       WikiTree profile ID (required)
 *   --out FILE         Output markdown file (default: research-leads/{ID}-leads.md)
 *   --limit N          Max results to request (default: 50)
 *   --min-age N        Minimum child age vs parent birth year (default: 15)
 *   --max-age N        Maximum child age vs parent birth year (default: 60)
 *   --location TEXT    Birth location to use for child search (default: parent death or birth location)
 *   --verbose          Enable verbose API logging
 *   --help             Show this help message
 */

require_once __DIR__ . '/wikitree_api_client.php';

function parseArgs(array $argv): array {
    $options = [
        'profile' => null,
        'out' => null,
        'limit' => 50,
        'min-age' => 15,
        'max-age' => 60,
        'location' => null,
        'verbose' => false,
        'help' => false
    ];

    for ($i = 1; $i < count($argv); $i++) {
        switch ($argv[$i]) {
            case '--profile':
                $options['profile'] = $argv[++$i] ?? null;
                break;
            case '--out':
                $options['out'] = $argv[++$i] ?? null;
                break;
            case '--limit':
                $options['limit'] = (int)($argv[++$i] ?? 50);
                break;
            case '--min-age':
                $options['min-age'] = (int)($argv[++$i] ?? 15);
                break;
            case '--max-age':
                $options['max-age'] = (int)($argv[++$i] ?? 60);
                break;
            case '--location':
                $options['location'] = $argv[++$i] ?? null;
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
    echo <<<HELP
WikiTree Research Leads (API-based)

Usage:
  php wikitree_research_leads.php --profile PROFILE_ID [OPTIONS]

Options:
  --profile ID       WikiTree profile ID (required)
  --out FILE         Output markdown file (default: research-leads/{ID}-leads.md)
  --limit N          Max results to request (default: 50)
  --min-age N        Minimum child age vs parent birth year (default: 15)
  --max-age N        Maximum child age vs parent birth year (default: 60)
  --location TEXT    Birth location to use for child search (default: parent death or birth location)
  --verbose          Enable verbose API logging
  --help, -h         Show this help message

HELP;
}

function ensureDir(string $path): void {
    if (!is_dir($path)) {
        @mkdir($path, 0755, true);
    }
}

function loadCachedProfile(string $profilesDir, string $profileId): ?array {
    $firstLetter = strtoupper(substr($profileId, 0, 1));
    $filePath = "{$profilesDir}/{$firstLetter}/{$profileId}.json";

    if (!is_file($filePath)) {
        return null;
    }

    $json = file_get_contents($filePath);
    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);
    if (!is_array($data)) {
        return null;
    }

    return $data['profile'] ?? null;
}

function extractYear(?string $date): ?int {
    if (!$date || $date === '0000-00-00') {
        return null;
    }
    if (preg_match('/^(\d{4})/', $date, $matches)) {
        return (int)$matches[1];
    }
    return null;
}

function buildSearchParams(array $profile, int $minAge, int $maxAge, ?string $location, int $limit): array {
    $lastName = $profile['LastNameAtBirth'] ?? $profile['LastNameCurrent'] ?? null;
    $birthYear = extractYear($profile['BirthDate'] ?? null);
    $birthLocation = $profile['BirthLocation'] ?? '';
    $deathLocation = $profile['DeathLocation'] ?? '';

    $childBirthLocation = $location ?: ($deathLocation ?: $birthLocation);

    $startYear = $birthYear ? $birthYear + $minAge : null;
    $endYear = $birthYear ? $birthYear + $maxAge : null;

    $params = [
        'LastName' => $lastName,
        'BirthLocation' => $childBirthLocation,
        'BirthDate' => $startYear ? sprintf('%04d-00-00', $startYear) : null,
        'BirthDateTo' => $endYear ? sprintf('%04d-00-00', $endYear) : null,
        'limit' => $limit
    ];

    return array_filter($params, function ($value) {
        return $value !== null && $value !== '';
    });
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

function formatPersonLabel(array $person): string {
    $id = $person['Name'] ?? $person['WikiTreeId'] ?? $person['Id'] ?? null;

    $first = $person['FirstName'] ?? '';
    $middle = $person['MiddleName'] ?? '';
    $last = $person['LastNameAtBirth'] ?? $person['LastNameCurrent'] ?? '';
    $real = $person['RealName'] ?? '';
    $nameParts = trim($real) !== '' ? $real : trim(implode(' ', array_filter([$first, $middle, $last])));

    if ($id && $nameParts) {
        return "[[{$id}|{$nameParts}]]";
    }
    if ($id) {
        return "[[{$id}|{$id}]]";
    }
    return $nameParts !== '' ? $nameParts : 'Unknown';
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

function formatLeadLine(array $person): string {
    $label = formatPersonLabel($person);

    $birth = formatVital($person['BirthDate'] ?? '', $person['BirthLocation'] ?? '');
    $death = formatVital($person['DeathDate'] ?? '', $person['DeathLocation'] ?? '');

    $parts = [];
    if ($birth !== '') {
        $parts[] = "b. {$birth}";
    }
    if ($death !== '') {
        $parts[] = "d. {$death}";
    }

    return $label . (empty($parts) ? '' : ' — ' . implode('; ', $parts));
}

function main(array $argv): void {
    $options = parseArgs($argv);

    if ($options['help']) {
        showHelp();
        exit(0);
    }

    if (empty($options['profile'])) {
        fwrite(STDERR, "Error: --profile is required\n");
        showHelp();
        exit(1);
    }

    $profileId = $options['profile'];
    if (!WikiTreeAPI::validateID($profileId)) {
        fwrite(STDERR, "Error: Invalid WikiTree ID format. Expected format: Surname-Number\n");
        exit(1);
    }

    $logFile = __DIR__ . '/../logs/wikitree_api_errors.log';
    $profilesDir = __DIR__ . '/../profiles';
    $leadsDir = __DIR__ . '/../research-leads';

    $api = new WikiTreeAPI($options['verbose'], $logFile);

    $profile = loadCachedProfile($profilesDir, $profileId);
    if (!$profile) {
        $data = $api->fetchProfile($profileId);
        if ($data === false || !isset($data['profile'])) {
            fwrite(STDERR, "Error: Failed to fetch profile {$profileId}\n");
            exit(1);
        }
        $profile = $data['profile'];
    }

    $params = buildSearchParams(
        $profile,
        $options['min-age'],
        $options['max-age'],
        $options['location'],
        $options['limit']
    );

    $response = $api->searchPerson($params);
    if ($response === false) {
        fwrite(STDERR, "Error: searchPerson failed\n");
        exit(1);
    }

    $results = extractSearchResults($response);

    ensureDir($leadsDir);

    $jsonPath = "{$leadsDir}/{$profileId}-searchPerson.json";
    file_put_contents($jsonPath, json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

    $outPath = $options['out'] ?: "{$leadsDir}/{$profileId}-leads.md";

    $lines = [];
    $lines[] = "# Research Leads for {$profileId}";
    $lines[] = "";
    $lines[] = "**Generated**: " . date('Y-m-d H:i:s');
    $lines[] = "";
    $lines[] = "## Profile Snapshot";
    $lines[] = "* Name: " . trim(implode(' ', array_filter([
        $profile['FirstName'] ?? '',
        $profile['MiddleName'] ?? '',
        $profile['LastNameAtBirth'] ?? $profile['LastNameCurrent'] ?? ''
    ])));
    $lines[] = "* Birth: " . formatVital($profile['BirthDate'] ?? '', $profile['BirthLocation'] ?? '');
    $lines[] = "* Death: " . formatVital($profile['DeathDate'] ?? '', $profile['DeathLocation'] ?? '');
    $lines[] = "";
    $lines[] = "## Search Parameters";
    foreach ($params as $key => $value) {
        $lines[] = "* {$key}: {$value}";
    }
    $lines[] = "";
    $lines[] = "## Candidate Children (searchPerson)";
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

    echo "Saved leads: {$outPath}\n";
    echo "Saved raw JSON: {$jsonPath}\n";
}

main($argv);
