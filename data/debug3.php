<?php
// Test HTTLVN endpoints để tìm nơi chứa lời bài hát
$ctx = stream_context_create(['http' => [
    'timeout' => 20,
    'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: text/html,*/*\r\n",
    'ignore_errors' => true,
]]);

$tests = [
    // Direct number redirect
    'Go?id=1'        => 'https://thanhca.httlvn.org/Home/Go?id=1&type_book=thanh-ca',
    // Song with no op (default = loi)
    'song-page'      => 'https://thanhca.httlvn.org/thanh-ca-1/cui-xin-vua-thanh-ngu-lai',
    // Loi tab
    'loi-tab'        => 'https://thanhca.httlvn.org/thanh-ca-1/cui-xin-vua-thanh-ngu-lai?op=loi',
    // API-like
    'lyric-api'      => 'https://thanhca.httlvn.org/Home/Lyric?id=1',
    'content-api'    => 'https://thanhca.httlvn.org/Home/Content?id=1',
    // JSON endpoint
    'json-api'       => 'https://thanhca.httlvn.org/api/song?id=1',
];

foreach($tests as $name => $url) {
    $html = @file_get_contents($url, false, $ctx);
    $size = strlen($html ?: '');
    $hasLyric = preg_match('/Câu\s*[1-9]|C[àa]u\s*[1-9]/u', $html);
    $hasCui = strpos($html, 'Cúi Xin') !== false || strpos($html, 'cui xin') !== false;
    $redirect = '';
    foreach($http_response_header ?? [] as $h) {
        if(stripos($h, 'Location:') === 0) $redirect = trim(substr($h, 9));
    }
    echo "[$name] size={$size} hasLyric=" . ($hasLyric?'YES':'NO') . " hasCui=" . ($hasCui?'YES':'NO');
    if($redirect) echo " redirect=$redirect";
    echo "\n";
    if($hasLyric) {
        preg_match('/(?:Câu|C[àa]u)\s*1(.*?)(?:Câu|C[àa]u)\s*2/su', $html, $m);
        if($m) echo "  Sample: " . substr(strip_tags(preg_replace('/<br\s*\/?>/i', "\n", $m[1])), 0, 200) . "\n";
    }
}
