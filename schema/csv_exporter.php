<?php
/**
 * CSV Export Tool
 * Generates CSV from JSON genealogy data
 * Format: wikitree_id, name, birth_date, birth_place, death_date, death_place
 */

class CsvExporter {
    
    /**
     * Load JSON data from file or string
     */
    public static function fromJsonFile($filePath) {
        if (!file_exists($filePath)) {
            throw new Exception("File not found: $filePath");
        }
        return json_decode(file_get_contents($filePath), true);
    }
    
    public static function fromJsonString($jsonString) {
        return json_decode($jsonString, true);
    }
    
    /**
     * Generate CSV from data
     */
    public static function toCsv($data, $includeHeaders = true) {
        $csv = [];
        
        // Add header row
        if ($includeHeaders) {
            $csv[] = self::csvLine([
                'wikitree_id',
                'name',
                'given_name',
                'surname',
                'birth_date',
                'birth_place',
                'death_date',
                'death_place',
                'sex'
            ]);
        }
        
        // Add data rows
        if (isset($data['people']) && is_array($data['people'])) {
            foreach ($data['people'] as $person) {
                $row = [
                    $person['wikitreeId'] ?? '',
                    $person['name']['full'] ?? '',
                    $person['name']['given'] ?? '',
                    $person['name']['surname'] ?? '',
                    $person['birth']['date'] ?? '',
                    $person['birth']['place'] ?? '',
                    $person['death']['date'] ?? '',
                    $person['death']['place'] ?? '',
                    $person['sex'] ?? ''
                ];
                $csv[] = self::csvLine($row);
            }
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Generate CSV lookup file (minimal: just ID and Name)
     */
    public static function toLookupCsv($data, $includeHeaders = true) {
        $csv = [];
        
        if ($includeHeaders) {
            $csv[] = self::csvLine(['wikitree_id', 'name']);
        }
        
        if (isset($data['people']) && is_array($data['people'])) {
            foreach ($data['people'] as $person) {
                $csv[] = self::csvLine([
                    $person['wikitreeId'] ?? '',
                    $person['name']['full'] ?? ''
                ]);
            }
        }
        
        return implode("\n", $csv);
    }
    
    /**
     * Escape and format CSV line
     */
    private static function csvLine($fields) {
        $escaped = array_map(function($field) {
            // Escape quotes and wrap in quotes if contains comma, quote, or newline
            if (preg_match('/[,"\n]/', $field)) {
                return '"' . str_replace('"', '""', $field) . '"';
            }
            return $field;
        }, $fields);
        
        return implode(',', $escaped);
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    if ($argc < 2) {
        echo "Usage: php csv_exporter.php <json_file> [output_file] [lookup|full]\n";
        echo "  lookup: Export wikitree_id and name only (default)\n";
        echo "  full: Export full genealogy data\n";
        exit(1);
    }
    
    try {
        $data = CsvExporter::fromJsonFile($argv[1]);
        $type = $argv[3] ?? 'lookup';
        
        $csv = ($type === 'full') 
            ? CsvExporter::toCsv($data) 
            : CsvExporter::toLookupCsv($data);
        
        if (isset($argv[2])) {
            file_put_contents($argv[2], $csv);
            echo "Exported to: {$argv[2]}\n";
        } else {
            echo $csv;
        }
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
