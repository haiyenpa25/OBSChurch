<?php
// Tìm pattern chính xác của lời bài hát trong HTML HTTLVN
$html = file_get_contents('d:/Xampp/htdocs/OBSChurch/data/debug_song1.html');

echo "=== Raw HTML around 'Câu' keyword ===\n";
// Find position of "Câu"
$pos = mb_strpos($html, 'Câu');
if($pos !== false) {
    echo "Found at byte position: $pos\n";
    echo "Context (±300 chars):\n";
    echo htmlspecialchars_decode(substr($html, max(0,$pos-100), 700)) . "\n";
}

echo "\n\n=== Search for verse patterns ===\n";
// Pattern: text between Câu markers
preg_match_all('/Câu\s*(\d+)[\s\S]{0,20}?(?:<\/[^>]+>)?\s*([\s\S]{30,500}?)(?=Câu\s*\d+|Điệp khúc|<\/div>|<div class="row)/u', $html, $ms, PREG_SET_ORDER);
foreach($ms as $m) {
    echo "--- Câu {$m[1]} ---\n";
    $text = preg_replace('/<br\s*\/?>/i', "\n", $m[2]);
    $text = strip_tags($text);
    $text = html_entity_decode(trim($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    echo $text . "\n";
}

echo "\n\n=== Try: get full content between tabs ===\n";
// Look for the lyric tab content
if(preg_match('#class="tab-pane active[^"]*">([\s\S]+?)(?:class="tab-pane[^a]|</div>\s*</div>\s*</div>\s*</div>\s*<div class="row")#si', $html, $m)) {
    $content = $m[1];
    $content = preg_replace('/<br\s*\/?>/i', "\n", $content);
    $content = strip_tags($content);
    $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $content = trim(preg_replace('/\n{3,}/', "\n\n", $content));
    echo substr($content, 0, 1000) . "\n";
}
