<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree API Client
 * 
 * Provides reusable functions for interacting with the WikiTree API.
 * Documentation: https://github.com/wikitree/wikitree-api
 * 
 * @author David England
 * @date 2026-02-01
 */

class WikiTreeAPI {
    private const API_ENDPOINT = 'https://api.wikitree.com/api.php';
    private const APP_ID = 'genealogy_parser_v1';
    private const USER_AGENT = 'WikiTreeAPIClient/1.0';
    
    private $verbose = false;
    private $logFile = null;
    
    /**
     * Constructor
     * 
     * @param bool $verbose Enable verbose logging
     * @param string|null $logFile Path to error log file
     */
    public function __construct(bool $verbose = false, ?string $logFile = null) {
        $this->verbose = $verbose;
        $this->logFile = $logFile;
    }
    
    /**
     * Make a generic API call
     * 
     * @param string $action API action (getProfile, getRelatives, etc.)
     * @param array $params Additional parameters
     * @return array|false Decoded JSON response or false on failure
     */
    public function callAPI(string $action, array $params = []) {
        // Build query parameters
        $params['action'] = $action;
        $params['appId'] = self::APP_ID;
        
        $url = self::API_ENDPOINT . '?' . http_build_query($params);
        
        $this->debug("API Request: $url");
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::USER_AGENT);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        // Note: curl_close() deprecated in PHP 8.5, resources auto-freed
        
        // Check for cURL errors
        if ($response === false) {
            $this->logError("cURL error: $curlError");
            return false;
        }
        
        // Check HTTP response code
        if ($httpCode !== 200) {
            $this->logError("HTTP error: $httpCode");
            return false;
        }
        
        // Decode JSON response
        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->logError("JSON decode error: " . json_last_error_msg());
            return false;
        }
        
        $this->debug("API Response received: " . strlen($response) . " bytes");
        
        return $data;
    }
    
    /**
     * Fetch a single profile
     * 
     * @param string $id WikiTree ID (e.g., "Lewis-8883")
     * @param array $fields Optional field list (default: comprehensive set)
     * @return array|false Profile data or false on failure
     */
    public function fetchProfile(string $id, array $fields = []) {
        // Default comprehensive field list
        if (empty($fields)) {
            $fields = [
                'Id', 'PageId', 'Name', 'FirstName', 'MiddleName', 'LastNameAtBirth', 
                'LastNameCurrent', 'RealName', 'Nicknames', 'Prefix', 'Suffix',
                'BirthDate', 'DeathDate', 'BirthLocation', 'DeathLocation',
                'Gender', 'IsLiving', 'Photo', 'PhotoData',
                'Father', 'Mother', 'Parents', 'Children', 'Spouses', 'Siblings',
                'Created', 'Touched', 'Privacy', 'Manager'
            ];
        }
        
        $params = [
            'key' => $id,
            'fields' => implode(',', $fields)
        ];
        
        $response = $this->callAPI('getProfile', $params);
        
        if ($response === false) {
            return false;
        }
        
        // Check for API-level errors
        if (isset($response[0]['status'])) {
            $status = $response[0]['status'];
            if ($status !== 0) {
                $this->logError("API returned status $status for profile $id");
                return false;
            }
        }
        
        // Check if profile exists
        if (!isset($response[0]['profile'])) {
            $this->logError("No profile data returned for $id");
            return false;
        }
        
        return $response[0];
    }
    
    /**
     * Fetch profile with relatives
     * 
     * @param string $id WikiTree ID
     * @param array $keys Relationship keys (Parents, Children, Siblings, Spouses)
     * @param array $fields Optional field list
     * @return array|false Profile data with relatives or false on failure
     */
    public function fetchRelatives(string $id, array $keys = [], array $fields = []) {
        // Default to all relationship types
        if (empty($keys)) {
            $keys = ['Parents', 'Children', 'Siblings', 'Spouses'];
        }
        
        // Default comprehensive field list
        if (empty($fields)) {
            $fields = [
                'Id', 'PageId', 'Name', 'FirstName', 'MiddleName', 'LastNameAtBirth', 
                'LastNameCurrent', 'RealName', 'Nicknames', 'Prefix', 'Suffix',
                'BirthDate', 'DeathDate', 'BirthLocation', 'DeathLocation',
                'Gender', 'IsLiving', 'Photo', 'PhotoData',
                'Father', 'Mother', 'Created', 'Touched', 'Privacy', 'Manager'
            ];
        }
        
        $params = [
            'keys' => $id,
            'getParents' => in_array('Parents', $keys) ? '1' : '0',
            'getChildren' => in_array('Children', $keys) ? '1' : '0',
            'getSiblings' => in_array('Siblings', $keys) ? '1' : '0',
            'getSpouses' => in_array('Spouses', $keys) ? '1' : '0',
            'fields' => implode(',', $fields)
        ];
        
        $response = $this->callAPI('getRelatives', $params);
        
        if ($response === false) {
            return false;
        }
        
        // Check for API-level errors
        if (isset($response[0]['status'])) {
            $status = $response[0]['status'];
            if ($status !== 0) {
                $this->logError("API returned status $status for profile $id");
                return false;
            }
        }
        
        // Check if data exists
        if (!isset($response[0]['items'])) {
            $this->logError("No relatives data returned for $id");
            return false;
        }
        
        return $response[0];
    }

    /**
     * Fetch ancestors for a profile
     *
     * @param string $id WikiTree ID
     * @param int $depth Ancestor depth (default: 5)
     * @param array $fields Optional field list
     * @return array|false Ancestors data or false on failure
     */
    public function fetchAncestors(string $id, int $depth = 5, array $fields = []) {
        if ($depth < 1) {
            $this->logError("Depth must be a positive integer");
            return false;
        }

        // Default comprehensive field list
        if (empty($fields)) {
            $fields = [
                'Id', 'PageId', 'Name', 'FirstName', 'MiddleName', 'LastNameAtBirth',
                'LastNameCurrent', 'RealName', 'Nicknames', 'Prefix', 'Suffix',
                'BirthDate', 'DeathDate', 'BirthLocation', 'DeathLocation',
                'Gender', 'IsLiving', 'Photo', 'PhotoData',
                'Father', 'Mother', 'Created', 'Touched', 'Privacy', 'Manager'
            ];
        }

        $params = [
            'key' => $id,
            'depth' => (string)$depth,
            'fields' => implode(',', $fields)
        ];

        $response = $this->callAPI('getAncestors', $params);

        if ($response === false) {
            return false;
        }

        // Check for API-level errors
        if (isset($response[0]['status'])) {
            $status = $response[0]['status'];
            if ($status !== 0) {
                $this->logError("API returned status $status for profile $id");
                return false;
            }
        }

        if (!isset($response[0])) {
            $this->logError("No ancestors data returned for $id");
            return false;
        }

        return $response[0];
    }

    /**
     * Search for people (searchPerson)
     *
     * @param array $params Search parameters
     * @return array|false Search results or false on failure
     */
    public function searchPerson(array $params = []) {
        $response = $this->callAPI('searchPerson', $params);

        if ($response === false) {
            return false;
        }

        // Check for API-level errors
        if (isset($response[0]['status'])) {
            $status = $response[0]['status'];
            if ($status !== 0) {
                $this->logError("API returned status $status for searchPerson");
                return false;
            }
        }

        if (!isset($response[0])) {
            $this->logError("No search data returned for searchPerson");
            return false;
        }

        return $response[0];
    }
    
    /**
     * Log error message
     * 
     * @param string $message Error message
     */
    private function logError(string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        
        // Always write to log file if specified
        if ($this->logFile) {
            @file_put_contents($this->logFile, $logMessage, FILE_APPEND);
        }
        
        // Also output to STDERR if verbose
        if ($this->verbose) {
            fwrite(STDERR, "Error: $message\n");
        }
    }
    
    /**
     * Log debug message (only if verbose)
     * 
     * @param string $message Debug message
     */
    private function debug(string $message): void {
        if ($this->verbose) {
            echo "[DEBUG] $message\n";
        }
    }
    
    /**
     * Validate WikiTree ID format
     * 
     * @param string $id WikiTree ID to validate
     * @return bool True if valid format
     */
    public static function validateID(string $id): bool {
        return preg_match('/^[A-Z][a-z]+-\d+$/', $id) === 1;
    }
}
