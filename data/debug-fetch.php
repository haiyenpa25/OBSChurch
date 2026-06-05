<?php
// Debug: fetch and analyze HTTLVN song page
$ctx = stream_context_create(['http' => [
    'timeout' => 30,
    'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\nAccept: text/html,*/*\r\n",
    'ignore_errors' => true,
]]);

$url  = 'https://thanhca.httlvn.org/thanh-ca-1/cui-xin-vua-thanh-ngu-lai';
$html = file_get_contents($url, false, $ctx);

echo "=== Size: " . strlen($html) . " bytes\n\n";

// Look for lyric patterns
$patterns = [
    'pre tag'          => '/<pre[^>]*>(.*?)<\/pre>/si',
    'lyric class'      => '/<[^>]+class="[^"]*lyric[^"]*"[^>]*>(.*?)(?:<div class="row|<\/div>\s*<\/div>\s*<\/div>)/si',
    'Câu 1'            => '/(?:Câu|C[àa]u)\s*1[\s\S]{0,30}([\s\S]{50,500}?)(?:Câu|C[àa]u)\s*2/u',
    'tab-pane content' => '/<div role="tabpanel" class="tab-pane active[^"]*">([\s\S]{100,}?)(?:<div class="row row-control|<ul class="nav nav-tabs)/si',
    'container-fluid'  => '/<div class="container-fluid">([\s\S]{50,}?)(?:<div class="row row-control)/si',
];

foreach ($patterns as $name => $regex) {
    if (preg_match($regex, $html, $m)) {
        $text = strip_tags($m[1]);
        $text = html_entity_decode(trim(preg_replace('/\s+/', ' ', $text)));
        echo "✓ Pattern [$name] found:\n";
        echo substr($text, 0, 400) . "\n";
        echo "---\n";
    } else {
        echo "✗ Pattern [$name] NOT found\n";
    }
}

// Save full HTML for inspection
file_put_contents(dirname(__DIR__) . '/data/debug_song1.html', $html);
echo "\nSaved to data/debug_song1.html\n";

// Also check listing page 1 for total count
$listHtml = file_get_contents('https://thanhca.httlvn.org/thanh-ca', false, $ctx);
preg_match_all('/class="hymn-item mb-1 tc-(\d+)"/', $listHtml, $ms);
$ids = array_map('intval', $ms[1]);
echo "\nPage 1 songs: " . count($ids) . " (from " . min($ids) . " to " . max($ids) . ")\n";

// Check last page
preg_match_all('/href="\/thanh-ca\?page=(\d+)"/', $listHtml, $pm);
echo "Pages found: " . implode(', ', array_unique($pm[1])) . "\n";
