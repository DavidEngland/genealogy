<?php
/**
 * GEDCOM to JSON Parser
 * Converts GEDCOM files to JSON following schema.json structure
 */

class GedcomParser {
    private $data = [
        'metadata' => [],
        'people' => [],
        'families' => [],
        'places' => [],
        'sources' => []
    ];
    
    private $placeIndex = [];
    private $sourceIndex = [];
    private $gedcomIdToWikiTree = []; // Maps @I123@ to England-1357
    
    /**
     * Parse a GEDCOM file
     * @param string $filePath Path to .ged file
     * @param string $wikitreePrefix Optional prefix for auto-generating WikiTree IDs
     * @return array JSON-structured genealogy data
     */
    public function parse($filePath, $wikitreePrefix = '') {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
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
            
            // Handle header
            if ($tag === 'HEAD' && $level === 0) {
                $this->parseHeader($lines);
                continue;
            }
            
            // Start new individual record
            if ($tag === 'INDI' && $level === 0) {
                if ($currentRecord && $recordType === 'INDI') {
                    $this->addPerson($currentRecord);
                }
                $currentRecord = ['gedcomId' => $xref];
                $recordType = 'INDI';
                continue;
            }
            
            // Start new family record
            if ($tag === 'FAM' && $level === 0) {
                if ($currentRecord && $recordType === 'FAM') {
                    $this->addFamily($currentRecord);
                }
                $currentRecord = ['familyId' => $xref];
                $recordType = 'FAM';
                continue;
            }
            
            // Start new source record
            if ($tag === 'SOUR' && $level === 0) {
                if ($currentRecord && $recordType === 'SOUR') {
                    $this->addSource($currentRecord);
                }
                $currentRecord = ['sourceId' => $xref];
                $recordType = 'SOUR';
                continue;
            }
            
            // Parse record fields
            if ($currentRecord && $level > 0) {
                $this->addToRecord($currentRecord, $level, $tag, $value, $xref);
            }
        }
        
        // Add final record
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
     * Format: LEVEL TAG XREF VALUE
     * Returns: [level, tag, value, xref]
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
     * Parse GEDCOM header for metadata
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
     * Add field to current record
     */
    private function addToRecord(&$record, $level, $tag, $value, $xref) {
        switch ($tag) {
            case 'NAME':
                if (!isset($record['name'])) {
                    $record['name'] = [];
                }
                $record['name']['full'] = $value;
                // Parse name into given and surname
                if (preg_match('/^(.+?)\/(.+?)\/$/', $value, $m)) {
                    $record['name']['given'] = trim($m[1]);
                    $record['name']['surname'] = trim($m[2]);
                }
                break;
                
            case 'SEX':
                $record['sex'] = $value;
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
                
            case 'NOTE':
                $record['notes'] = ($record['notes'] ?? '') . $value . "\n";
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
     * Convert person record from GEDCOM to schema
     */
    private function addPerson($record) {
        $person = [
            'gedcomId' => $record['gedcomId'],
            'wikitreeId' => $record['wikitreeId'] ?? $this->generateWikiTreeId($record['gedcomId']),
            'name' => $record['name'] ?? ['full' => 'Unknown']
        ];
        
        if (isset($record['sex'])) $person['sex'] = $record['sex'];
        if (isset($record['birth'])) $person['birth'] = array_filter($record['birth']);
        if (isset($record['death'])) $person['death'] = array_filter($record['death']);
        if (isset($record['familyAsSpouse'])) $person['familyAsSpouse'] = $record['familyAsSpouse'];
        if (isset($record['familyAsChild'])) $person['familyAsChild'] = $record['familyAsChild'];
        if (isset($record['notes'])) $person['notes'] = trim($record['notes']);
        
        $this->data['people'][] = $person;
        $this->gedcomIdToWikiTree[$record['gedcomId']] = $person['wikitreeId'];
    }
    
    /**
     * Convert family record from GEDCOM to schema
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
     * Convert source record from GEDCOM to schema
     */
    private function addSource($record) {
        $source = ['sourceId' => $record['sourceId']];
        
        if (isset($record['title'])) $source['title'] = $record['title'];
        if (isset($record['abbreviation'])) $source['abbreviation'] = $record['abbreviation'];
        
        $this->data['sources'][] = $source;
    }
    
    /**
     * Generate WikiTree ID from GEDCOM ID (fallback)
     * Format: FamilyName-Number
     */
    private function generateWikiTreeId($gedcomId) {
        // Remove @ symbols
        $clean = str_replace('@', '', $gedcomId);
        return "Person-{$clean}";
    }
    
    /**
     * Get JSON output
     */
    public function toJson($pretty = true) {
        return json_encode($this->data, $pretty ? JSON_PRETTY_PRINT : 0);
    }
    
    /**
     * Get data as array
     */
    public function getData() {
        return $this->data;
    }
}

// Usage example
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php gedcom_parser.php <gedcom_file> [output_file]\n";
        exit(1);
    }
    
    try {
        $parser = new GedcomParser();
        $data = $parser->parse($argv[1]);
        
        $output = $parser->toJson(true);
        
        if (isset($argv[2])) {
            file_put_contents($argv[2], $output);
            echo "Parsed to: {$argv[2]}\n";
        } else {
            echo $output;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
