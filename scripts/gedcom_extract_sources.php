<?php
/**
 * Extract sources and biographical information from GEDCOM files
 * Outputs WikiTree-formatted markdown to sources/ folder
 *
 * Usage:
 *   # Single file
 *   php scripts/gedcom_extract_sources.php --in GEDs/NicholosWelch-offshoots.ged
 *
 *   # Single file to specific output
 *   php scripts/gedcom_extract_sources.php --in GEDs/NicholosWelch-offshoots.ged --out sources/Welch-sources.md
 *
 *   # Batch process all GEDs/ files
 *   php scripts/gedcom_extract_sources.php --batch
 *
 *   # Batch with family filter
 *   php scripts/gedcom_extract_sources.php --batch --family Welch
 */

declare(strict_types=1);

// Parse command line options
$options = getopt('', ['in:', 'out:', 'batch', 'family:', 'verbose']);

// Helper functions
function debug(string $msg): void {
    global $options;
    if (isset($options['verbose'])) {
        echo "[DEBUG] $msg\n";
    }
}

function extractBiography(array $noteLines): array {
    $bio = [
        'raw_text' => '',
        'biography' => '',
        'sources' => [],
        'research_notes' => '',
        'see_also' => '',
    ];
    
    $inBio = false;
    $inSources = false;
    $bioLines = [];
    $sourceLines = [];
    $researchLines = [];
    $seeAlsoLines = [];
    
    foreach ($noteLines as $line) {
        $line = trim($line);
        
        if (strpos($line, '== Biography ==') !== false) {
            $inBio = true;
            $inSources = false;
            continue;
        }
        if (strpos($line, '== Sources ==') !== false) {
            $inBio = false;
            $inSources = true;
            continue;
        }
        if (strpos($line, '== Research') !== false || strpos($line, '==Research') !== false) {
            $inBio = false;
            $inSources = false;
            $researchLines = [];
            continue;
        }
        if (strpos($line, '== See Also') !== false || strpos($line, 'See Also:') !== false) {
            $inSources = false;
            $seeAlsoLines = [];
            continue;
        }
        
        if ($inBio && !empty($line)) {
            $bioLines[] = $line;
        } elseif ($inSources && !empty($line)) {
            $sourceLines[] = $line;
        } elseif (!empty($researchLines) && !empty($line)) {
            $researchLines[] = $line;
        } elseif (!empty($seeAlsoLines) && !empty($line)) {
            $seeAlsoLines[] = $line;
        }
    }
    
    $bio['biography'] = implode("\n", $bioLines);
    $bio['sources'] = extractSourceCitations(implode("\n", $sourceLines));
    $bio['research_notes'] = implode("\n", $researchLines);
    $bio['see_also'] = implode("\n", $seeAlsoLines);
    $bio['raw_text'] = implode("\n", $noteLines);
    
    return $bio;
}

function extractSourceCitations(string $text): array {
    $sources = [];
    $lines = explode("\n", $text);
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || $line === '<references />') {
            continue;
        }
        if (strpos($line, '*') === 0 || strpos($line, '-') === 0) {
            $sources[] = $line;
        }
    }
    
    return $sources;
}

function extractDatabaseIds(string $text): array {
    $ids = [];
    
    // WikiTree
    if (preg_match('/(?:WikiTree|wiki|Welch|England|Hargrove|Duncan|Lewis|Gresham)-(\d+)/i', $text, $m)) {
        $ids['wikitree'] = preg_replace('/[^A-Za-z0-9-]/', '', $text);
    }
    
    // FamilySearch ARK
    if (preg_match('/ark:\/61903\/1:1:([A-Z0-9]{6,})/i', $text, $m)) {
        $ids['familysearch'] = $m[1];
    }
    
    return $ids;
}

function parseGedcomFile(string $filePath): array {
    if (!file_exists($filePath)) {
        throw new Exception("GEDCOM file not found: $filePath");
    }
    
    $individuals = [];
    $handle = fopen($filePath, 'r');
    if (!$handle) {
        throw new Exception("Cannot open file: $filePath");
    }
    
    $currentId = null;
    $currentPerson = null;
    $noteLines = [];
    $inNote = false;
    
    while (($line = fgets($handle)) !== false) {
        $line = rtrim("\r\n");
        
        if (empty($line)) continue;
        
        // Parse GEDCOM level and tag
        $parts = preg_split('/\s+/', $line, 3);
        if (count($parts) < 2) continue;
        
        $level = intval($parts[0]);
        $tag = $parts[1];
        $value = isset($parts[2]) ? trim($parts[2]) : '';
        
        // New individual
        if ($level === 0 && preg_match('/^@I\d+@$/', $tag)) {
            if ($currentPerson) {
                $currentPerson['note'] = implode("\n", $noteLines);
                $individuals[$currentId] = $currentPerson;
            }
            $currentId = $tag;
            $currentPerson = [
                'id' => $currentId,
                'name' => '',
                'given' => '',
                'surname' => '',
                'sex' => '',
                'birth_date' => '',
                'birth_place' => '',
                'death_date' => '',
                'death_place' => '',
                'wiki_url' => '',
                'note' => '',
            ];
            $noteLines = [];
            $inNote = false;
        }
        
        // Personal data
        if ($currentPerson) {
            if ($tag === 'NAME' && $level === 1) {
                $currentPerson['name'] = $value;
            } elseif ($tag === 'GIVN' && $level === 2) {
                $currentPerson['given'] = $value;
            } elseif ($tag === 'SURN' && $level === 2) {
                $currentPerson['surname'] = $value;
            } elseif ($tag === 'SEX' && $level === 1) {
                $currentPerson['sex'] = $value;
            } elseif ($tag === 'DATE' && $level === 2) {
                if (empty($currentPerson['birth_date'])) {
                    $currentPerson['birth_date'] = $value;
                } else {
                    $currentPerson['death_date'] = $value;
                }
            } elseif ($tag === 'PLAC' && $level === 2) {
                if (empty($currentPerson['birth_place'])) {
                    $currentPerson['birth_place'] = $value;
                } else {
                    $currentPerson['death_place'] = $value;
                }
            } elseif ($tag === 'WWW' && $level === 1) {
                $currentPerson['wiki_url'] = $value;
            } elseif ($tag === 'NOTE' && $level === 1) {
                $inNote = true;
                $noteLines = [$value];
            } elseif (($tag === 'CONT' || $tag === 'CONC') && $inNote && $level === 2) {
                if ($tag === 'CONT') {
                    $noteLines[] = $value;
                } else {
                    // CONC continues the previous line
                    if (!empty($noteLines)) {
                        $noteLines[count($noteLines) - 1] .= $value;
                    }
                }
            }
        }
    }
    
    // Don't forget last person
    if ($currentPerson) {
        $currentPerson['note'] = implode("\n", $noteLines);
        $individuals[$currentId] = $currentPerson;
    }
    
    fclose($handle);
    return $individuals;
}

function generateMarkdownOutput(array $individual, string $gedFileName): string {
    $md = '';
    
    // Extract biography
    $noteLines = array_filter(explode("\n", $individual['note']));
    $bio = extractBiography($noteLines);
    
    // WikiTree category
    $surname = preg_replace('/[^A-Za-z]/', '', $individual['surname']);
    if ($surname) {
        $md .= "[[Category:$surname]]\n";
    }
    if (!empty($individual['birth_place'])) {
        $birthPlace = explode(',', $individual['birth_place'])[0];
        $md .= "[[Category:" . trim($birthPlace) . "]]\n";
    }
    $md .= "\n";
    
    // Name and dates
    $fullName = trim($individual['name']);
    $fullName = str_replace('  ', ' ', $fullName);
    $md .= "'''$fullName'''\n";
    
    if (!empty($individual['birth_date']) || !empty($individual['death_date'])) {
        $dates = '';
        if (!empty($individual['birth_date'])) {
            $dates .= $individual['birth_date'];
        }
        if (!empty($individual['death_date'])) {
            $dates .= (!empty($dates) ? ' – ' : '') . $individual['death_date'];
        }
        if ($dates) {
            $md .= "($dates)";
        }
    }
    $md .= "\n\n";
    
    // Biography section
    if (!empty($bio['biography'])) {
        $md .= "== Biography ==\n\n";
        $md .= $bio['biography'] . "\n\n";
    }
    
    // Sources section
    if (!empty($bio['sources'])) {
        $md .= "== Sources ==\n\n";
        foreach ($bio['sources'] as $source) {
            if (!empty($source)) {
                $md .= $source . "\n\n";
            }
        }
        $md .= "<references />\n\n";
    }
    
    // Research notes
    if (!empty($bio['research_notes'])) {
        $md .= "== Research Notes ==\n\n";
        $md .= $bio['research_notes'] . "\n\n";
    }
    
    // See also
    if (!empty($bio['see_also'])) {
        $md .= "== See Also ==\n\n";
        $md .= $bio['see_also'] . "\n\n";
    }
    
    // Source file note
    $md .= "{{Sourced from GEDCOM|" . basename($gedFileName) . "|31 Jan 2026}}\n";
    
    return $md;
}

function generateOutputFileName(string $gedFile, ?string $surname = null): string {
    $fileName = basename($gedFile, '.ged');
    $outDir = 'sources';
    
    if ($surname) {
        return "$outDir/GEDCOM-$surname-sources.md";
    }
    
    return "$outDir/GEDCOM-$fileName-sources.md";
}

function getFileAncestorSurname(array $individuals): ?string {
    // Try to find primary surname from individuals
    $surnames = [];
    foreach ($individuals as $person) {
        if (!empty($person['surname'])) {
            $surname = trim($person['surname']);
            $surnames[$surname] = ($surnames[$surname] ?? 0) + 1;
        }
    }
    
    if (!empty($surnames)) {
        return array_key_first($surnames);
    }
    
    return null;
}

// Main execution
try {
    if (isset($options['batch'])) {
        // Batch process all GEDs
        $pattern = 'GEDs/*.ged';
        $files = glob($pattern);
        if (empty($files)) {
            echo "No GEDCOM files found in GEDs/\n";
            exit(1);
        }
        
        $familyFilter = $options['family'] ?? null;
        $processed = 0;
        $skipped = 0;
        
        foreach ($files as $gedFile) {
            $baseName = basename($gedFile, '.ged');
            
            // Skip non-family files
            if ($familyFilter && stripos($baseName, $familyFilter) === false) {
                debug("Skipping $baseName (doesn't match family filter '$familyFilter')");
                $skipped++;
                continue;
            }
            
            // Skip markdown and other non-ged files
            if (pathinfo($gedFile, PATHINFO_EXTENSION) !== 'ged') {
                continue;
            }
            
            try {
                echo "Processing: $baseName.ged ... ";
                $individuals = parseGedcomFile($gedFile);
                
                if (empty($individuals)) {
                    echo "SKIPPED (no individuals found)\n";
                    $skipped++;
                    continue;
                }
                
                $surname = getFileAncestorSurname($individuals);
                $outFile = generateOutputFileName($gedFile, $surname);
                
                // Create output directory if needed
                $outDir = dirname($outFile);
                if (!is_dir($outDir)) {
                    mkdir($outDir, 0755, true);
                }
                
                // Generate markdown output with all individuals
                $allMd = "# Sources from GEDCOM: " . basename($gedFile) . "\n\n";
                $allMd .= "Extracted: " . date('d M Y') . "\n";
                $allMd .= "Total individuals: " . count($individuals) . "\n\n";
                $allMd .= "---\n\n";
                
                $count = 0;
                foreach ($individuals as $person) {
                    // Skip entries with no biographical data
                    if (empty($person['note'])) {
                        continue;
                    }
                    
                    $personMd = generateMarkdownOutput($person, $gedFile);
                    if (!empty($personMd)) {
                        $allMd .= $personMd . "\n\n";
                        $count++;
                    }
                }
                
                if ($count > 0) {
                    file_put_contents($outFile, $allMd);
                    echo "OK ($count individuals with sources)\n";
                    $processed++;
                } else {
                    echo "SKIPPED (no individuals with sources)\n";
                    $skipped++;
                }
            } catch (Exception $e) {
                echo "ERROR: " . $e->getMessage() . "\n";
                $skipped++;
            }
        }
        
        echo "\n=== Batch Processing Complete ===\n";
        echo "Processed: $processed\n";
        echo "Skipped: $skipped\n";
        
    } else {
        // Single file processing
        if (!isset($options['in'])) {
            die("Usage: php scripts/gedcom_extract_sources.php --in GEDs/file.ged [--out output.md] [--batch] [--family Surname]\n");
        }
        
        $inFile = $options['in'];
        $outFile = $options['out'] ?? generateOutputFileName($inFile, getFileAncestorSurname(parseGedcomFile($inFile)));
        
        echo "Extracting from: $inFile\n";
        echo "Output file: $outFile\n";
        
        $individuals = parseGedcomFile($inFile);
        
        if (empty($individuals)) {
            die("No individuals found in GEDCOM file.\n");
        }
        
        // Create output directory
        $outDir = dirname($outFile);
        if (!is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }
        
        // Generate markdown with all individuals
        $allMd = "# Sources from GEDCOM: " . basename($inFile) . "\n\n";
        $allMd .= "Extracted: " . date('d M Y') . "\n";
        $allMd .= "Total individuals: " . count($individuals) . "\n\n";
        $allMd .= "---\n\n";
        
        $count = 0;
        foreach ($individuals as $person) {
            if (empty($person['note'])) {
                continue;
            }
            
            $personMd = generateMarkdownOutput($person, $inFile);
            if (!empty($personMd)) {
                $allMd .= $personMd . "\n\n";
                $count++;
            }
        }
        
        file_put_contents($outFile, $allMd);
        
        echo "Extracted: $count individuals with biographical sources\n";
        echo "Generated: $outFile\n";
    }
} catch (Exception $e) {
    die("Error: " . $e->getMessage() . "\n");
}
