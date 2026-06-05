<?php
// Verify HTTLVN scraper encoding and data quality
$data = file_get_contents('http://localhost/OBSChurch/api/httlvn.php?action=song&id=2');
$song = json_decode($data, true);

echo "=== Bài " . $song['num'] . " ===\n";
echo "Title: " . $song['title'] . "\n";
echo "Cat: " . $song['cat_top'] . "\n";
echo "Sections: " . count($song['sections']) . "\n";
echo "Raw lines: " . count($song['rawLines']) . "\n\n";

foreach ($song['sections'] as $sec) {
    echo "--- " . $sec['label'] . " ---\n";
    foreach ($sec['lines'] as $line) {
        echo "  " . $line . "\n";
    }
}

// Test pages endpoint
echo "\n\n=== Pages ===\n";
$pdata = file_get_contents('http://localhost/OBSChurch/api/httlvn.php?action=pages');
$pages = json_decode($pdata, true);
print_r($pages);
