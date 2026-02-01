<?php
$filePath = '/Users/davidengland/Documents/GitHub/genealogy/GEDs/NicholosWelch-offshoots.ged';
$handle = fopen($filePath, 'r');
$individuals = [];
$currentId = null;
$count = 0;

while (($line = fgets($handle)) !== false && $count < 50) {
    $count++;
    $line = rtrim("\r\n");
    
    if (empty($line)) continue;
    
    $parts = preg_split('/\s+/', $line, 3);
    if (count($parts) < 2) {
        echo "Line $count: Not enough parts: " . count($parts) . " - '$line'\n";
        continue;
    }
    
    $level = $parts[0];
    $tag = $parts[1];
    $value = isset($parts[2]) ? $parts[2] : '';
    
    echo "Line $count: level='$level' tag='$tag' value='$value'\n";
    
    if ($level === '0' && preg_match('/^@I\d+@$/', $tag)) {
        $currentId = $tag;
        $individuals[$currentId] = ['id' => $currentId];
        echo "  -> FOUND INDIVIDUAL: $currentId\n";
    }
}
fclose($handle);
echo "\nTotal found: " . count($individuals) . "\n";

