<?php
// Enhanced GEDCOM parser.
/**
 * Enhanced GEDCOM Parser with WikiTree & FamilySearch ID Extraction
 * Converts GEDCOM files to JSON, extracting genealogy service IDs
 */

class EnhancedGedcomParser {
    private $data = [
        'metadata' => [],
        'people' => [],
        'families' => [],
        'places' => [],
        'sources' => [],
        'idMappings' => []  // Track ID sources
    ];
    
    private $placeIndex = [];
    private $sourceIndex = [];
    private $gedcomIdToWikiTree = [];
    private $idPatterns = [];
    private $extractionStats = [
        'wikitree' => 0,
        'familysearch' => 0,
        'ancestry' => 0,
        'other' => 0,
        'missing' => 0
    ];
    
    public function __construct() {
        $this->initializeIdPatterns();
    }
    
    /**
     * Initialize patterns for different genealogy service IDs
     */
    private function initializeIdPatterns() {
        $this->idPatterns = [
            'wikitree' => [
                'pattern' => '/(?:wiki[tT]ree[:\s]+)?([A-Z][a-z]+-\d+)/i',
                'extractor' => 'extractWikiTreeId'
            ],
            'familysearch' => [
                'pattern' => '/(?:familysearch|FS|fs)[\s:]*([A-Z0-9]{4}-[A-Z0-9]{2,3})/i',
                'extractor' => 'extractFamilySearchId'
            ],
            'ancestry' => [
                'pattern' => '/(?:ancestry|Ancestry)[\s:]*([0-9]{6,})/i',
                'extractor' => 'extractAncestryId'
            ],
            'findagrave' => [
                'pattern' => '/(?:find.?a.?grave|FaG|findagrave)[\s:]*([0-9]+)/i',
                'extractor' => 'extractFindAGraveId'
            ]
        ];
    }
    
    /**
     * Parse a GEDCOM file
     */
    public function parse($filePath, $verbose = false) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        if ($verbose) {
            echo "Parsing: " . basename($filePath) . " (" . count($lines) . " lines)\n";
        }
        
        $this->parseLines($lines);
        
        return $this->data;
    }
    
    /**
     * Parse array of GEDCOM lines
     */
    private function parseLines($lines) {
        $stack = [];
        $currentRecord = null;
        $recordType = null;
        
        foreach ($lines as $line) {
            $parts = $this->parseLine($line);
            if (!$parts) continue;
            
            list($level, $tag, $value, $xref) = $parts;
            
            if ($tag === 'HEAD' && $level === 0) {
                $this->parseHeader($lines);
                continue;
            }
            
            if ($tag === 'INDI' && $level === 0) {
                if ($currentRecord && $recordType === 'INDI') {
                    $this->addPerson($currentRecord);
                }
                $currentRecord = ['gedcomId' => $xref];
                $recordType = 'INDI';
                continue;
            }
            
            if ($tag === 'FAM' && $level === 0) {
                if ($currentRecord && $recordType === 'FAM') {
                    $this->addFamily($currentRecord);
                }
                $currentRecord = ['familyId' => $xref];
                $recordType = 'FAM';
                continue;
            }
            
            if ($tag === 'SOUR' && $level === 0) {
                if ($currentRecord && $recordType === 'SOUR') {
                    $this->addSource($currentRecord);
                }
                $currentRecord = ['sourceId' => $xref];
                $recordType = 'SOUR';
                continue;
            }
            
            if ($currentRecord && $level > 0) {
                $this->addToRecord($currentRecord, $level, $tag, $value, $xref);
            }
        }
        
        if ($currentRecord && $recordType === 'INDI') {
            $this->addPerson($currentRecord);
        } elseif ($currentRecord && $recordType === 'FAM') {
            $this->addFamily($currentRecord);
        } elseif ($currentRecord && $recordType === 'SOUR') {
            $this->addSource($currentRecord);
        }
    }
    
    /**
     * Parse single GEDCOM line
     */
    private function parseLine($line) {
        if (empty(trim($line))) return null;
        
        $matches = [];
        if (!preg_match('/^(\d+)\s+(?:(@[^@]+@)\s+)?([A-Z_]+)(?:\s+(.*))?$/', $line, $matches)) {
            return null;
        }
        
        $level = (int)$matches[1];
        $xref = $matches[2] ?? '';
        $tag = $matches[3];
        $value = $matches[4] ?? '';
        
        return [$level, $tag, trim($value), $xref];
    }
    
    /**
     * Parse GEDCOM header
     */
    private function parseHeader($lines) {
        foreach ($lines as $line) {
            if (strpos($line, '1 SOUR') === 0) {
                preg_match('/1 SOUR\s+(.+)/', $line, $m);
                $this->data['metadata']['source'] = $m[1] ?? '';
            }
            if (strpos($line, '1 GEDC') === 0) {
                foreach ($lines as $subline) {
                    if (strpos($subline, '2 VERS') === 0) {
                        preg_match('/2 VERS\s+(.+)/', $subline, $m);
                        $this->data['metadata']['gedcomVersion'] = $m[1] ?? '';
                    }
                }
            }
            if (strpos($line, '1 CHAR') === 0) {
                preg_match('/1 CHAR\s+(.+)/', $line, $m);
                $this->data['metadata']['charset'] = $m[1] ?? '';
            }
            if (strpos($line, '1 DATE') === 0) {
                preg_match('/1 DATE\s+(.+)/', $line, $m);
                $this->data['metadata']['lastUpdated'] = $m[1] ?? '';
            }
        }
    }
    
    /**
     * Add field to record and extract IDs
     */
    private function addToRecord(&$record, $level, $tag, $value, $xref) {
        switch ($tag) {
            case 'NAME':
                if (!isset($record['name'])) {
                    $record['name'] = [];
                }
                $record['name']['full'] = $value;
                if (preg_match('/^(.+?)\/(.+?)\/$/', $value, $m)) {
                    $record['name']['given'] = trim($m[1]);
                    $record['name']['surname'] = trim($m[2]);
                }
                break;
                
            case 'SEX':
                $record['sex'] = $value;
                break;
                
            case 'NOTE':
            case '_NOTE':
                $record['notes'] = ($record['notes'] ?? '') . $value . "\n";
                // Extract IDs from notes
                $this->extractIdsFromText($record, $value);
                break;
                
            case 'SOUR':
            case '_SOUR':
                if (!isset($record['sources'])) {
                    $record['sources'] = [];
                }
                $record['sources'][] = $value;
                $this->extractIdsFromText($record, $value);
                break;
                
            case '_UID':
            case 'UID':
                $record['externalId'] = $value;
                $this->extractIdsFromText($record, $value);
                break;
                
            case '_AFN':  // Ancestry File Number
            case 'AFN':
                $record['ancestryId'] = $value;
                break;
                
            case 'BIRT':
                $record['birth'] = [];
                break;
                
            case 'DEAT':
                $record['death'] = [];
                break;
                
            case 'DATE':
                if (isset($record['birth']) && is_array($record['birth']) && empty($record['birth'])) {
                    $record['birth']['date'] = $value;
                } elseif (isset($record['death']) && is_array($record['death']) && empty($record['death'])) {
                    $record['death']['date'] = $value;
                } elseif (isset($record['marriage']) && is_array($record['marriage']) && empty($record['marriage'])) {
                    $record['marriage']['date'] = $value;
                }
                break;
                
            case 'PLAC':
                if (isset($record['birth']) && is_array($record['birth']) && empty($record['birth'])) {
                    $record['birth']['place'] = $value;
                } elseif (isset($record['death']) && is_array($record['death']) && empty($record['death'])) {
                    $record['death']['place'] = $value;
                } elseif (isset($record['marriage']) && is_array($record['marriage']) && empty($record['marriage'])) {
                    $record['marriage']['place'] = $value;
                }
                break;
                
            case 'FAMS':
                if (!isset($record['familyAsSpouse'])) {
                    $record['familyAsSpouse'] = [];
                }
                $record['familyAsSpouse'][] = $value;
                break;
                
            case 'FAMC':
                if (!isset($record['familyAsChild'])) {
                    $record['familyAsChild'] = [];
                }
                $record['familyAsChild'][] = $value;
                break;
                
            case 'HUSB':
                $record['husband'] = $value;
                break;
                
            case 'WIFE':
                $record['wife'] = $value;
                break;
                
            case 'CHIL':
                if (!isset($record['children'])) {
                    $record['children'] = [];
                }
                $record['children'][] = $value;
                break;
                
            case 'MARR':
                $record['marriage'] = [];
                break;
                
            case 'TITL':
                if (!isset($record['title'])) {
                    $record['title'] = $value;
                }
                break;
                
            case 'ABBR':
                $record['abbreviation'] = $value;
                break;
        }
    }
    
    /**
     * Extract IDs from text content
     */
    private function extractIdsFromText(&$record, $text) {
        if (!$text) return;
        
        // WikiTree ID
        if (preg_match($this->idPatterns['wikitree']['pattern'], $text, $m)) {
            if (!isset($record['wikitreeId']) || empty($record['wikitreeId'])) {
                $record['wikitreeId'] = $m[1];
                $this->extractionStats['wikitree']++;
            }
        }
        
        // FamilySearch ID
        if (preg_match($this->idPatterns['familysearch']['pattern'], $text, $m)) {
            if (!isset($record['familySearchId']) || empty($record['familySearchId'])) {
                $record['familySearchId'] = $m[1];
                $this->extractionStats['familysearch']++;
            }
        }
        
        // Ancestry ID
        if (preg_match($this->idPatterns['ancestry']['pattern'], $text, $m)) {
            if (!isset($record['ancestryId']) || empty($record['ancestryId'])) {
                $record['ancestryId'] = $m[1];
                $this->extractionStats['ancestry']++;
            }
        }
        
        // Find A Grave ID
        if (preg_match($this->idPatterns['findagrave']['pattern'], $text, $m)) {
            if (!isset($record['findAGraveId']) || empty($record['findAGraveId'])) {
                $record['findAGraveId'] = $m[1];
            }
        }
    }
    
    /**
     * Convert person record from GEDCOM to schema
     */
    private function addPerson($record) {
        // Generate or use existing WikiTree ID
        $wikitreeId = $record['wikitreeId'] 
            ?? $record['ancestryId']
            ?? $record['familySearchId']
            ?? $this->generateWikiTreeId($record);
        
        $person = [
            'gedcomId' => $record['gedcomId'],
            'wikitreeId' => $wikitreeId,
            'name' => $record['name'] ?? ['full' => 'Unknown']
        ];
        
        // Add external IDs if found
        if (isset($record['familySearchId'])) {
            $person['familySearchId'] = $record['familySearchId'];
        }
        if (isset($record['ancestryId'])) {
            $person['ancestryId'] = $record['ancestryId'];
        }
        if (isset($record['findAGraveId'])) {
            $person['findAGraveId'] = $record['findAGraveId'];
        }
        
        if (isset($record['sex'])) $person['sex'] = $record['sex'];
        if (isset($record['birth'])) $person['birth'] = array_filter($record['birth']);
        if (isset($record['death'])) $person['death'] = array_filter($record['death']);
        if (isset($record['familyAsSpouse'])) $person['familyAsSpouse'] = $record['familyAsSpouse'];
        if (isset($record['familyAsChild'])) $person['familyAsChild'] = $record['familyAsChild'];
        if (isset($record['notes'])) $person['notes'] = trim($record['notes']);
        
        $this->data['people'][] = $person;
        $this->gedcomIdToWikiTree[$record['gedcomId']] = $wikitreeId;
        
        // Track ID sources
        $this->data['idMappings'][] = [
            'gedcomId' => $record['gedcomId'],
            'wikitreeId' => $wikitreeId,
            'name' => $person['name']['full'],
            'sources' => array_filter([
                isset($record['wikitreeId']) ? 'wikitree' : null,
                isset($record['familySearchId']) ? 'familysearch' : null,
                isset($record['ancestryId']) ? 'ancestry' : null,
                isset($record['findAGraveId']) ? 'findagrave' : null,
            ])
        ];
    }
    
    /**
     * Convert family record
     */
    private function addFamily($record) {
        $family = ['familyId' => $record['familyId']];
        
        if (isset($record['husband'])) {
            $family['husband'] = $this->gedcomIdToWikiTree[$record['husband']] ?? $record['husband'];
        }
        if (isset($record['wife'])) {
            $family['wife'] = $this->gedcomIdToWikiTree[$record['wife']] ?? $record['wife'];
        }
        if (isset($record['marriage'])) {
            $family['marriage'] = array_filter($record['marriage']);
        }
        if (isset($record['children'])) {
            $family['children'] = array_map(
                fn($child) => $this->gedcomIdToWikiTree[$child] ?? $child,
                $record['children']
            );
        }
        
        $this->data['families'][] = $family;
    }
    
    /**
     * Convert source record
     */
    private function addSource($record) {
        $source = ['sourceId' => $record['sourceId']];
        
        if (isset($record['title'])) $source['title'] = $record['title'];
        if (isset($record['abbreviation'])) $source['abbreviation'] = $record['abbreviation'];
        
        $this->data['sources'][] = $source;
    }
    
    /**
     * Generate WikiTree ID from GEDCOM ID (fallback)
     */
    private function generateWikiTreeId($record) {
        if (isset($record['name']['surname'])) {
            $surname = $record['name']['surname'];
            $clean = str_replace('@', '', $record['gedcomId']);
            $num = preg_replace('/[^0-9]/', '', $clean) ?: rand(1000, 9999);
            $this->extractionStats['missing']++;
            return "$surname-$num";
        }
        
        $clean = str_replace('@', '', $record['gedcomId']);
        $this->extractionStats['missing']++;
        return "Person-{$clean}";
    }
    
    /**
     * Get JSON output
     */
    public function toJson($pretty = true) {
        return json_encode($this->data, $pretty ? JSON_PRETTY_PRINT : 0);
    }
    
    /**
     * Get extraction statistics
     */
    public function getStats() {
        return [
            'totalPeople' => count($this->data['people']),
            'totalFamilies' => count($this->data['families']),
            'idExtraction' => $this->extractionStats
        ];
    }
    
    /**
     * Get data array
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Export ID mappings to CSV
     */
    public function exportIdMappings($outputFile) {
        $handle = fopen($outputFile, 'w');
        
           fputcsv($handle, ['GEDCOM ID', 'WikiTree ID', 'Name', 'ID Sources'], ',', '"', '\\');
        
        foreach ($this->data['idMappings'] as $mapping) {
                            fputcsv($handle, [
                $mapping['gedcomId'],
                $mapping['wikitreeId'],
                $mapping['name'],
                implode(', ', $mapping['sources'])
                            ], ',', '"', '\\');
        }
        
        fclose($handle);
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php enhanced_gedcom_parser.php <gedcom_file> [output_json] [id_mapping_csv]\n";
        echo "Example: php enhanced_gedcom_parser.php myfile.ged data/myfile.json data/myfile-ids.csv\n";
        exit(1);
    }
    
    try {
        $parser = new EnhancedGedcomParser();
        $verbose = in_array('-v', $argv) || in_array('--verbose', $argv);
        $data = $parser->parse($argv[1], $verbose);
        
        // Output JSON
        $jsonOutput = $parser->toJson(true);
        if (isset($argv[2])) {
            file_put_contents($argv[2], $jsonOutput);
            if ($verbose) echo "✓ JSON output: {$argv[2]}\n";
        } else {
            echo $jsonOutput;
        }
        
        // Export ID mappings if requested
        if (isset($argv[3])) {
            $parser->exportIdMappings($argv[3]);
            if ($verbose) echo "✓ ID mappings: {$argv[3]}\n";
        }
        
        // Show statistics
        if ($verbose) {
            $stats = $parser->getStats();
            echo "\nStatistics:\n";
            echo "  People: {$stats['totalPeople']}\n";
            echo "  Families: {$stats['totalFamilies']}\n";
            echo "  IDs extracted: WikiTree={$stats['idExtraction']['wikitree']}, FamilySearch={$stats['idExtraction']['familysearch']}, Ancestry={$stats['idExtraction']['ancestry']}\n";
            echo "  IDs generated: {$stats['idExtraction']['missing']}\n";
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
