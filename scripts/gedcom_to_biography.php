<?php
// GEDCOM to WikiTree-style Biography Markdown Generator
// Usage:
//   php scripts/gedcom_to_biography.php --input GEDs/example.ged [--person @I123@] [--out path]
// Examples:
//   # Single person to a specific file
//   php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --person @I123@ --out Hargroves.md
//   # All individuals to a directory (one .md per person)
//   php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --out sources/Hargroves
//
// Behavior:
// - If --person is provided: writes a single biography. If --out ends with .md, that file is written; otherwise treated as an output directory (default: sources).
// - If --person is omitted: writes one markdown file per person into the output directory (default: sources).

declare(strict_types=1);

function usage(): void {
    $msg = <<<TXT
Usage: php scripts/gedcom_to_biography.php --input <file.ged> [--person @I123@] [--out path]\n
Options:
  --input   Path to GEDCOM file (required)
  --person  Specific individual ID (e.g., @I135@). If omitted, all people are exported.
  --out     Output file (when --person is set) or output directory (default: sources)

Examples:
  Single person -> file:
    php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --person @I123@ --out Hargroves.md
  All individuals -> directory (one .md per person):
    php scripts/gedcom_to_biography.php --input GEDs/Hargroves.ged --out sources/Hargroves
TXT;
    fwrite(STDERR, $msg);
    exit(1);
}

function parseArgs(array $argv): array {
    $args = ['input' => null, 'person' => null, 'out' => null];
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        if ($arg === '--input' && isset($argv[$i + 1])) {
            $args['input'] = $argv[++$i];
        } elseif ($arg === '--person' && isset($argv[$i + 1])) {
            $args['person'] = $argv[++$i];
        } elseif ($arg === '--out' && isset($argv[$i + 1])) {
            $args['out'] = $argv[++$i];
        }
    }
    if ($args['input'] === null) {
        usage();
    }
    return $args;
}

function loadGedcom(string $path): array {
    $data = @file($path, FILE_IGNORE_NEW_LINES);
    if ($data === false) {
        fwrite(STDERR, "Error: Unable to read GEDCOM file: {$path}\n");
        exit(2);
    }
    return $data;
}

function parseGedcom(array $lines): array {
    $individuals = [];
    $families = [];

    $currentType = null; // 'INDI' or 'FAM'
    $currentId = null;
    $currentEvent = null;

    foreach ($lines as $line) {
        // Pattern: level [@X@] TAG [value]
        if (!preg_match('/^(\d+)\s+(?:(@[^@]+@)\s+)?([^\s]+)\s*(.*)$/', $line, $m)) {
            continue;
        }
        $level = (int)$m[1];
        $pointer = $m[2] ?? null;
        $tag = $m[3];
        $value = trim($m[4] ?? '');

        if ($level === 0) {
            $currentEvent = null;
            if ($tag === 'INDI') {
                $currentType = 'INDI';
                $currentId = $pointer;
                $individuals[$currentId] = $individuals[$currentId] ?? [
                    'id' => $currentId,
                    'name' => '',
                    'given' => '',
                    'surname' => '',
                    'sex' => '',
                    'events' => [],
                    'fams' => [],
                    'famc' => null,
                    'notes' => [],
                ];
            } elseif ($tag === 'FAM') {
                $currentType = 'FAM';
                $currentId = $pointer;
                $families[$currentId] = $families[$currentId] ?? [
                    'id' => $currentId,
                    'husb' => null,
                    'wife' => null,
                    'children' => [],
                    'events' => [],
                ];
            } else {
                $currentType = null;
                $currentId = null;
            }
            continue;
        }

        if ($currentType === 'INDI' && $currentId !== null) {
            if ($level === 1) {
                $currentEvent = null;
                switch ($tag) {
                    case 'NAME':
                        $individuals[$currentId]['name'] = $value;
                        break;
                    case 'GIVN':
                        $individuals[$currentId]['given'] = $value;
                        break;
                    case 'SURN':
                        $individuals[$currentId]['surname'] = $value;
                        break;
                    case 'SEX':
                        $individuals[$currentId]['sex'] = $value;
                        break;
                    case 'FAMS':
                        $individuals[$currentId]['fams'][] = $value;
                        break;
                    case 'FAMC':
                        $individuals[$currentId]['famc'] = $value;
                        break;
                    case 'NOTE':
                        $individuals[$currentId]['notes'][] = $value;
                        break;
                    case 'BIRT':
                    case 'DEAT':
                    case 'CHR':
                    case 'BURI':
                        $currentEvent = $tag;
                        $individuals[$currentId]['events'][$currentEvent] = $individuals[$currentId]['events'][$currentEvent] ?? ['date' => '', 'place' => ''];
                        break;
                }
            } elseif ($level >= 2 && $currentEvent !== null) {
                if ($tag === 'DATE') {
                    $individuals[$currentId]['events'][$currentEvent]['date'] = $value;
                } elseif ($tag === 'PLAC') {
                    $individuals[$currentId]['events'][$currentEvent]['place'] = $value;
                } elseif ($tag === 'CONT' || $tag === 'CONC') {
                    // Append to last note block when inside an event note
                    $lastNoteIndex = count($individuals[$currentId]['notes']) - 1;
                    if ($lastNoteIndex >= 0) {
                        $sep = ($tag === 'CONT') ? "\n" : '';
                        $individuals[$currentId]['notes'][$lastNoteIndex] .= $sep . $value;
                    }
                }
            }
        } elseif ($currentType === 'FAM' && $currentId !== null) {
            if ($level === 1) {
                $currentEvent = null;
                switch ($tag) {
                    case 'HUSB':
                        $families[$currentId]['husb'] = $value;
                        break;
                    case 'WIFE':
                        $families[$currentId]['wife'] = $value;
                        break;
                    case 'CHIL':
                        $families[$currentId]['children'][] = $value;
                        break;
                    case 'MARR':
                        $currentEvent = 'MARR';
                        $families[$currentId]['events']['MARR'] = $families[$currentId]['events']['MARR'] ?? ['date' => '', 'place' => ''];
                        break;
                }
            } elseif ($level >= 2 && $currentEvent !== null) {
                if ($tag === 'DATE') {
                    $families[$currentId]['events'][$currentEvent]['date'] = $value;
                } elseif ($tag === 'PLAC') {
                    $families[$currentId]['events'][$currentEvent]['place'] = $value;
                }
            }
        }
    }

    return [$individuals, $families];
}

function displayName(array $person): string {
    $given = trim($person['given'] ?? '');
    $surname = trim($person['surname'] ?? '');
    if ($given !== '' && $surname !== '') {
        return $given . ' ' . $surname;
    }
    $name = trim($person['name'] ?? '');
    if ($name !== '') {
        return str_replace('/', '', $name);
    }
    return $person['id'] ?? 'Unknown';
}

function eventText(array $event): string {
    $date = trim($event['date'] ?? '');
    $place = trim($event['place'] ?? '');
    if ($date !== '' && $place !== '') {
        return "on {$date} in {$place}";
    }
    if ($date !== '') {
        return "on {$date}";
    }
    if ($place !== '') {
        return "in {$place}";
    }
    return '';
}

function yearFromDate(string $date): string {
    if (preg_match('/(\d{4})/', $date, $m)) {
        return $m[1];
    }
    return '';
}

function buildOpening(array $person, array $families): string {
    $name = displayName($person);
    $birth = $person['events']['BIRT'] ?? null;
    $birthText = $birth ? eventText($birth) : '';
    $death = $person['events']['DEAT'] ?? null;
    $deathYear = $death ? yearFromDate($death['date'] ?? '') : '';
    $birthYear = $birth ? yearFromDate($birth['date'] ?? '') : '';

    $lifeSpan = '';
    if ($birthYear !== '' || $deathYear !== '') {
        $lifeSpan = ' (' . ($birthYear ?: '?') . ' – ' . ($deathYear ?: '?') . ')';
    }

    $line = "'''{$name}'''{$lifeSpan}";
    if ($birthText !== '') {
        $line .= " was born {$birthText}.";
    } else {
        $line .= ' is recorded in this GEDCOM file.';
    }
    return $line;
}

function buildParentage(array $person, array $families, array $individuals): ?string {
    $famc = $person['famc'] ?? null;
    if (!$famc || !isset($families[$famc])) {
        return null;
    }
    $fam = $families[$famc];
    $parents = [];
    if (!empty($fam['husb']) && isset($individuals[$fam['husb']])) {
        $parents[] = "'''" . displayName($individuals[$fam['husb']]) . "'''";
    }
    if (!empty($fam['wife']) && isset($individuals[$fam['wife']])) {
        $parents[] = "'''" . displayName($individuals[$fam['wife']]) . "'''";
    }
    if (empty($parents)) {
        return null;
    }
    $relation = ($person['sex'] ?? '') === 'F' ? 'daughter' : 'son';
    return ($relation === 'son' ? 'He' : 'She') . ' was the ' . $relation . ' of ' . implode(' and ', $parents) . '.';
}

function buildMarriageSections(array $person, array $families, array $individuals): array {
    $sections = [];
    $lines = [];
    foreach ($person['fams'] as $famId) {
        if (!isset($families[$famId])) continue;
        $fam = $families[$famId];
        $spouseId = null;
        if (($fam['husb'] ?? null) === $person['id']) {
            $spouseId = $fam['wife'] ?? null;
        } elseif (($fam['wife'] ?? null) === $person['id']) {
            $spouseId = $fam['husb'] ?? null;
        }
        $spouseName = $spouseId && isset($individuals[$spouseId]) ? "'''" . displayName($individuals[$spouseId]) . "'''" : 'an unknown spouse';
        $marr = $fam['events']['MARR'] ?? null;
        $marrText = $marr ? eventText($marr) : '';
        $sentence = $spouseName;
        if ($marrText !== '') {
            $sentence = "Married {$spouseName} {$marrText}.";
        } else {
            $sentence = "Married {$spouseName}.";
        }
        $lines[] = $sentence;
    }
    if (!empty($lines)) {
        $sections['=== Marriage ==='] = $lines;
    }
    return $sections;
}

function buildChildrenSection(array $person, array $families, array $individuals): ?array {
    $children = [];
    foreach ($person['fams'] as $famId) {
        if (!isset($families[$famId])) continue;
        foreach ($families[$famId]['children'] as $childId) {
            if (!isset($individuals[$childId])) continue;
            $child = $individuals[$childId];
            $name = "'''" . displayName($child) . "'''";
            $birthYear = '';
            if (isset($child['events']['BIRT'])) {
                $birthYear = yearFromDate($child['events']['BIRT']['date'] ?? '');
            }
            if ($birthYear !== '') {
                $children[] = "* {$name} (b. {$birthYear})";
            } else {
                $children[] = "* {$name}";
            }
        }
    }
    if (empty($children)) {
        return null;
    }
    $title = '=== Children ===';
    array_unshift($children, 'Children (from linked family records):');
    return [$title => $children];
}

function buildDeathSection(array $person): ?array {
    if (!isset($person['events']['DEAT'])) {
        return null;
    }
    $deathText = eventText($person['events']['DEAT']);
    if ($deathText === '') {
        return null;
    }
    return ['=== Death ===' => ["Died {$deathText}."]];
}

function buildBiography(array $person, array $families, array $individuals): string {
    $out = [];
    $out[] = '== Biography ==';
    $out[] = '';
    $out[] = buildOpening($person, $families);

    $parentLine = buildParentage($person, $families, $individuals);
    if ($parentLine) {
        $out[] = $parentLine;
    }

    $sections = [];
    $sections = array_merge($sections, buildMarriageSections($person, $families, $individuals));
    $childrenSection = buildChildrenSection($person, $families, $individuals);
    if ($childrenSection) {
        $sections = array_merge($sections, $childrenSection);
    }
    $deathSection = buildDeathSection($person);
    if ($deathSection) {
        $sections = array_merge($sections, $deathSection);
    }

    if (!empty($sections)) {
        foreach ($sections as $header => $lines) {
            $out[] = '';
            $out[] = $header;
            foreach ($lines as $line) {
                $out[] = $line;
            }
        }
    }

    $out[] = '';
    $out[] = '== Sources ==';
    $out[] = '* Information synthesized from GEDCOM file import.';

    return implode("\n", $out) . "\n";
}

function ensureDir(string $dir): void {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            fwrite(STDERR, "Error: Unable to create output directory: {$dir}\n");
            exit(3);
        }
    }
}

function outputPathForPerson(string $baseDir, array $person): string {
    $name = displayName($person);
    // Sanitize for filesystem
    $safe = preg_replace('/[^A-Za-z0-9_.-]+/', '-', $name);
    $safe = trim($safe, '-');
    if ($safe === '') {
        $safe = trim($person['id'], '@');
    }
    return rtrim($baseDir, '/'). "/{$safe}.md";
}

// Main
$args = parseArgs($argv);
$inputPath = $args['input'];
$personId = $args['person'];
$outArg = $args['out'];

if (!file_exists($inputPath)) {
    fwrite(STDERR, "Error: Input file not found: {$inputPath}\n");
    exit(4);
}

$lines = loadGedcom($inputPath);
[$individuals, $families] = parseGedcom($lines);

if ($personId !== null && !isset($individuals[$personId])) {
    fwrite(STDERR, "Error: Person ID not found in GEDCOM: {$personId}\n");
    exit(5);
}

if ($personId !== null) {
    $target = $individuals[$personId];
    $md = buildBiography($target, $families, $individuals);
    if ($outArg !== null && preg_match('/\.md$/i', $outArg)) {
        $outFile = $outArg;
        $outDir = dirname($outFile);
        ensureDir($outDir);
    } else {
        $outDir = $outArg ?? 'sources';
        ensureDir($outDir);
        $outFile = outputPathForPerson($outDir, $target);
    }
    if (@file_put_contents($outFile, $md) === false) {
        fwrite(STDERR, "Error: Failed to write output to {$outFile}\n");
        exit(6);
    }
    fwrite(STDOUT, "Wrote {$outFile}\n");
    exit(0);
}

// Batch: all individuals
$outDir = $outArg ?? 'sources';
ensureDir($outDir);
$count = 0;
foreach ($individuals as $person) {
    $md = buildBiography($person, $families, $individuals);
    $outFile = outputPathForPerson($outDir, $person);
    if (@file_put_contents($outFile, $md) === false) {
        fwrite(STDERR, "Error: Failed to write output to {$outFile}\n");
        exit(6);
    }
    $count++;
}
fwrite(STDOUT, "Wrote {$count} biographies to {$outDir}\n");

?>
