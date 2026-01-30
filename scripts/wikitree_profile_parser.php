#!/usr/bin/env php
<?php
/**
 * WikiTree Profile Parser
 * 
 * Fetches WikiTree profile pages and extracts family relationships
 * (parents, siblings, spouses, children) formatted as markdown with
 * [[WikiTree-ID|Person Name]] syntax.
 * 
 * Usage:
 *   php wikitree_profile_parser.php --profile Lewis-8883
 *   php wikitree_profile_parser.php --profile Lewis-8883 --output Lewis-8883-family.md
 *   php wikitree_profile_parser.php --profile Lewis-8883 --section parents
 *   php wikitree_profile_parser.php --profile Lewis-8883 --section all
 * 
 * Options:
 *   --profile ID       WikiTree profile ID (e.g., Lewis-8883)
 *   --output FILE      Output file path (default: stdout)
 *   --section NAME     Section to output: parents, siblings, spouses, children, all (default: all)
 *   --help            Show this help message
 */

class WikiTreeProfileParser {
    private $profileId;
    private $html;
    private $dom;
    private $xpath;
    private $baseUrl = 'https://www.wikitree.com';
    
    public function __construct($profileId) {
        $this->profileId = $profileId;
    }
    
    /**
     * Fetch the WikiTree profile HTML
     */
    public function fetchProfile() {
        $url = $this->baseUrl . '/wiki/' . $this->profileId;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WikiTree Profile Parser/1.0 (Genealogy Research)');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $this->html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . $error);
        }
        
        // Note: curl_close() is deprecated in PHP 8.5+ and has no effect since PHP 8.0
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP error $httpCode fetching profile $this->profileId");
        }
        
        if (empty($this->html)) {
            throw new Exception("Empty response for profile $this->profileId");
        }
        
        return $this;
    }
    
    /**
     * Parse the HTML into a DOM document
     */
    public function parseHTML() {
        if (empty($this->html)) {
            throw new Exception("No HTML to parse. Call fetchProfile() first.");
        }
        
        // Suppress HTML parsing warnings
        libxml_use_internal_errors(true);
        
        $this->dom = new DOMDocument();
        $this->dom->loadHTML($this->html);
        
        libxml_clear_errors();
        
        $this->xpath = new DOMXPath($this->dom);
        
        return $this;
    }
    
    /**
     * Extract WikiTree ID from a URL
     */
    private function extractWikiTreeId($url) {
        if (preg_match('/\/wiki\/([A-Z][a-z]+-\d+)/', $url, $matches)) {
            return $matches[1];
        }
        return null;
    }
    
    /**
     * Get text content from a node, trimming whitespace
     */
    private function getNodeText($node) {
        if (!$node) {
            return '';
        }
        return trim($node->textContent);
    }
    
    /**
     * Extract parents information
     */
    public function getParents() {
        $parents = [];
        
        // Find parent spans with itemprop="parent"
        $parentNodes = $this->xpath->query('//span[@itemprop="parent"]');
        
        foreach ($parentNodes as $parentNode) {
            // Find the link within the parent span
            $linkNodes = $this->xpath->query('.//a[@href]', $parentNode);
            if ($linkNodes->length > 0) {
                $link = $linkNodes->item(0);
                $href = $link->getAttribute('href');
                $wikiTreeId = $this->extractWikiTreeId($href);
                
                // Get the name from the span with itemprop="name"
                $nameNodes = $this->xpath->query('.//span[@itemprop="name"]', $parentNode);
                $name = $nameNodes->length > 0 ? $this->getNodeText($nameNodes->item(0)) : '';
                
                if ($wikiTreeId && $name) {
                    // Determine if father or mother based on the id attribute
                    $parentId = $parentNode->getAttribute('id');
                    $role = '';
                    
                    // Check the preceding text to determine father/mother
                    $parentsSection = $this->xpath->query('//p[@id="Parents"]');
                    if ($parentsSection->length > 0) {
                        $text = $this->getNodeText($parentsSection->item(0));
                        // The structure is typically "Son/Daughter of [Father] and [Mother]"
                        // We'll use position to determine role
                    }
                    
                    $parents[] = [
                        'id' => $wikiTreeId,
                        'name' => $name,
                        'role' => $role
                    ];
                }
            }
        }
        
        return $parents;
    }
    
    /**
     * Extract siblings information
     */
    public function getSiblings() {
        $siblings = [];
        
        // Find sibling spans with itemprop="sibling"
        $siblingNodes = $this->xpath->query('//span[@itemprop="sibling"]');
        
        foreach ($siblingNodes as $siblingNode) {
            $linkNodes = $this->xpath->query('.//a[@href]', $siblingNode);
            if ($linkNodes->length > 0) {
                $link = $linkNodes->item(0);
                $href = $link->getAttribute('href');
                $wikiTreeId = $this->extractWikiTreeId($href);
                
                $nameNodes = $this->xpath->query('.//span[@itemprop="name"]', $siblingNode);
                $name = $nameNodes->length > 0 ? $this->getNodeText($nameNodes->item(0)) : '';
                
                if ($wikiTreeId && $name) {
                    $siblings[] = [
                        'id' => $wikiTreeId,
                        'name' => $name
                    ];
                }
            }
        }
        
        return $siblings;
    }
    
    /**
     * Extract spouse information
     */
    public function getSpouses() {
        $spouses = [];
        
        // Find spouse divs within the Spouses section
        $spouseNodes = $this->xpath->query('//div[@id="Spouses"]//div[@class="spouse"]');
        
        foreach ($spouseNodes as $spouseNode) {
            // Find the spouse span with itemprop="spouse"
            $spouseSpans = $this->xpath->query('.//span[@itemprop="spouse"]', $spouseNode);
            
            if ($spouseSpans->length > 0) {
                $spouseSpan = $spouseSpans->item(0);
                $linkNodes = $this->xpath->query('.//a[@href]', $spouseSpan);
                
                if ($linkNodes->length > 0) {
                    $link = $linkNodes->item(0);
                    $href = $link->getAttribute('href');
                    $wikiTreeId = $this->extractWikiTreeId($href);
                    
                    $nameNodes = $this->xpath->query('.//span[@itemprop="name" or @class="spouse-name"]', $spouseSpan);
                    $name = $nameNodes->length > 0 ? $this->getNodeText($nameNodes->item(0)) : '';
                    
                    // Extract marriage date
                    $marriageDateNodes = $this->xpath->query('.//span[@class="marriage-date"]', $spouseNode);
                    $marriageDate = $marriageDateNodes->length > 0 ? $this->getNodeText($marriageDateNodes->item(0)) : '';
                    
                    // Extract marriage location
                    $marriageLocationNodes = $this->xpath->query('.//span[@class="marriage-location"]', $spouseNode);
                    $marriageLocation = $marriageLocationNodes->length > 0 ? $this->getNodeText($marriageLocationNodes->item(0)) : '';
                    
                    if ($wikiTreeId && $name) {
                        $spouses[] = [
                            'id' => $wikiTreeId,
                            'name' => $name,
                            'marriage_date' => $marriageDate,
                            'marriage_location' => $marriageLocation
                        ];
                    }
                }
            }
        }
        
        return $spouses;
    }
    
    /**
     * Extract children information
     */
    public function getChildren() {
        $children = [];
        
        // Find children spans with itemprop="children"
        $childNodes = $this->xpath->query('//span[@itemprop="children"]');
        
        foreach ($childNodes as $childNode) {
            $linkNodes = $this->xpath->query('.//a[@href]', $childNode);
            if ($linkNodes->length > 0) {
                $link = $linkNodes->item(0);
                $href = $link->getAttribute('href');
                $wikiTreeId = $this->extractWikiTreeId($href);
                
                $nameNodes = $this->xpath->query('.//span[@itemprop="name"]', $childNode);
                $name = $nameNodes->length > 0 ? $this->getNodeText($nameNodes->item(0)) : '';
                
                if ($wikiTreeId && $name) {
                    $children[] = [
                        'id' => $wikiTreeId,
                        'name' => $name
                    ];
                }
            }
        }
        
        return $children;
    }
    
    /**
     * Get the person's name from the profile
     */
    public function getPersonName() {
        // Try to get from the VITALS section
        $vitalsNodes = $this->xpath->query('//p[@class="VITALS"]');
        
        if ($vitalsNodes->length > 0) {
            $vitalsNode = $vitalsNodes->item(0);
            
            // Extract given name
            $givenNameNodes = $this->xpath->query('.//span[@itemprop="givenName"]', $vitalsNode);
            $givenName = $givenNameNodes->length > 0 ? $this->getNodeText($givenNameNodes->item(0)) : '';
            
            // Extract additional name (middle name)
            $additionalNameNodes = $this->xpath->query('.//span[@itemprop="additionalName"]', $vitalsNode);
            $additionalName = $additionalNameNodes->length > 0 ? $this->getNodeText($additionalNameNodes->item(0)) : '';
            
            // Extract family name from meta tag
            $familyNameNodes = $this->xpath->query('.//meta[@itemprop="familyName"]', $vitalsNode);
            $familyName = $familyNameNodes->length > 0 ? $familyNameNodes->item(0)->getAttribute('content') : '';
            
            // Construct full name
            $parts = array_filter([$givenName, $additionalName, $familyName]);
            return implode(' ', $parts);
        }
        
        return '';
    }
    
    /**
     * Format a person as markdown link
     */
    private function formatPersonLink($person) {
        return "[[{$person['id']}|{$person['name']}]]";
    }
    
    /**
     * Format parents section as markdown
     */
    public function formatParentsMarkdown($parents) {
        if (empty($parents)) {
            return "Parents: (Not specified)\n";
        }
        
        $output = "=== Parents ===\n";
        
        if (count($parents) == 2) {
            $output .= "Child of " . $this->formatPersonLink($parents[0]);
            $output .= " and " . $this->formatPersonLink($parents[1]) . "\n";
        } else {
            foreach ($parents as $parent) {
                $output .= "* " . $this->formatPersonLink($parent) . "\n";
            }
        }
        
        return $output . "\n";
    }
    
    /**
     * Format siblings section as markdown
     */
    public function formatSiblingsMarkdown($siblings) {
        if (empty($siblings)) {
            return "Siblings: (Not specified)\n";
        }
        
        $output = "=== Siblings ===\n";
        
        $links = array_map([$this, 'formatPersonLink'], $siblings);
        $output .= "Sibling of " . implode(', ', $links) . "\n";
        
        return $output . "\n";
    }
    
    /**
     * Format spouses section as markdown
     */
    public function formatSpousesMarkdown($spouses) {
        if (empty($spouses)) {
            return "Spouses: (Not specified)\n";
        }
        
        $output = "=== Marriage ===\n";
        
        foreach ($spouses as $spouse) {
            $output .= "Married " . $this->formatPersonLink($spouse);
            
            $details = [];
            if (!empty($spouse['marriage_date'])) {
                $details[] = "on '''{$spouse['marriage_date']}'''";
            }
            if (!empty($spouse['marriage_location'])) {
                $details[] = "in '''{$spouse['marriage_location']}'''";
            }
            
            if (!empty($details)) {
                $output .= " " . implode(' ', $details);
            }
            
            $output .= "\n";
        }
        
        return $output . "\n";
    }
    
    /**
     * Format children section as markdown
     */
    public function formatChildrenMarkdown($children) {
        if (empty($children)) {
            return "Children: (Not specified)\n";
        }
        
        $output = "=== Children ===\n";
        
        foreach ($children as $child) {
            $output .= "* " . $this->formatPersonLink($child) . "\n";
        }
        
        return $output . "\n";
    }
    
    /**
     * Generate markdown output for specified sections
     */
    public function generateMarkdown($sections = ['all']) {
        $output = "# Family Information for {$this->profileId}\n\n";
        
        $personName = $this->getPersonName();
        if ($personName) {
            $output .= "**Person**: [[{$this->profileId}|{$personName}]]\n\n";
        }
        
        $includeAll = in_array('all', $sections);
        
        if ($includeAll || in_array('parents', $sections)) {
            $parents = $this->getParents();
            $output .= $this->formatParentsMarkdown($parents);
        }
        
        if ($includeAll || in_array('siblings', $sections)) {
            $siblings = $this->getSiblings();
            $output .= $this->formatSiblingsMarkdown($siblings);
        }
        
        if ($includeAll || in_array('spouses', $sections)) {
            $spouses = $this->getSpouses();
            $output .= $this->formatSpousesMarkdown($spouses);
        }
        
        if ($includeAll || in_array('children', $sections)) {
            $children = $this->getChildren();
            $output .= $this->formatChildrenMarkdown($children);
        }
        
        return $output;
    }
}

/**
 * Parse command line arguments
 */
function parseArgs($argv) {
    $options = [
        'profile' => null,
        'output' => null,
        'section' => ['all'],
        'help' => false
    ];
    
    for ($i = 1; $i < count($argv); $i++) {
        switch ($argv[$i]) {
            case '--profile':
                if (isset($argv[$i + 1])) {
                    $options['profile'] = $argv[++$i];
                }
                break;
            case '--output':
                if (isset($argv[$i + 1])) {
                    $options['output'] = $argv[++$i];
                }
                break;
            case '--section':
                if (isset($argv[$i + 1])) {
                    $options['section'] = [$argv[++$i]];
                }
                break;
            case '--help':
            case '-h':
                $options['help'] = true;
                break;
        }
    }
    
    return $options;
}

/**
 * Show help message
 */
function showHelp() {
    echo <<<HELP
WikiTree Profile Parser

Usage:
  php wikitree_profile_parser.php --profile PROFILE_ID [OPTIONS]

Options:
  --profile ID       WikiTree profile ID (required, e.g., Lewis-8883)
  --output FILE      Output file path (default: stdout)
  --section NAME     Section to output: parents, siblings, spouses, children, all (default: all)
  --help, -h         Show this help message

Examples:
  php wikitree_profile_parser.php --profile Lewis-8883
  php wikitree_profile_parser.php --profile Lewis-8883 --output Lewis-8883-family.md
  php wikitree_profile_parser.php --profile Lewis-8883 --section parents
  php wikitree_profile_parser.php --profile Hargrove-277 --section spouses

HELP;
}

/**
 * Main execution
 */
function main($argv) {
    $options = parseArgs($argv);
    
    if ($options['help']) {
        showHelp();
        exit(0);
    }
    
    if (empty($options['profile'])) {
        fwrite(STDERR, "Error: --profile is required\n\n");
        showHelp();
        exit(1);
    }
    
    try {
        $parser = new WikiTreeProfileParser($options['profile']);
        
        echo "Fetching profile {$options['profile']}...\n";
        $parser->fetchProfile();
        
        echo "Parsing HTML...\n";
        $parser->parseHTML();
        
        echo "Generating markdown...\n";
        $markdown = $parser->generateMarkdown($options['section']);
        
        // Output to file or stdout
        if ($options['output']) {
            file_put_contents($options['output'], $markdown);
            echo "Written to {$options['output']}\n";
        } else {
            echo "\n" . str_repeat('=', 60) . "\n";
            echo $markdown;
        }
        
        exit(0);
        
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

// Run if called directly
if (php_sapi_name() === 'cli') {
    main($argv);
}
