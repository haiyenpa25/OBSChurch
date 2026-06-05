<?php
$html = file_get_contents('d:/Xampp/htdocs/OBSChurch/data/debug_song1.html');

// Find JS files
echo "=== JS FILES ===\n";
preg_match_all('#["\']([^"\']+\.js[^"\']*)["\']#i', $html, $m);
foreach(array_unique($m[1]) as $s) echo "JS: $s\n";

// Find fetch calls
echo "\n=== FETCH/AJAX ===\n";
preg_match_all('#fetch\s*\(\s*["\']([^"\']+)["\']#i', $html, $m2);
foreach(array_unique($m2[1]) as $s) echo "FETCH: $s\n";

// API paths
echo "\n=== API/ENDPOINT ===\n";
preg_match_all('#(/api/[^\s"\'<>]+|/Home/[^\s"\'<>]+|/thanh-ca[^\s"\'<>]+ajax[^\s"\'<>]*)#i', $html, $m3);
foreach(array_unique($m3[0]) as $s) echo "API: $s\n";

// data attributes
echo "\n=== DATA ATTRS ===\n";
preg_match_all('/data-[a-z-]+="([^"]+)"/i', $html, $m4);
foreach(array_slice(array_unique($m4[0]), 0, 20) as $s) echo "DATA: $s\n";

// script content
echo "\n=== INLINE SCRIPTS ===\n";
preg_match_all('#<script[^>]*>(.*?)</script>#si', $html, $ms);
foreach($ms[1] as $sc) {
    $sc = trim($sc);
    if(strlen($sc) > 20) {
        echo "SCRIPT: " . substr($sc, 0, 500) . "\n---\n";
    }
}
