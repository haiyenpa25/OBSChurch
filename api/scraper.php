<?php
/**
 * OBSChurch — Thánh Ca Scraper + Proxy
 * Scrape lời bài hát từ thanhcatinlanh.com và cache vào local JSON
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$action = $_GET['action'] ?? 'scrape';
$cacheDir = __DIR__ . '/data/songcache/';
if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);

// ── Helpers ──────────────────────────────────────────────────────
function fetchUrl($url) {
    $ctx = stream_context_create(['http' => [
        'timeout' => 15,
        'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n"
    ]]);
    return @file_get_contents($url, false, $ctx);
}

function extractLyrics($html) {
    // Find the "Lời Bài Hát" tab content
    // Pattern: between title "Lời Bài Hát" and end of that tab
    if (preg_match('/Lời Bài Hát.*?<\/h2>(.*?)<\/div>\s*<div class="jwts_tabbertab"/si', $html, $m)) {
        $raw = $m[1];
    } elseif (preg_match('/<div class="description"[^>]*>(.*?)<\/div>\s*<div class="clr">/si', $html, $m)) {
        $raw = $m[1];
    } else {
        $raw = '';
    }
    
    // Convert <br> to newlines
    $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
    // Strip all HTML tags
    $text = strip_tags($raw);
    // Normalize whitespace
    $lines = array_filter(array_map('trim', explode("\n", $text)), fn($l) => strlen($l) > 0);
    return array_values($lines);
}

function extractSongMeta($html) {
    $meta = [
        'title' => '',
        'author' => '',
        'number' => 0,
        'key' => '',
        'category' => '',
    ];
    
    // Title
    if (preg_match('/<h1 itemprop="name">(.*?)<\/h1>/si', $html, $m)) {
        $meta['title'] = trim(strip_tags($m[1]));
    }
    
    // Author
    if (preg_match('/itemprop="name">([^<]+)<\/span>/si', $html, $m)) {
        $meta['author'] = trim($m[1]);
    }
    
    // Song number
    if (preg_match('/Bài số.*?<span[^>]*>(\d+)<\/span>/si', $html, $m)) {
        $meta['number'] = (int)$m[1];
    }
    
    // Key
    if (preg_match('/Hợp âm.*?\(([A-Gb#m]+)\)/si', $html, $m)) {
        $meta['key'] = trim($m[1]);
    }
    
    // Category from breadcrumb
    if (preg_match('/href="\/index\.php\/([^"]+)">[^<]*(Thánh ca|Tôn Vinh|Ca Khúc|Bài Ca)[^<]*<\/a>/si', $html, $m)) {
        $meta['category'] = trim(strip_tags($m[0]));
    }
    
    return $meta;
}

// ── Group lines into sections (verses by paragraph breaks) ────────
function groupIntoSections($lines) {
    $sections = [];
    $current = [];
    $sectionIdx = 1;
    
    foreach ($lines as $line) {
        // Detect verse markers like "1.", "2.", "ĐK:", "Điệp khúc", "Bridge"
        if (preg_match('/^(\d+\.|ĐK:|Điệp khúc|Bridge|Cầu nối)/i', $line)) {
            if (!empty($current)) {
                $sections[] = ['label' => 'Câu ' . $sectionIdx++, 'lines' => $current];
                $current = [];
            }
            // Don't add the marker itself, just the next lines
        } else {
            $current[] = $line;
        }
        
        // Max 4 lines per section for OBS display
        if (count($current) >= 4) {
            $sections[] = ['label' => 'Câu ' . $sectionIdx++, 'lines' => $current];
            $current = [];
        }
    }
    
    if (!empty($current)) {
        $sections[] = ['label' => 'Câu ' . $sectionIdx, 'lines' => $current];
    }
    
    return $sections ?: [['label' => 'Câu 1', 'lines' => $lines]];
}

// ── ACTIONS ─────────────────────────────────────────────────────

// GET CATALOG: list all songs from a collection
if ($action === 'catalog') {
    $collection = $_GET['collection'] ?? 'thanh-ca-httl-viet-nam';
    $cacheFile = $cacheDir . "catalog_{$collection}.json";
    
    // Return cache if fresh (< 24h)
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        echo file_get_contents($cacheFile);
        exit;
    }
    
    $url = "http://thanhcatinlanh.com/index.php/{$collection}";
    $html = fetchUrl($url);
    if (!$html) { echo json_encode(['error' => 'Cannot fetch catalog']); exit; }
    
    // Find all song links in the table
    preg_match_all('/<a href="\/index\.php\/' . preg_quote($collection, '/') . '\/(\d+-[^"]+)"[^>]*>([^<]+)</', $html, $matches);
    
    $songs = [];
    for ($i = 0; $i < count($matches[1]); $i++) {
        $slug = $matches[1][$i];
        $title = trim($matches[2][$i]);
        if (empty($title) || strlen($title) < 2) continue;
        
        // Extract song number from slug
        preg_match('/^(\d+)-/', $slug, $numMatch);
        $num = isset($numMatch[1]) ? (int)$numMatch[1] : 0;
        
        $songs[] = [
            'id'   => $slug,
            'slug' => $slug,
            'title'=> $title,
            'number' => $num,
            'url'  => "http://thanhcatinlanh.com/index.php/{$collection}/{$slug}",
            'collection' => $collection,
        ];
    }
    
    // Deduplicate by slug
    $seen = [];
    $songs = array_values(array_filter($songs, function($s) use (&$seen) {
        if (isset($seen[$s['slug']])) return false;
        $seen[$s['slug']] = true;
        return true;
    }));
    
    $result = ['collection' => $collection, 'count' => count($songs), 'songs' => $songs];
    file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
    exit;
}

// GET SONG: scrape individual song lyrics
if ($action === 'song') {
    $slug = $_GET['slug'] ?? '';
    $collection = $_GET['collection'] ?? 'thanh-ca-httl-viet-nam';
    
    if (!$slug) { echo json_encode(['error' => 'No slug']); exit; }
    
    $cacheFile = $cacheDir . md5($collection . '_' . $slug) . '.json';
    
    // Use cache if available
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }
    
    $url = "http://thanhcatinlanh.com/index.php/{$collection}/{$slug}";
    $html = fetchUrl($url);
    if (!$html) { echo json_encode(['error' => 'Cannot fetch song']); exit; }
    
    $meta    = extractSongMeta($html);
    $lines   = extractLyrics($html);
    $sections = groupIntoSections($lines);
    
    $song = [
        'id'         => $slug,
        'slug'       => $slug,
        'title'      => $meta['title'] ?: $slug,
        'author'     => $meta['author'],
        'number'     => $meta['number'],
        'key'        => $meta['key'],
        'collection' => $collection,
        'source'     => $url,
        'sections'   => $sections,
        'rawLines'   => $lines,
        'fetchedAt'  => date('Y-m-d H:i:s'),
    ];
    
    file_put_contents($cacheFile, json_encode($song, JSON_UNESCAPED_UNICODE));
    echo json_encode($song, JSON_UNESCAPED_UNICODE);
    exit;
}

// BATCH: scrape multiple songs by number range
if ($action === 'batch') {
    $collection = $_GET['collection'] ?? 'thanh-ca-httl-viet-nam';
    $start = max(1, (int)($_GET['start'] ?? 1));
    $end   = min(903, (int)($_GET['end'] ?? 10));
    
    // Load catalog first
    $cacheFile = $cacheDir . "catalog_{$collection}.json";
    if (!file_exists($cacheFile)) {
        echo json_encode(['error' => 'Run catalog action first']); exit;
    }
    
    $catalog = json_decode(file_get_contents($cacheFile), true);
    $songs = array_filter($catalog['songs'], fn($s) => $s['number'] >= $start && $s['number'] <= $end);
    
    $results = [];
    foreach (array_values($songs) as $s) {
        $songCacheFile = $cacheDir . md5($collection . '_' . $s['slug']) . '.json';
        
        if (file_exists($songCacheFile)) {
            $results[] = json_decode(file_get_contents($songCacheFile), true);
        } else {
            // Small delay to be polite
            usleep(500000); // 0.5s
            
            $url = $s['url'];
            $html = fetchUrl($url);
            if (!$html) continue;
            
            $meta = extractSongMeta($html);
            $lines = extractLyrics($html);
            $sections = groupIntoSections($lines);
            
            $song = [
                'id' => $s['slug'], 'slug' => $s['slug'],
                'title' => $meta['title'] ?: $s['title'],
                'author' => $meta['author'],
                'number' => $s['number'],
                'key' => $meta['key'],
                'collection' => $collection,
                'source' => $url,
                'sections' => $sections,
                'rawLines' => $lines,
                'fetchedAt' => date('Y-m-d H:i:s'),
            ];
            
            file_put_contents($songCacheFile, json_encode($song, JSON_UNESCAPED_UNICODE));
            $results[] = $song;
        }
    }
    
    echo json_encode([
        'fetched' => count($results),
        'songs' => $results,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// LIST CACHED SONGS
if ($action === 'list-cached') {
    $collection = $_GET['collection'] ?? '';
    $files = glob($cacheDir . '*.json');
    $songs = [];
    
    foreach ($files as $f) {
        $base = basename($f);
        if (str_starts_with($base, 'catalog_')) continue;
        
        $data = json_decode(file_get_contents($f), true);
        if (!$data || !isset($data['title'])) continue;
        if ($collection && ($data['collection'] ?? '') !== $collection) continue;
        
        $songs[] = [
            'id'         => $data['id'] ?? '',
            'title'      => $data['title'] ?? '',
            'number'     => $data['number'] ?? 0,
            'author'     => $data['author'] ?? '',
            'key'        => $data['key'] ?? '',
            'collection' => $data['collection'] ?? '',
            'sections'   => count($data['sections'] ?? []),
        ];
    }
    
    usort($songs, fn($a, $b) => $a['number'] <=> $b['number']);
    echo json_encode(['count' => count($songs), 'songs' => $songs], JSON_UNESCAPED_UNICODE);
    exit;
}

// COLLECTIONS list
if ($action === 'collections') {
    echo json_encode([
        'collections' => [
            ['id' => 'thanh-ca-httl-viet-nam',   'name' => 'Thánh Ca HTTL Việt Nam', 'total' => 903],
            ['id' => 'thanh-ca-baptist-bac-my',   'name' => 'Thánh Ca Báp-tít Bắc Mỹ', 'total' => 0],
            ['id' => 'thanh-ca-tin-lanh-bac-my',  'name' => 'Thánh Ca Tin Lành Bắc Mỹ', 'total' => 0],
            ['id' => 'ton-vinh-chua-hang-huu',    'name' => 'Tôn Vinh Chúa Hằng Hữu', 'total' => 0],
            ['id' => 'ca-khuc-chuc-ton',           'name' => 'Ca Khúc Chúc Tôn', 'total' => 0],
            ['id' => 'bai-ca-moi',                'name' => 'Bài Ca Mới', 'total' => 0],
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Unknown action: ' . $action, 'actions' => ['catalog', 'song', 'batch', 'list-cached', 'collections']]);
