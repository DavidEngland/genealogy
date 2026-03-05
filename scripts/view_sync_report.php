<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * Sync Report Viewer
 * 
 * Displays sync reports in a readable format with actionable recommendations
 * 
 * Usage:
 *   php view_sync_report.php data/welch-185-sync-report.json
 *   php view_sync_report.php data/welch-185-sync-report.json --format markdown
 * 
 * @author David England
 * @date 2026-03-04
 */

// Parse command line arguments
$options = getopt('', ['format:', 'help']);
$reportFile = $argv[1] ?? null;

if (isset($options['help']) || !$reportFile) {
    showHelp();
    exit(0);
}

$format = $options['format'] ?? 'text';

// Load report
if (!file_exists($reportFile)) {
    fwrite(STDERR, "Error: Report file not found: $reportFile\n");
    exit(1);
}

$report = json_decode(file_get_contents($reportFile), true);
if (!$report) {
    fwrite(STDERR, "Error: Invalid JSON in report file\n");
    exit(1);
}

// Display report based on format
if ($format === 'markdown') {
    displayMarkdownReport($report);
} else {
    displayTextReport($report);
}

/* ========== DISPLAY FUNCTIONS ========== */

/**
 * Display report in plain text format
 */
function displayTextReport(array $report): void {
    echo str_repeat('=', 80) . "\n";
    echo "GENEALOGY SYNC REPORT\n";
    echo str_repeat('=', 80) . "\n\n";
    
    // Metadata
    echo "Principal: {$report['metadata']['principal_wikitree_id']}\n";
    echo "Sync Date: {$report['metadata']['sync_date']}\n\n";
    
    // Overview
    echo str_repeat('-', 80) . "\n";
    echo "OVERVIEW\n";
    echo str_repeat('-', 80) . "\n";
    
    $wt = $report['wikitree'];
    $fs = $report['familysearch'];
    
    echo "WikiTree:\n";
    echo "  Principal: {$wt['principal']['name']} ({$wt['principal']['wikitree_id']})\n";
    echo "  Birth: {$wt['principal']['birth_year']} - Death: {$wt['principal']['death_year']}\n";
    if ($wt['spouse']) {
        echo "  Spouse: {$wt['spouse']['name']} ({$wt['spouse']['wikitree_id']})\n";
    }
    echo "  Children: " . count($wt['children']) . "\n\n";
    
    if ($fs) {
        echo "FamilySearch:\n";
        echo "  Principal: {$fs['principal']['name']} ({$fs['principal']['familysearch_id']})\n";
        echo "  Birth: {$fs['principal']['birth_year']} - Death: {$fs['principal']['death_year']}\n";
        if ($fs['spouse']) {
            echo "  Spouse: {$fs['spouse']['name']} ({$fs['spouse']['familysearch_id']})\n";
        }
        echo "  Children: " . count($fs['children']) . "\n\n";
    }
    
    // Cross-references
    echo str_repeat('-', 80) . "\n";
    echo "CROSS-REFERENCES (" . count($report['cross_references']) . ")\n";
    echo str_repeat('-', 80) . "\n";
    
    foreach ($report['cross_references'] as $ref) {
        $conf = strtoupper($ref['confidence']);
        $wtName = $ref['wikitree_name'] ?? $ref['name'];
        $fsName = $ref['familysearch_name'] ?? $ref['name'];
        echo "[$conf] {$ref['wikitree_id']} <-> {$ref['familysearch_id']}\n";
        if ($wtName !== $fsName) {
            echo "       WT: $wtName\n";
            echo "       FS: $fsName\n";
        } else {
            echo "       $wtName\n";
        }
    }
    echo "\n";
    
    // Discrepancies
    if (!empty($report['discrepancies'])) {
        echo str_repeat('-', 80) . "\n";
        echo "DISCREPANCIES (" . count($report['discrepancies']) . ")\n";
        echo str_repeat('-', 80) . "\n";
        
        $byType = [];
        foreach ($report['discrepancies'] as $disc) {
            $byType[$disc['type']][] = $disc;
        }
        
        foreach ($byType as $type => $discs) {
            $title = strtoupper(str_replace('_', ' ', $type));
            echo "\n$title (" . count($discs) . "):\n";
            
            foreach ($discs as $disc) {
                if ($type === 'missing_in_wikitree') {
                    $p = $disc['person'];
                    echo "  • {$p['name']} (FS: {$p['familysearch_id']}) - {$p['birth_year']}-{$p['death_year']}\n";
                } elseif ($type === 'missing_in_familysearch') {
                    $p = $disc['person'];
                    echo "  • {$p['name']} (WT: {$p['wikitree_id']}) - {$p['birth_year']}-{$p['death_year']}\n";
                } elseif ($type === 'birth_year_mismatch') {
                    echo "  • {$disc['name']} - WT: {$disc['wikitree_year']}, FS: {$disc['familysearch_year']}\n";
                }
            }
        }
        echo "\n";
    }
    
    // Recommendations
    if (!empty($report['recommendations'])) {
        echo str_repeat('=', 80) . "\n";
        echo "RECOMMENDATIONS\n";
        echo str_repeat('=', 80) . "\n\n";
        
        foreach ($report['recommendations'] as $rec) {
            $priority = strtoupper($rec['priority']);
            echo "[$priority] {$rec['description']}\n";
            
            if ($rec['action'] === 'add_to_wikitree' && !empty($rec['items'])) {
                echo "\nTo add to WikiTree:\n";
                foreach ($rec['items'] as $person) {
                    echo "  • {$person['name']} ({$person['familysearch_id']})\n";
                    echo "    Born: {$person['birth_year']} - Died: " . 
                         ($person['death_year'] ?? 'Unknown') . "\n";
                }
            } elseif ($rec['action'] === 'add_familysearch_ids' && !empty($rec['items'])) {
                echo "\nAdd FamilySearch IDs:\n";
                foreach ($rec['items'] as $ref) {
                    echo "  • {$ref['wikitree_id']} → {$ref['familysearch_id']}\n";
                }
            }
            echo "\n";
        }
    }
    
    echo str_repeat('=', 80) . "\n";
}

/**
 * Display report in Markdown format
 */
function displayMarkdownReport(array $report): void {
    echo "# Genealogy Sync Report\n\n";
    
    // Metadata
    echo "**Principal:** {$report['metadata']['principal_wikitree_id']}  \n";
    echo "**Sync Date:** {$report['metadata']['sync_date']}  \n\n";
    
    // Overview
    echo "## Overview\n\n";
    
    $wt = $report['wikitree'];
    $fs = $report['familysearch'];
    
    echo "### WikiTree\n\n";
    echo "- **Principal:** {$wt['principal']['name']} ({$wt['principal']['wikitree_id']})\n";
    echo "- **Birth:** {$wt['principal']['birth_year']} - **Death:** {$wt['principal']['death_year']}\n";
    if ($wt['spouse']) {
        echo "- **Spouse:** {$wt['spouse']['name']} ({$wt['spouse']['wikitree_id']})\n";
    }
    echo "- **Children:** " . count($wt['children']) . "\n\n";
    
    if ($fs) {
        echo "### FamilySearch\n\n";
        echo "- **Principal:** {$fs['principal']['name']} ({$fs['principal']['familysearch_id']})\n";
        echo "- **Birth:** {$fs['principal']['birth_year']} - **Death:** {$fs['principal']['death_year']}\n";
        if ($fs['spouse']) {
            echo "- **Spouse:** {$fs['spouse']['name']} ({$fs['spouse']['familysearch_id']})\n";
        }
        echo "- **Children:** " . count($fs['children']) . "\n\n";
    }
    
    // Cross-references
    echo "## Cross-References\n\n";
    echo "Total matches: " . count($report['cross_references']) . "\n\n";
    
    echo "| Confidence | WikiTree ID | FamilySearch ID | Name |\n";
    echo "|------------|-------------|-----------------|------|\n";
    
    foreach ($report['cross_references'] as $ref) {
        $name = $ref['wikitree_name'] ?? $ref['name'];
        echo "| {$ref['confidence']} | {$ref['wikitree_id']} | {$ref['familysearch_id']} | $name |\n";
    }
    echo "\n";
    
    // Discrepancies
    if (!empty($report['discrepancies'])) {
        echo "## Discrepancies\n\n";
        echo "Total: " . count($report['discrepancies']) . "\n\n";
        
        $byType = [];
        foreach ($report['discrepancies'] as $disc) {
            $byType[$disc['type']][] = $disc;
        }
        
        foreach ($byType as $type => $discs) {
            $title = ucwords(str_replace('_', ' ', $type));
            echo "### $title (" . count($discs) . ")\n\n";
            
            foreach ($discs as $disc) {
                if ($type === 'missing_in_wikitree') {
                    $p = $disc['person'];
                    echo "- **{$p['name']}** (FS: {$p['familysearch_id']}) - {$p['birth_year']}-{$p['death_year']}\n";
                } elseif ($type === 'missing_in_familysearch') {
                    $p = $disc['person'];
                    echo "- **{$p['name']}** (WT: {$p['wikitree_id']}) - {$p['birth_year']}-{$p['death_year']}\n";
                } elseif ($type === 'birth_year_mismatch') {
                    echo "- **{$disc['name']}** - WT: {$disc['wikitree_year']}, FS: {$disc['familysearch_year']}\n";
                }
            }
            echo "\n";
        }
    }
    
    // Recommendations
    if (!empty($report['recommendations'])) {
        echo "## Recommendations\n\n";
        
        foreach ($report['recommendations'] as $rec) {
            echo "### [{$rec['priority']}] {$rec['description']}\n\n";
            
            if ($rec['action'] === 'add_to_wikitree' && !empty($rec['items'])) {
                echo "People to add to WikiTree:\n\n";
                foreach ($rec['items'] as $person) {
                    echo "- **{$person['name']}** ({$person['familysearch_id']})\n";
                    echo "  - Born: {$person['birth_year']} - Died: " . 
                         ($person['death_year'] ?? 'Unknown') . "\n";
                }
            } elseif ($rec['action'] === 'add_familysearch_ids' && !empty($rec['items'])) {
                echo "Add FamilySearch IDs to WikiTree:\n\n";
                foreach ($rec['items'] as $ref) {
                    echo "- {$ref['wikitree_id']} → {$ref['familysearch_id']}\n";
                }
            }
            echo "\n";
        }
    }
}

/**
 * Show help message
 */
function showHelp(): void {
    echo <<<HELP
Sync Report Viewer

Displays genealogy sync reports in a readable format.

Usage:
  php view_sync_report.php REPORT_FILE [--format FORMAT]

Options:
  --format FORMAT    Output format: text (default) or markdown
  --help             Show this help message

Examples:
  php view_sync_report.php data/welch-185-sync-report.json
  php view_sync_report.php data/welch-185-sync-report.json --format markdown > report.md

HELP;
}
