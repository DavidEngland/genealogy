<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
// Batch process genealogy CSV files to generate source markdown
// Usage: php scripts/batch-sources.php [OPTIONS]
// Examples:
//   php scripts/batch-sources.php                    # Process all search-results/*.csv files
//   php scripts/batch-sources.php --dry-run          # Preview without writing files
//   php scripts/batch-sources.php --config my-rules.json
//   php scripts/batch-sources.php --filter-keywords "Find a Grave,Index" --skip

declare(strict_types=1);

function usage(): void {
    fwrite(STDERR, "Usage: php scripts/batch-sources.php [OPTIONS]\n\n");
    fwrite(STDERR, "Batch process CSV files in search-results/ folder to generate markdown sources.\n");
    fwrite(STDERR, "CSV files are matched to output markdown filenames (e.g., JamesHartgrave.csv → JamesHartgrave-sources.md).\n\n");
    fwrite(STDERR, "Options:\n");
    fwrite(STDERR, "  --dry-run                    Show what would be processed without writing files\n");
    fwrite(STDERR, "  --config <file.json>         Load filtering rules from JSON config file\n");
    fwrite(STDERR, "  --only-principal             Only include Principal records (default filter)\n");
    fwrite(STDERR, "  --include-all                Include all records (override --only-principal)\n");
    fwrite(STDERR, "  --skip-keywords <csv>        Skip rows where collectionName contains keyword\n");
    fwrite(STDERR, "  --exclude-families           Skip records with relationshipToHead (family members)\n");
    fwrite(STDERR, "  --pattern <glob>             Process only CSV files matching pattern (default: search-results/*.csv)\n");
    fwrite(STDERR, "  --verbose                    Show detailed output for each file\n");
    fwrite(STDERR, "  --help                       Show this message\n");
}

function findSearchResultsFolder(): string {
    // Try repo root / search-results
    $repoRoot = dirname(__DIR__);
    $paths = [
        $repoRoot . DIRECTORY_SEPARATOR . 'search-results',
        __DIR__ . DIRECTORY_SEPARATOR . 'search-results',
        getcwd() . DIRECTORY_SEPARATOR . 'search-results',
    ];
    
    foreach ($paths as $p) {
        if (is_dir($p)) {
            return $p;
        }
    }
    
    fwrite(STDERR, "Error: Could not find search-results folder. Tried:\n");
    foreach ($paths as $p) {
        fwrite(STDERR, "  - {$p}\n");
    }
    exit(1);
}

function findSourcesFolder(): string {
    // Try repo root / sources
    $repoRoot = dirname(__DIR__);
    $paths = [
        $repoRoot . DIRECTORY_SEPARATOR . 'sources',
        __DIR__ . DIRECTORY_SEPARATOR . 'sources',
        getcwd() . DIRECTORY_SEPARATOR . 'sources',
    ];
    
    foreach ($paths as $p) {
        if (is_dir($p)) {
            return $p;
        }
    }
    
    // Use first writable path
    foreach ($paths as $p) {
        if (is_writable(dirname($p))) {
            return $p;
        }
    }
    
    fwrite(STDERR, "Error: Could not find writable sources folder.\n");
    exit(1);
}

function parseArgs(array $argv): array {
    $options = [
        'dry_run' => false,
        'config' => null,
        'only_principal' => true,
        'skip_keywords' => [],
        'exclude_families' => false,
        'pattern' => 'search-results/*.csv',
        'verbose' => false,
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        $arg = $argv[$i];
        
        if ($arg === '--help') {
            usage();
            exit(0);
        } elseif ($arg === '--dry-run') {
            $options['dry_run'] = true;
        } elseif ($arg === '--only-principal') {
            $options['only_principal'] = true;
        } elseif ($arg === '--include-all') {
            $options['only_principal'] = false;
        } elseif ($arg === '--config' && isset($argv[$i + 1])) {
            $options['config'] = $argv[++$i];
        } elseif ($arg === '--skip-keywords' && isset($argv[$i + 1])) {
            $keywords = $argv[++$i];
            $options['skip_keywords'] = array_map('trim', explode(',', $keywords));
        } elseif ($arg === '--exclude-families') {
            $options['exclude_families'] = true;
        } elseif ($arg === '--pattern' && isset($argv[$i + 1])) {
            $options['pattern'] = $argv[++$i];
        } elseif ($arg === '--verbose') {
            $options['verbose'] = true;
        }
    }
    
    return $options;
}

function loadConfig(string $configPath): array {
    if (!file_exists($configPath)) {
        fwrite(STDERR, "Warning: Config file not found: {$configPath}\n");
        return [];
    }
    
    $json = file_get_contents($configPath);
    $config = json_decode($json, true);
    
    if ($config === null) {
        fwrite(STDERR, "Warning: Invalid JSON in config file: {$configPath}\n");
        return [];
    }
    
    return $config;
}

function getOutputPath(string $csvPath, string $sourcesFolder): string {
    $basename = basename($csvPath, '.csv');
    $outputName = $basename . '-sources.md';
    return $sourcesFolder . DIRECTORY_SEPARATOR . $outputName;
}

function buildFilterArgs(string $csvBasename, array $options, array $config): array {
    $filters = [];
    
    // Check config for per-file rules
    $fileConfig = $config[$csvBasename] ?? null;
    
    if ($fileConfig && is_array($fileConfig)) {
        // Config-driven filters override command-line
        if (isset($fileConfig['only_principal'])) {
            if ($fileConfig['only_principal']) {
                $filters[] = '--only-principal';
            }
        } elseif ($options['only_principal']) {
            $filters[] = '--only-principal';
        }
        
        if (isset($fileConfig['skip_keywords']) && is_array($fileConfig['skip_keywords'])) {
            $keywords = implode(',', $fileConfig['skip_keywords']);
            $filters[] = '--skip-keywords';
            $filters[] = $keywords;
        } elseif (!empty($options['skip_keywords'])) {
            $keywords = implode(',', $options['skip_keywords']);
            $filters[] = '--skip-keywords';
            $filters[] = $keywords;
        }
        
        if ($fileConfig['exclude_families'] ?? $options['exclude_families']) {
            $filters[] = '--exclude-families';
        }
    } else {
        // Command-line filters
        if ($options['only_principal']) {
            $filters[] = '--only-principal';
        }
        
        if (!empty($options['skip_keywords'])) {
            $keywords = implode(',', $options['skip_keywords']);
            $filters[] = '--skip-keywords';
            $filters[] = $keywords;
        }
        
        if ($options['exclude_families']) {
            $filters[] = '--exclude-families';
        }
    }
    
    return $filters;
}

// Main
$options = parseArgs($argv);
$searchFolder = findSearchResultsFolder();
$sourcesFolder = findSourcesFolder();

// Load config if provided
$config = [];
if ($options['config']) {
    $config = loadConfig($options['config']);
}

// Find CSV files
$repoRoot = dirname(__DIR__);
$pattern = $options['pattern'];

// Resolve pattern relative to repo root or search-results folder
if (strpos($pattern, '/') !== false && !file_exists($pattern)) {
    $pattern = $repoRoot . DIRECTORY_SEPARATOR . $pattern;
}

$csvFiles = glob($pattern);
if (empty($csvFiles)) {
    fwrite(STDERR, "No CSV files found matching pattern: {$options['pattern']}\n");
    exit(1);
}

// Process each CSV
$results = [
    'total' => count($csvFiles),
    'processed' => 0,
    'skipped' => 0,
    'errors' => 0,
];

echo "Found " . count($csvFiles) . " CSV file(s) in search-results folder\n";
if ($options['dry_run']) {
    echo "(DRY RUN - no files will be written)\n\n";
}

foreach ($csvFiles as $csvPath) {
    $csvBasename = basename($csvPath);
    $outputPath = getOutputPath($csvPath, $sourcesFolder);
    
    // Check if output already exists
    $outputBasename = basename($outputPath);
    if (file_exists($outputPath)) {
        echo "⊘ {$csvBasename} → {$outputBasename} (already exists)\n";
        $results['skipped']++;
        continue;
    }
    
    // Build filter arguments
    $filterArgs = buildFilterArgs($csvBasename, $options, $config);
    
    // Build command
    $scriptPath = __DIR__ . DIRECTORY_SEPARATOR . 'csv_to_wikitree_sources.php';
    $cmd = 'php ' . escapeshellarg($scriptPath);
    $cmd .= ' --in ' . escapeshellarg($csvPath);
    $cmd .= ' --out ' . escapeshellarg($outputPath);
    foreach ($filterArgs as $arg) {
        $cmd .= ' ' . escapeshellarg($arg);
    }
    
    // Show what we're processing
    $filterStr = !empty($filterArgs) ? ' [' . implode(' ', $filterArgs) . ']' : '';
    echo "→ {$csvBasename}{$filterStr}\n";
    
    if ($options['verbose']) {
        echo "  cmd: {$cmd}\n";
    }
    
    if (!$options['dry_run']) {
        // Execute conversion
        $output = [];
        $returnCode = 0;
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0) {
            fwrite(STDERR, "  Error processing {$csvBasename}\n");
            $results['errors']++;
        } else {
            if (file_exists($outputPath)) {
                $size = filesize($outputPath);
                $lineCount = count(file($outputPath));
                if ($options['verbose']) {
                    echo "  ✓ {$outputBasename} ({$size} bytes, {$lineCount} lines)\n";
                }
                $results['processed']++;
            }
        }
    } else {
        $results['processed']++;
    }
}

// Summary
echo "\n--- Summary ---\n";
echo "Processed: {$results['processed']}/{$results['total']}\n";
echo "Skipped: {$results['skipped']}\n";
if ($results['errors'] > 0) {
    echo "Errors: {$results['errors']}\n";
}

if ($options['dry_run']) {
    echo "\nNo files were written (dry-run mode). Run without --dry-run to generate markdown files.\n";
}

?>
