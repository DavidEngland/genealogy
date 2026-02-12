<?php

/**
 * WikiTree ID Lookup Tool
 * 
 * Parses genealogy markdown files to extract person information and queries WikiTree's API
 * to find potential matches for unidentified individuals.
 * 
 * Usage: php wikitree-lookup.php Hargrove-286.md
 */

class WikiTreeLookup {
    private $apiBaseUrl = 'https://api.wikitree.com/api.php';
    private $dateSpread = 5; // years
    private $maxResults = 3;
    private $inputFile;
    private $people = [];
    private $results = [];

    public function __construct($filename) {
        $this->inputFile = $filename;
        if (!file_exists($this->inputFile)) {
            throw new Exception("File not found: {$this->inputFile}");
        }
    }

    /**
     * Parse markdown file to extract person data
     */
    public function parsePeople() {
        $content = file_get_contents($this->inputFile);
        
        // Extract main person from biography section
        if (preg_match("/'''([^']+)'''\s*\(?(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+(\d{4})/i", 
            $content, $matches)) {
            
            $this->addPerson([
                'name' => trim($matches[1]),
                'birthDate' => $this->formatDate($matches[2], $matches[3], $matches[4]),
                'type' => 'main'
            ]);
        }

        // Extract children from the Children section
        // Pattern: [[Hargrove-## | Name]] (birth–death)
        if (preg_match("/=== Children ===\s*(.*?)(?:== |$)/is", $content, $section)) {
            $childrenText = $section[1];
            
            // Match each list item with name and dates
            if (preg_match_all("/\[\[Hargrove-#+\s*\|\s*([^\]]+)\]\]\s*\((\d{4})(?:–(\d{4}))?\)/", $childrenText, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $name = trim($match[1]);
                    $birthYear = $match[2];
                    $deathYear = isset($match[3]) && $match[3] ? $match[3] : null;
                    
                    // Try to find more complete birth date in content
                    $birthDate = $this->findPersonBirthDate($content, $name, $birthYear);
                    
                    $this->addPerson([
                        'name' => $name,
                        'birthDate' => $birthDate,
                        'birthYear' => $birthYear,
                        'deathYear' => $deathYear,
                        'type' => 'child'
                    ]);
                }
            }
        }

        // Extract father and mother
        if (preg_match("/father,\s*\[\[Hargrove-#+\s*\|\s*([^\]]+)\]\]/i", $content, $match)) {
            $this->addPerson([
                'name' => trim($match[1]),
                'type' => 'parent'
            ]);
        }

        if (preg_match("/mother,\s*\[\[Lewis-#+\s*\|\s*([^\]]+)\]\]/i", $content, $match)) {
            $this->addPerson([
                'name' => trim($match[1]),
                'type' => 'parent'
            ]);
        }

        return count($this->people);
    }

    /**
     * Find complete birth date for a person from content if available
     */
    private function findPersonBirthDate($content, $name, $birthYear) {
        // Try to find full date like "born on 4 January 1840"
        $escaped = preg_quote($name, '/');
        if (preg_match("/{$escaped}.*?born\s+(?:on\s+)?(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+{$birthYear}/i", 
            $content, $matches)) {
            return $this->formatDate($matches[1], $matches[2], $birthYear);
        }
        
        // Also try pattern "birth, date day month year"
        if (preg_match("/{$escaped}.*?\((\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December)\s+{$birthYear}/i", 
            $content, $matches)) {
            return $this->formatDate($matches[1], $matches[2], $birthYear);
        }
        
        // Fall back to year only
        return "{$birthYear}-00-00";
    }

    /**
     * Add person to lookup list (avoid duplicates)
     */
    private function addPerson($person) {
        // Check if person already exists
        foreach ($this->people as $existing) {
            if (strtolower($existing['name']) === strtolower($person['name'])) {
                return;
            }
        }
        $this->people[] = $person;
    }

    /**
     * Format date components into YYYY-MM-DD format
     */
    private function formatDate($day, $month, $year) {
        $months = [
            'January' => '01', 'February' => '02', 'March' => '03', 'April' => '04',
            'May' => '05', 'June' => '06', 'July' => '07', 'August' => '08',
            'September' => '09', 'October' => '10', 'November' => '11', 'December' => '12'
        ];
        
        $monthNum = $months[ucfirst(strtolower($month))] ?? '00';
        $dayNum = str_pad($day, 2, '0', STR_PAD_LEFT);
        
        return "{$year}-{$monthNum}-{$dayNum}";
    }

    /**
     * Search for person in WikiTree API
     */
    public function searchPerson($person) {
        $firstName = $this->extractFirstName($person['name']);
        $lastName = $this->extractLastName($person['name']);

        // Try 1: Complete birth date if available (with date spread)
        $birthDate = $person['birthDate'] ?? '';
        if (!empty($birthDate) && $birthDate !== '0000-00-00' && substr($birthDate, 5) !== '00-00') {
            $results = $this->apiSearch([
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'BirthDate' => $birthDate,
                'dateSpread' => $this->dateSpread
            ]);
            
            if (!empty($results)) {
                return $this->formatResults($person, $results);
            }
        }

        // Try 2: Birth year only if complete date didn't work
        $birthYear = $person['birthYear'] ?? '';
        if (!empty($birthYear)) {
            $yearDate = $birthYear . '-00-00';
            $results = $this->apiSearch([
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'BirthDate' => $yearDate,
                'dateSpread' => $this->dateSpread
            ]);
            
            if (!empty($results)) {
                return $this->formatResults($person, $results);
            }
        }

        // Try 3: Death date if available
        if (!empty($person['deathYear'])) {
            $deathDate = ($person['deathYear'] ?? '') . '-00-00';
            $results = $this->apiSearch([
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'DeathDate' => $deathDate
            ]);
            
            if (!empty($results)) {
                return $this->formatResults($person, $results);
            }
        }

        // Try 4: Name only
        $results = $this->apiSearch([
            'FirstName' => $firstName,
            'LastName' => $lastName
        ]);

        if (!empty($results)) {
            return $this->formatResults($person, $results);
        }

        return null;
    }

    /**
     * Call WikiTree API
     */
    private function apiSearch($params) {
        $params['action'] = 'searchPerson';
        $params['limit'] = $this->maxResults;
        $params['fields'] = 'Id,Name,FirstName,BirthDate,DeathDate';

        $url = $this->apiBaseUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WikiTree-PHP-Lookup/1.0');

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode === 429) {
            throw new Exception("WikiTree API rate limit exceeded. Please wait and try again.");
        }

        if ($httpCode !== 200) {
            return [];
        }

        $data = json_decode($response, true);
        
        if (isset($data[0]['matches'])) {
            return array_slice($data[0]['matches'], 0, $this->maxResults);
        }

        return [];
    }

    /**
     * Extract first name from full name
     */
    private function extractFirstName($fullName) {
        // Remove middle initials and nicknames in quotes
        $name = preg_replace("/\s+'[^']+'/", '', $fullName);
        $name = preg_replace("/\s+[A-Z]\..*$/", '', $name);
        
        $parts = explode(' ', trim($name));
        return $parts[0] ?? '';
    }

    /**
     * Extract last name from full name
     */
    private function extractLastName($fullName) {
        // Remove middle initials and nicknames
        $name = preg_replace("/\s+'[^']+'/", '', $fullName);
        $name = preg_replace("/\s+[A-Z]\..*$/", '', $name);
        
        $parts = explode(' ', trim($name));
        return end($parts) ?? '';
    }

    /**
     * Format API results into readable output
     */
    private function formatResults($person, $results) {
        $output = [];
        $output[] = "\n" . str_repeat("-", 80);
        $output[] = "Name: {$person['name']}";
        
        if (!empty($person['birthDate']) && $person['birthDate'] !== '0000-00-00') {
            $output[] = "Birth: {$person['birthDate']}";
        } elseif (!empty($person['birthYear'])) {
            $output[] = "Birth Year: {$person['birthYear']}";
        }
        
        if (!empty($person['deathYear'])) {
            $output[] = "Death Year: {$person['deathYear']}";
        }
        
        $output[] = "\nMatches Found:";

        foreach ($results as $result) {
            if (isset($result['Name'])) {
                $id = substr($result['Name'], strrpos($result['Name'], '-') + 1);
                $lastName = substr($result['Name'], 0, strrpos($result['Name'], '-'));
                
                $link = "https://wikitree.com/wiki/{$lastName}-{$id}";
                $birthInfo = $result['BirthDate'] ?? 'Unknown';
                $deathInfo = $result['DeathDate'] ?? 'Unknown';
                
                $output[] = "  • {$result['FirstName']} {$lastName} ({$birthInfo} - {$deathInfo})";
                $output[] = "    {$link}";
            }
        }

        return implode("\n", $output);
    }

    /**
     * Run the lookup for all people
     */
    public function run() {
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "WikiTree ID Lookup Tool\n";
        echo "Input File: {$this->inputFile}\n";
        echo str_repeat("=", 80) . "\n";

        $count = $this->parsePeople();
        echo "Found {$count} people to look up.\n";

        if ($count === 0) {
            echo "No people found in file.\n";
            return;
        }

        $output = [];
        $output[] = "# WikiTree ID Lookup Results\n";
        $output[] = "Input File: {$this->inputFile}";
        $output[] = "Generated: " . date('Y-m-d H:i:s');
        $output[] = "\nManually verify matches and update the markdown file with confirmed WikiTree IDs.\n";

        foreach ($this->people as $person) {
            try {
                echo "Searching for: {$person['name']}...\n";
                $result = $this->searchPerson($person);
                
                if ($result) {
                    $output[] = $result;
                } else {
                    $output[] = "\n" . str_repeat("-", 80);
                    $output[] = "Name: {$person['name']}";
                    if (!empty($person['birthDate']) && $person['birthDate'] !== '0000-00-00') {
                        $output[] = "Birth: {$person['birthDate']}";
                    } elseif (!empty($person['birthYear'])) {
                        $output[] = "Birth Year: {$person['birthYear']}";
                    }
                    $output[] = "No WikiTree matches found - manual search recommended";
                }

                // Rate limiting
                sleep(1);
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
                break;
            }
        }

        // Generate output filename
        $baseName = basename($this->inputFile, '.md');
        $outputFile = dirname($this->inputFile) . '/unknowns/' . $baseName . '.md';

        // Create unknowns directory if needed
        if (!is_dir(dirname($outputFile))) {
            mkdir(dirname($outputFile), 0755, true);
        }

        file_put_contents($outputFile, implode("\n", $output));
        
        echo "\n" . str_repeat("=", 80) . "\n";
        echo "Results saved to: {$outputFile}\n";
        echo str_repeat("=", 80) . "\n";
    }
}

// Main execution
if ($argc < 2) {
    echo "Usage: php wikitree-lookup.php <input-file.md>\n";
    echo "Example: php wikitree-lookup.php Hargrove-286.md\n";
    exit(1);
}

$inputFile = $argv[1];

// If relative path, assume it's in current directory
if (!file_exists($inputFile)) {
    $inputFile = getcwd() . '/' . $inputFile;
}

try {
    $lookup = new WikiTreeLookup($inputFile);
    $lookup->run();
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
