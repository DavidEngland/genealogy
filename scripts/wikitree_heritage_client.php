<?php
/**
 * WikiTree API Client for Heritage Album Project
 * Fetches profile data from WikiTree API (api.wikitree.com)
 * Returns structured data for markdown generation
 */

class WikiTreeAPIClient {
    private $baseUrl = 'https://api.wikitree.com/api.php';
    private $appId = 'HeritageAlbum'; // User agent for API
    private $cacheDir = null;
    private $useCache = true;

    public function __construct($cacheDir = null, $useCache = true) {
        $this->cacheDir = $cacheDir ?: sys_get_temp_dir() . '/wikitree_cache';
        $this->useCache = $useCache;
        
        if ($useCache && !is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get person profile by WikiTree ID
     * @param string $wikiTreeId Format: "Surname-Number" (e.g., "England-1357")
     * @return array Person data or false on error
     */
    public function getPerson($wikiTreeId) {
        $cacheFile = $this->cacheDir . '/' . str_replace('-', '_', $wikiTreeId) . '.json';
        
        // Check cache first
        if ($this->useCache && file_exists($cacheFile)) {
            $cached = json_decode(file_get_contents($cacheFile), true);
            if ($cached) {
                return $cached;
            }
        }

        // Query WikiTree API
        $params = [
            'action' => 'getProfile',
            'key' => $wikiTreeId,
            'fields' => 'Id,Name,FirstName,LastName,BirthDate,BirthLocation,DeathDate,DeathLocation,' .
                       'Gender,Father,Mother,Spouses,Children,Photo,Biography',
            'appId' => $this->appId
        ];

        $url = $this->baseUrl . '?' . http_build_query($params);
        
        $response = $this->makeRequest($url);
        if (!$response) {
            return false;
        }

        // Parse response
        $data = json_decode($response, true);
        if (!isset($data[0]) || isset($data[0]['error'])) {
            return false;
        }

        $person = $data[0];

        // Cache the result
        if ($this->useCache) {
            file_put_contents($cacheFile, json_encode($person, JSON_PRETTY_PRINT));
        }

        return $person;
    }

    /**
     * Get person profile with relations (parents, children, spouses)
     * @param string $wikiTreeId
     * @return array Extended person data with relations
     */
    public function getPersonWithRelations($wikiTreeId) {
        $person = $this->getPerson($wikiTreeId);
        if (!$person) {
            return null;
        }

        $person['relations'] = [];

        // Get parents
        if (!empty($person['Father'])) {
            $person['relations']['father'] = $this->getPerson($person['Father']);
        }
        if (!empty($person['Mother'])) {
            $person['relations']['mother'] = $this->getPerson($person['Mother']);
        }

        // Get spouses (limited to first 2)
        if (!empty($person['Spouses'])) {
            $spouses = is_array($person['Spouses']) ? $person['Spouses'] : [$person['Spouses']];
            $person['relations']['spouses'] = [];
            foreach (array_slice($spouses, 0, 2) as $spouseId) {
                $spouse = $this->getPerson($spouseId);
                if ($spouse) {
                    $person['relations']['spouses'][] = $spouse;
                }
            }
        }

        // Get children (limited to first 5)
        if (!empty($person['Children'])) {
            $children = is_array($person['Children']) ? $person['Children'] : [$person['Children']];
            $person['relations']['children'] = [];
            foreach (array_slice($children, 0, 5) as $childId) {
                $child = $this->getPerson($childId);
                if ($child) {
                    $person['relations']['children'][] = $child;
                }
            }
        }

        return $person;
    }

    /**
     * Get photos available for a person
     * Requires manual configuration - returns metadata for sourcing
     * @param string $wikiTreeId
     * @return array Photo references
     */
    public function getPhotos($wikiTreeId) {
        $person = $this->getPerson($wikiTreeId);
        if (!$person) {
            return [];
        }

        $photos = [];

        // Check if person has photo field
        if (!empty($person['Photo'])) {
            $photos[] = [
                'source' => 'wikitree',
                'url' => 'https://www.wikitree.com/photos/photo/' . $person['Photo'],
                'person' => $person['Name'],
                'wikitree_id' => $wikiTreeId
            ];
        }

        return $photos;
    }

    /**
     * Make HTTP request with retry logic
     * @param string $url
     * @param int $retries
     * @return string|false Response body or false on error
     */
    private function makeRequest($url, $retries = 3) {
        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 10,
                    'user_agent' => 'HeritageAlbum/1.0'
                ]
            ]);

            $response = @file_get_contents($url, false, $ctx);
            if ($response !== false) {
                return $response;
            }

            if ($attempt < $retries) {
                sleep(2); // Rate limiting
            }
        }

        return false;
    }

    /**
     * Clear cache for a specific person or all
     * @param string|null $wikiTreeId Specific ID or null for all
     */
    public function clearCache($wikiTreeId = null) {
        if ($wikiTreeId) {
            $cacheFile = $this->cacheDir . '/' . str_replace('-', '_', $wikiTreeId) . '.json';
            if (file_exists($cacheFile)) {
                unlink($cacheFile);
            }
        } else {
            // Clear all cache
            foreach (glob($this->cacheDir . '/*.json') as $file) {
                unlink($file);
            }
        }
    }
}
?>
