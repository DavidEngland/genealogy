<?php
/**
 * Author: David Edward England, PhD
 * ORCID: https://orcid.org/0009-0001-2095-6646
 * Repo: https://github.com/DavidEngland/genealogy
 */
/**
 * WikiTree to Markdown Album Generator
 * Generates customizable markdown output for family heritage albums
 * 
 * Usage:
 *   php generate_album.php --id England-1357 --bio --parents --photos
 *   php generate_album.php --input people.csv --album pioneers
 */

require_once __DIR__ . '/wikitree_heritage_client.php';

class AlbumMarkdownGenerator {
    private $api;
    private $includeBio = false;
    private $includeParents = false;
    private $includePhotos = false;
    private $includeChildren = false;
    private $includeSpouses = false;

    public function __construct() {
        $this->api = new WikiTreeAPIClient(null, true);
    }

    /**
     * Generate markdown for single person
     * @param string $wikiTreeId
     * @param array $options Flags: bio, parents, photos, children, spouses
     * @return string Markdown content
     */
    public function generatePersonMarkdown($wikiTreeId, $options = []) {
        $this->includeBio = $options['bio'] ?? false;
        $this->includeParents = $options['parents'] ?? false;
        $this->includePhotos = $options['photos'] ?? false;
        $this->includeChildren = $options['children'] ?? false;
        $this->includeSpouses = $options['spouses'] ?? false;

        $person = $this->api->getPersonWithRelations($wikiTreeId);
        if (!$person) {
            return "# Error: Could not fetch WikiTree ID: {$wikiTreeId}\n\n";
        }

        $md = [];
        
        // Person header
        $md[] = "## " . $this->formatName($person);
        $md[] = "**WikiTree ID:** `{$wikiTreeId}`";
        $md[] = "";

        // Life dates
        $md[] = $this->formatLifeDates($person);
        $md[] = "";

        // Photo section
        if ($this->includePhotos) {
            $photoSection = $this->formatPhotos($wikiTreeId, $person);
            if ($photoSection) {
                $md[] = $photoSection;
                $md[] = "";
            }
        }

        // Parents section
        if ($this->includeParents && isset($person['relations'])) {
            $parentsSection = $this->formatParents($person['relations']);
            if ($parentsSection) {
                $md[] = $parentsSection;
                $md[] = "";
            }
        }

        // Spouses section
        if ($this->includeSpouses && isset($person['relations']['spouses'])) {
            $spousesSection = $this->formatSpouses($person['relations']['spouses']);
            if ($spousesSection) {
                $md[] = $spousesSection;
                $md[] = "";
            }
        }

        // Children section
        if ($this->includeChildren && isset($person['relations']['children'])) {
            $childrenSection = $this->formatChildren($person['relations']['children']);
            if ($childrenSection) {
                $md[] = $childrenSection;
                $md[] = "";
            }
        }

        // Biography section
        if ($this->includeBio && !empty($person['Biography'])) {
            $md[] = "### Biography\n";
            $md[] = $person['Biography'];
            $md[] = "";
        }

        // Notes/Story placeholder
        $md[] = "### Story & Heritage Notes\n";
        $md[] = "_Add family stories, historical context, and personal heritage notes here._\n";
        $md[] = "";

        // Photo gallery placeholder
        $md[] = "### Photo Gallery\n";
        $md[] = "_Photos can be added here during manual editing._\n";
        $md[] = "- ![Photo 1](./photos/FILENAME.jpg)";
        $md[] = "- ![Photo 2](./photos/FILENAME.jpg)\n";
        $md[] = "";

        // Sources/Links
        $md[] = "### Sources & References\n";
        $md[] = "- [WikiTree Profile](https://www.wikitree.com/wiki/" . urlencode($wikiTreeId) . ")";
        $md[] = "- [FamilySearch]()";
        $md[] = "- [Other sources]()";
        $md[] = "";

        return implode("\n", $md);
    }

    /**
     * Generate markdown for multiple people (batch)
     * @param array $people Array of ['id' => 'WikiTreeID', 'options' => [...]]
     * @return string Combined markdown
     */
    public function generateAlbumMarkdown($people) {
        $md = [];
        
        $md[] = "# Family Heritage Album\n";
        $md[] = "**Date Generated:** " . date('F d, Y') . "\n";
        $md[] = "**Editor:** David Edward England, PhD";
        $md[] = "**ORCID:** https://orcid.org/0009-0001-2095-6646";
        $md[] = "**Email:** DavidEngland@Hotmail.Com\n";
        $md[] = "**License:** Creative Commons - Public Domain\n";
        $md[] = "---\n";

        foreach ($people as $entry) {
            $wikiTreeId = $entry['id'] ?? null;
            $options = $entry['options'] ?? [];

            if (!$wikiTreeId) {
                continue;
            }

            $personMd = $this->generatePersonMarkdown($wikiTreeId, $options);
            $md[] = $personMd;
            $md[] = "\n---\n";
        }

        return implode("", $md);
    }

    /**
     * Format person name
     */
    private function formatName($person) {
        if (!empty($person['Name'])) {
            return trim($person['Name']);
        }
        
        $firstName = $person['FirstName'] ?? '';
        $lastName = $person['LastName'] ?? '';
        $name = trim($firstName . ' ' . $lastName);
        
        return !empty($name) ? $name : 'Unknown Person';
    }

    /**
     * Format life dates and location
     */
    private function formatLifeDates($person) {
        $birth = $person['BirthDate'] ?? 'Unknown';
        $death = $person['DeathDate'] ?? 'Unknown';
        $birthLoc = $person['BirthLocation'] ?? '';
        $deathLoc = $person['DeathLocation'] ?? '';

        $dates = "**Dates:** {$birth} – {$death}";
        if ($birthLoc || $deathLoc) {
            $dates .= "\n";
            if ($birthLoc) {
                $dates .= "**Birth Location:** {$birthLoc}\n";
            }
            if ($deathLoc) {
                $dates .= "**Death Location:** {$deathLoc}";
            }
        }

        return $dates;
    }

    /**
     * Format parent information
     */
    private function formatParents($relations) {
        $md = [];
        $md[] = "### Parents\n";

        if (isset($relations['father'])) {
            $father = $relations['father'];
            $md[] = "- **Father:** " . $this->formatName($father) . " ({$father['BirthDate']} – {$father['DeathDate']})";
        }

        if (isset($relations['mother'])) {
            $mother = $relations['mother'];
            $md[] = "- **Mother:** " . $this->formatName($mother) . " ({$mother['BirthDate']} – {$mother['DeathDate']})";
        }

        return count($md) > 1 ? implode("\n", $md) : null;
    }

    /**
     * Format spouse information
     */
    private function formatSpouses($spouses) {
        if (empty($spouses)) {
            return null;
        }

        $md = ["### Spouse(s)\n"];

        foreach ($spouses as $spouse) {
            $name = $this->formatName($spouse);
            $birth = $spouse['BirthDate'] ?? 'Unknown';
            $death = $spouse['DeathDate'] ?? 'Unknown';
            $md[] = "- **{$name}** ({$birth} – {$death})";
        }

        return implode("\n", $md);
    }

    /**
     * Format children information
     */
    private function formatChildren($children) {
        if (empty($children)) {
            return null;
        }

        $md = ["### Children\n"];

        foreach ($children as $child) {
            $name = $this->formatName($child);
            $birth = $child['BirthDate'] ?? 'Unknown';
            $death = $child['DeathDate'] ?? '';
            $deathStr = $death ? " – {$death}" : '';
            $md[] = "- {$name} ({$birth}{$deathStr})";
        }

        return implode("\n", $md);
    }

    /**
     * Format photo references
     */
    private function formatPhotos($wikiTreeId, $person) {
        $photos = $this->api->getPhotos($wikiTreeId);
        
        if (empty($photos)) {
            return null;
        }

        $md = ["### Available Photos\n"];

        foreach ($photos as $photo) {
            $md[] = "- [{$photo['person']}]({$photo['url']}) - WikiTree";
        }

        return implode("\n", $md);
    }
}

/**
 * Parse command line arguments
 */
function parseArgs($argv) {
    $args = [];
    for ($i = 1; $i < count($argv); $i++) {
        if (strpos($argv[$i], '--') === 0) {
            $key = substr($argv[$i], 2);
            $value = true;

            if ($i + 1 < count($argv) && strpos($argv[$i + 1], '--') !== 0) {
                $value = $argv[$i + 1];
                $i++;
            }

            $args[$key] = $value;
        }
    }
    return $args;
}

// Main execution
if (php_sapi_name() === 'cli') {
    $args = parseArgs($argv);

    if (empty($args) || isset($args['help'])) {
        echo "WikiTree Heritage Album Markdown Generator\n";
        echo "==========================================\n\n";
        echo "Usage:\n";
        echo "  php generate_album.php --id WIKITREE-ID [options]\n";
        echo "  php generate_album.php --input FILE.csv --album ALBUMNAME\n\n";
        echo "Single Person Options:\n";
        echo "  --id WIKITREE-ID       WikiTree ID (e.g., England-1357)\n";
        echo "  --bio                  Include biography text\n";
        echo "  --parents              Include parents information\n";
        echo "  --spouses              Include spouse information\n";
        echo "  --children             Include children information\n";
        echo "  --photos               Include photo references\n";
        echo "  --output FILE          Write to file (default: stdout)\n\n";
        echo "Batch Mode:\n";
        echo "  --input FILE           CSV file with people list\n";
        echo "  --album NAME           Album name/output file\n\n";
        echo "CSV Format (with header):\n";
        echo "  id,bio,parents,spouses,children,photos\n";
        echo "  England-1357,1,1,1,1,1\n";
        echo "  England-1358,1,0,0,1,1\n\n";
        exit(0);
    }

    $generator = new AlbumMarkdownGenerator();
    $output = '';

    if (isset($args['input'])) {
        // Batch mode
        $csvFile = $args['input'];
        if (!file_exists($csvFile)) {
            echo "Error: File not found: {$csvFile}\n";
            exit(1);
        }

        $handle = fopen($csvFile, 'r');
        $header = fgetcsv($handle, null, ',', '"', '\\');
        $people = [];

        while ($row = fgetcsv($handle, null, ',', '"', '\\')) {
            $person = [];
            foreach ($header as $i => $col) {
                $person[$col] = $row[$i] ?? '';
            }

            $options = [
                'bio' => (bool)($person['bio'] ?? 0),
                'parents' => (bool)($person['parents'] ?? 0),
                'spouses' => (bool)($person['spouses'] ?? 0),
                'children' => (bool)($person['children'] ?? 0),
                'photos' => (bool)($person['photos'] ?? 0),
            ];

            $people[] = [
                'id' => $person['id'] ?? $person['Id'] ?? null,
                'options' => $options
            ];
        }

        fclose($handle);

        $output = $generator->generateAlbumMarkdown($people);

        $albumName = $args['album'] ?? 'album';
        $outFile = "albums/{$albumName}.md";
        file_put_contents($outFile, $output);
        echo "✓ Generated: {$outFile}\n";

    } else if (isset($args['id'])) {
        // Single person mode
        $wikiTreeId = $args['id'];

        $options = [
            'bio' => isset($args['bio']),
            'parents' => isset($args['parents']),
            'spouses' => isset($args['spouses']),
            'children' => isset($args['children']),
            'photos' => isset($args['photos']),
        ];

        $output = $generator->generatePersonMarkdown($wikiTreeId, $options);

        if (isset($args['output'])) {
            file_put_contents($args['output'], $output);
            echo "✓ Generated: {$args['output']}\n";
        } else {
            echo $output;
        }
    } else {
        echo "Error: Must specify --id or --input\n";
        exit(1);
    }
}
?>
