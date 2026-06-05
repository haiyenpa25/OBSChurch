<?php
/**
 * Test tải 1 bài — debug lyric-content extraction
 */
$id = intval($_GET['id'] ?? 1);

$ctx = stream_context_create(['http' => [
    'timeout' => 25,
    'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: text/html\r\nAccept-Language: vi-VN,vi;q=0.9\r\n",
    'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 5,
]]);

// Tìm URL thật
$rHtml = @file_get_contents("https://thanhca.httlvn.org/Home/Go?id={$id}&type_book=thanh-ca", false, $ctx) ?: '';
if ($rHtml && !mb_check_encoding($rHtml, 'UTF-8')) $rHtml = mb_convert_encoding($rHtml, 'UTF-8', 'auto');

$songUrl = ''; $songSlug = '';
if (preg_match('#(/thanh-ca-\d+/[^"\'?\s&]+)#i', $rHtml, $m)) {
    $songSlug = ltrim($m[1], '/');
    $songUrl  = 'https://thanhca.httlvn.org' . $m[1];
}

if (!$songUrl) { echo json_encode(['error' => "No URL for id=$id"], JSON_UNESCAPED_UNICODE); exit; }

// Tải trang bài hát
$html = @file_get_contents($songUrl, false, $ctx) ?: '';
if ($html && !mb_check_encoding($html, 'UTF-8')) $html = mb_convert_encoding($html, 'UTF-8', 'auto');

// Tìm vị trí lyric-content trong HTML
$pos = strpos($html, 'id="lyric-content"');
$found = $pos !== false;

// Lấy từ lyric-content đến cuối div — strategy: lấy tất cả text sau tag này
$rawLines = []; $sections = [];

if ($found) {
    // Lấy tất cả HTML từ sau id="lyric-content">
    $startPos = strpos($html, '>', $pos) + 1;
    // Lấy ~5000 chars
    $chunk = substr($html, $startPos, 5000);
    
    // Convert <br> và </p><p> thành newlines
    $chunk = preg_replace('#</p>\s*<p[^>]*>#si', "\n", $chunk);
    $chunk = preg_replace('#<p[^>]*>#si', '', $chunk);
    $chunk = preg_replace('#</p>#si', "\n", $chunk);
    $chunk = preg_replace('#<br\s*/?>#si', "\n", $chunk);
    
    // Strip tất cả tags
    $text = strip_tags($chunk);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if (!$line) continue;
        // Bỏ navigation (← 001  003 →)
        if (preg_match('/^[←→\s\d]+$/', $line)) continue;
        // Bỏ dòng chỉ có chữ số, mũi tên
        if (mb_strlen($line) < 2) continue;
        // Dừng khi gặp phần ngoài lyrics (control buttons etc.)
        if (preg_match('/Cỡ chữ|Fullscreen|glyphicon|btn-|nav-tabs/i', $line)) break;
        $rawLines[] = $line;
    }
    
    // Parse sections
    $curLabel = null; $curLines = [];
    foreach ($rawLines as $line) {
        if (preg_match('/^(Câu\s*\d+|Điệp\s*[Kk]húc|ĐK\s*:?)/ui', $line)) {
            if (!empty($curLines)) $sections[] = ['label' => $curLabel ?? 'Câu 1', 'lines' => $curLines];
            $curLabel = preg_replace('/[:\s]+$/', '', trim($line));
            $curLines = [];
        } else {
            $curLines[] = $line;
        }
    }
    if (!empty($curLines)) $sections[] = ['label' => $curLabel ?? 'Câu 1', 'lines' => $curLines];
}

// Title
$title = '';
if (preg_match('#<title>([^<]+)</title>#i', $html, $m)) {
    $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/Thánh Ca \d+:\s*(.+?)(?:\s*-\s*Thánh Ca|$)/ui', $raw, $tm)) $title = trim($tm[1]);
}

// Cat
$catTop = ''; $catSub = '';
if (preg_match_all('#cat_top=[^"&]+\"[^>]*>([^<]+)<#u', $html, $cats)) $catTop = html_entity_decode(trim($cats[1][0]??''), ENT_QUOTES|ENT_HTML5,'UTF-8');
if (preg_match_all('#cat_sub=[^"&]+\"[^>]*>([^<]+)<#u', $html, $csubs)) $catSub = html_entity_decode(trim($csubs[1][0]??''), ENT_QUOTES|ENT_HTML5,'UTF-8');

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'ok'        => !empty($sections),
    'id'        => $id,
    'url'       => $songUrl,
    'title'     => $title,
    'cat_top'   => $catTop,
    'cat_sub'   => $catSub,
    'utf8_ok'   => mb_check_encoding($html, 'UTF-8'),
    'html_size' => strlen($html),
    'lyric_found' => $found,
    'lyric_pos' => $pos,
    'line_count'=> count($rawLines),
    'sections'  => $sections,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
