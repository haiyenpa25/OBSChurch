<?php
/**
 * HTTLVN Scraper — Tải 903 bài Thánh Ca từ thanhca.httlvn.org
 * 
 * URL danh sách: /thanh-ca?page=N (50 bài/trang, tổng ~19 trang = 903 bài)
 * URL bài hát:   /thanh-ca-{số}/{slug}
 * Lời nằm trong: <div id="lyric-content"> ... </div>
 * 
 * Actions:
 *   ?action=catalog&page=N    — Lấy danh sách 50 bài trang N
 *   ?action=catalog&all=1     — Lấy TẤT CẢ bài (crawl hết các trang)
 *   ?action=song&id=N         — Lấy lời bài số N
 *   ?action=song&slug=...     — Lấy lời theo slug
 *   ?action=pages             — Kiểm tra tổng số trang
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$CACHE_DIR = __DIR__ . '/../data/httlvn-cache/';
if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);

$DB_FILE = __DIR__ . '/../data/thanh-ca.db';

function httlvnFetch($url, $timeout = 20) {
    static $lastFetch = 0;
    $delay = 400000; // 0.4s polite delay
    $since = microtime(true) * 1e6 - $lastFetch;
    if ($since < $delay) usleep((int)($delay - $since));
    
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header'  => implode("\r\n", [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0",
            "Accept: text/html,application/xhtml+xml,*/*",
            "Accept-Language: vi-VN,vi;q=0.9,en;q=0.8",
            "Accept-Charset: utf-8",
            "Referer: https://thanhca.httlvn.org/thanh-ca",
        ]),
        'ignore_errors' => true,
        'follow_location' => 1,
        'max_redirects'   => 5,
    ]]);
    $lastFetch = microtime(true) * 1e6;
    $raw = @file_get_contents($url, false, $ctx) ?: '';
    
    // Ensure UTF-8 encoding
    if ($raw && !mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'auto');
    }
    // Fix HTML entities that declare charset
    $raw = preg_replace('/charset=windows-\d+|charset=iso-\d+-\d+/i', 'charset=utf-8', $raw);
    
    return $raw;
}

// ── Extract lyrics from song HTML ─────────────────────────────────
// Rule: UTF-8. Lời nằm trong <div id="lyric-content">.
// Dùng cách cắt theo vị trí thay vì regex closing (tránh lỗi nested div)
function extractHTTLVNLyrics($html) {
    // Tìm vị trí bắt đầu của lyric-content
    $markerPos = strpos($html, 'id="lyric-content"');
    if ($markerPos === false) return [];
    
    // Nhảy qua dấu >
    $startPos = strpos($html, '>', $markerPos);
    if ($startPos === false) return [];
    $startPos++;
    
    // Lấy ~6000 ký tự từ đó (đủ cho bài dài nhất)
    $chunk = substr($html, $startPos, 6000);
    
    // Convert tags thành newlines
    $chunk = preg_replace('#</p>\s*<p[^>]*>#si', "\n", $chunk);
    $chunk = preg_replace('#<p[^>]*>#si', '', $chunk);
    $chunk = preg_replace('#</p>#si', "\n", $chunk);
    $chunk = preg_replace('#<br\s*/?>#si', "\n", $chunk);
    
    // Strip tất cả tags còn lại
    $text = strip_tags($chunk);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    $lines   = [];
    $junkSeen = false;
    
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if (!$line) continue;
        
        // Dừng khi gặp nav/UI junk
        if (preg_match('/^(Thánh ca\s*$|KTĐ|Kinh Thánh Đối Đáp|Mới truy cập|Cỡ chữ|#\d{3}\.|Fullscreen)/ui', $line)) break;
        
        // Bỏ navigation arrows: ← 001  003 →
        if (preg_match('/^[←→\s\d]+$/', $line)) continue;
        
        // Bỏ dòng quá ngắn (1 ký tự)
        if (mb_strlen($line, 'UTF-8') < 2) continue;
        
        $lines[] = $line;
    }
    
    return $lines;
}

// ── Parse sections (Câu 1, Câu 2...) ──────────────────────────────
function parseHTTLVNSections($lines) {
    $sections = [];
    $currentLabel = null;
    $currentLines = [];
    
    foreach ($lines as $line) {
        // Detect section headers: "Câu 1", "Câu 2", "Điệp Khúc", "ĐK:"
        if (preg_match('/^(Câu\s*\d+|C[àa]u\s*\d+|Điệp\s*[Kk]húc|ĐK\s*:|Bridge|Chorus|Cầu\s*nối|Verse\s*\d+)/ui', $line, $m)) {
            if ($currentLabel !== null && !empty($currentLines)) {
                $sections[] = ['label' => $currentLabel, 'lines' => $currentLines];
            }
            $currentLabel = trim($line);
            $currentLines = [];
        } else {
            // Skip if line only has a bold marker (from <b> tags)
            $currentLines[] = $line;
        }
    }
    
    if (!empty($currentLines)) {
        $sections[] = ['label' => $currentLabel ?? 'Câu 1', 'lines' => $currentLines];
    }
    
    // If no sections found, put all lines in one section
    if (empty($sections)) {
        return [['label' => 'Câu 1', 'lines' => $lines]];
    }
    
    return $sections;
}

// ── Parse song meta from HTML ──────────────────────────────────────
function parseHTTLVNMeta($html, $id = 0, $slug = '') {
    $num = $id;
    if (!$num && preg_match('#/thanh-ca-(\d+)/#', $slug, $m)) $num = intval($m[1]);
    
    // Title from <title> tag: "Thánh Ca 1: Cúi Xin Vua Thánh Ngự Lai - ..."
    $title = '';
    if (preg_match('#<title>([^<]+)</title>#i', $html, $m)) {
        $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Format: "Thánh Ca N: TITLE - Site Name"
        if (preg_match('/Thánh Ca \d+:\s*(.+?)(?:\s*-\s*Thánh Ca Tin Lành|$)/ui', $raw, $tm)) {
            $title = trim($tm[1]);
        } else {
            $title = trim(explode('-', $raw)[0]);
        }
    }
    
    // Song number from URL
    if (!$num && preg_match('#thanh-ca-(\d+)#', $html, $m)) $num = intval($m[1]);
    
    // Category from breadcrumb
    $catTop = ''; $catSub = '';
    if (preg_match_all('#cat_top=([^"&]+)"[^>]*>([^<]+)<#u', $html, $cats)) {
        $catTop = html_entity_decode(urldecode($cats[1][0] ?? ''), ENT_QUOTES, 'UTF-8');
        $catTop = mb_convert_case($catTop, MB_CASE_TITLE, 'UTF-8');
    }
    if (preg_match_all('#cat_sub=([^"&]+)"[^>]*>([^<]+)<#u', $html, $csubs)) {
        $catSub = html_entity_decode(urldecode($csubs[2][0] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    
    return ['num' => $num, 'title' => $title, 'cat_top' => $catTop, 'cat_sub' => $catSub];
}

// ── Get DB connection ──────────────────────────────────────────────
function getDB() {
    global $DB_FILE;
    if (!is_dir(dirname($DB_FILE))) mkdir(dirname($DB_FILE), 0755, true);
    $db = new PDO("sqlite:$DB_FILE");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
    $db->exec("CREATE TABLE IF NOT EXISTS songs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL, number INTEGER DEFAULT 0,
        title TEXT NOT NULL, author TEXT DEFAULT '', key_sig TEXT DEFAULT '',
        cat_top TEXT DEFAULT '', cat_sub TEXT DEFAULT '',
        collection TEXT DEFAULT '', source_id TEXT DEFAULT '',
        source_url TEXT DEFAULT '', lyrics_raw TEXT DEFAULT '',
        lyrics_json TEXT DEFAULT '[]', sections_json TEXT DEFAULT '[]',
        saved_at DATETIME DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    return $db;
}

function saveToDb($db, $song) {
    $raw  = implode("\n", $song['rawLines'] ?? []);
    $json = json_encode($song['rawLines'] ?? [], JSON_UNESCAPED_UNICODE);
    $secs = json_encode($song['sections'] ?? [], JSON_UNESCAPED_UNICODE);
    $stmt = $db->prepare("
        INSERT INTO songs (slug,number,title,author,key_sig,cat_top,cat_sub,collection,source_id,source_url,lyrics_raw,lyrics_json,sections_json,updated_at)
        VALUES (:slug,:num,:title,'','', :cat_top,:cat_sub,'httl-vn','httlvn',:src,:raw,:json,:secs,CURRENT_TIMESTAMP)
        ON CONFLICT(slug) DO UPDATE SET
            title=excluded.title, number=excluded.number,
            cat_top=excluded.cat_top, cat_sub=excluded.cat_sub,
            lyrics_raw=excluded.lyrics_raw, lyrics_json=excluded.lyrics_json,
            sections_json=excluded.sections_json, updated_at=CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':slug' => $song['slug'], ':num' => $song['num'],
        ':title' => $song['title'], ':cat_top' => $song['cat_top'],
        ':cat_sub' => $song['cat_sub'], ':src' => $song['url'],
        ':raw' => $raw, ':json' => $json, ':secs' => $secs,
    ]);
}

// ═══════════════════════════════════════════════════
// ROUTER
// ═══════════════════════════════════════════════════
$action = $_GET['action'] ?? 'song';

// ── ACTION: pages ─────────────────────────────────────────────────
if ($action === 'pages') {
    $html = httlvnFetch('https://thanhca.httlvn.org/thanh-ca');
    preg_match_all('/href="\/thanh-ca\?page=(\d+)"/', $html, $m);
    $pages = array_map('intval', array_unique($m[1]));
    sort($pages);
    $maxPage = max($pages ?: [1]);
    
    // Count songs on page 1
    preg_match_all('/class="hymn-item mb-1 tc-\d+"/', $html, $sm);
    $perPage = count($sm[0]);
    
    echo json_encode(['pages' => $maxPage, 'per_page' => $perPage, 'est_total' => $maxPage * $perPage]);
    exit;
}

// ── ACTION: catalog ───────────────────────────────────────────────
if ($action === 'catalog') {
    $page = intval($_GET['page'] ?? 1);
    $cat  = urldecode($_GET['cat'] ?? '');
    $all  = ($_GET['all'] ?? '') === '1';
    
    if ($all) {
        // Crawl ALL pages and collect all songs
        $allSongs = [];
        $page = 1;
        do {
            $url  = "https://thanhca.httlvn.org/thanh-ca?page={$page}";
            if ($cat) $url .= "&cat_top=" . urlencode($cat);
            
            $cacheFile = $CACHE_DIR . "catalog_p{$page}_" . md5($cat) . ".json";
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $html = httlvnFetch($url);
                $data = parseCatalogPage($html, $page);
                file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
            }
            
            $allSongs = array_merge($allSongs, $data['songs'] ?? []);
            $hasNext  = $data['hasNext'] ?? false;
            $page++;
        } while ($hasNext && $page <= 25);
        
        echo json_encode(['total' => count($allSongs), 'songs' => $allSongs], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Single page
    $url = "https://thanhca.httlvn.org/thanh-ca?page={$page}";
    if ($cat) $url .= "&cat_top=" . urlencode($cat);
    
    $cacheFile = $CACHE_DIR . "catalog_p{$page}_" . md5($cat) . ".json";
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
        echo file_get_contents($cacheFile);
    } else {
        $html = httlvnFetch($url);
        $data = parseCatalogPage($html, $page);
        file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    exit;
}

function parseCatalogPage($html, $page) {
    // Pattern: <div class="hymn-item mb-1 tc-{id}">
    //          <a href="/thanh-ca-{id}/{slug}"> NNN </a>
    //          <h3><a href="/thanh-ca-{id}/{slug}">TITLE</a></h3>
    //          cat_top, cat_sub links
    preg_match_all('#<div class="hymn-item mb-1 tc-(\d+)">\s*<a href="(/thanh-ca-\d+/[^"]+)"[^>]*>\s*(\d+)\s*</a>\s*<div[^>]*>\s*<h3[^>]*><a href="([^"]+)">([^<]+)</a></h3>[\s\S]{0,400}?cat_top=([^"&]+)"[^>]*>([^<]+)<[\s\S]{0,200}?(?:cat_sub=([^"&]+)"[^>]*>([^<]+)<)?#u', $html, $ms, PREG_SET_ORDER);
    
    $songs = [];
    foreach ($ms as $m) {
        $id    = intval($m[1]);
        $path  = $m[2];
        $num   = intval($m[3]);
        $title = html_entity_decode(trim($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $catTop= html_entity_decode(urldecode(trim($m[6] ?? '')), ENT_QUOTES, 'UTF-8');
        $catTopLabel = html_entity_decode(trim($m[7] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $catSub= html_entity_decode(trim($m[9] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $songs[] = [
            'id'      => $id,
            'num'     => $num ?: $id,
            'slug'    => ltrim($path, '/'),
            'title'   => $title,
            'cat_top' => $catTopLabel ?: $catTop,
            'cat_sub' => $catSub,
            'url'     => 'https://thanhca.httlvn.org' . $path,
        ];
    }
    
    // Simpler fallback if above regex is too complex
    if (empty($songs)) {
        preg_match_all('#tc-(\d+).*?href="(/thanh-ca-(\d+)/([^"]+))"[^>]*>\s*\d+\s*</a>.*?<a href="[^"]+">([^<]+)</a>#si', $html, $ms2, PREG_SET_ORDER);
        foreach ($ms2 as $m) {
            $songs[] = [
                'id' => intval($m[1]), 'num' => intval($m[3]),
                'slug' => ltrim($m[2], '/'), 'title' => html_entity_decode(trim($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'cat_top' => '', 'cat_sub' => '',
                'url' => 'https://thanhca.httlvn.org' . $m[2],
            ];
        }
    }
    
    // Check if next page exists
    $hasNext = strpos($html, 'page=' . ($page + 1)) !== false;
    
    return ['page' => $page, 'count' => count($songs), 'hasNext' => $hasNext, 'songs' => $songs];
}

// ── ACTION: song ──────────────────────────────────────────────────
if ($action === 'song') {
    $id   = intval($_GET['id'] ?? 0);
    $slug = trim($_GET['slug'] ?? '');
    $save = ($_GET['save'] ?? '') === '1';
    
    // Determine URL
    if ($slug && str_contains($slug, 'thanh-ca-')) {
        $url = 'https://thanhca.httlvn.org/' . ltrim($slug, '/');
    } elseif ($id) {
        // Use redirect to get the slug
        $redirHtml = httlvnFetch("https://thanhca.httlvn.org/Home/Go?id={$id}&type_book=thanh-ca");
        if (preg_match('#(/thanh-ca-\d+/[^"\'?\s]+)#i', $redirHtml, $rm)) {
            $slug = ltrim($rm[1], '/');
            $url  = 'https://thanhca.httlvn.org' . $rm[1];
        } else {
            echo json_encode(['error' => "Cannot resolve song id={$id}"]); exit;
        }
    } else {
        echo json_encode(['error' => 'Need id or slug']); exit;
    }
    
    // Check cache
    $cacheKey  = $slug ?: "id_{$id}";
    $cacheFile = $CACHE_DIR . md5($cacheKey) . '.json';
    
    if (file_exists($cacheFile)) {
        echo file_get_contents($cacheFile);
        exit;
    }
    
    // Fetch song page
    $html = httlvnFetch($url);
    if (!$html) { echo json_encode(['error' => "Cannot fetch $url"]); exit; }
    
    // Extract data
    $meta     = parseHTTLVNMeta($html, $id, $slug);
    $rawLines = extractHTTLVNLyrics($html);
    $sections = parseHTTLVNSections($rawLines);
    
    $song = [
        'slug'      => $slug,
        'url'       => $url,
        'num'       => $meta['num'] ?: $id,
        'title'     => $meta['title'],
        'cat_top'   => $meta['cat_top'],
        'cat_sub'   => $meta['cat_sub'],
        'rawLines'  => $rawLines,
        'sections'  => $sections,
        'source'    => 'httlvn',
        'fetchedAt' => date('Y-m-d H:i:s'),
    ];
    
    // Cache
    file_put_contents($cacheFile, json_encode($song, JSON_UNESCAPED_UNICODE));
    
    // Optionally save to DB
    if ($save) {
        $db = getDB();
        saveToDb($db, $song);
    }
    
    echo json_encode($song, JSON_UNESCAPED_UNICODE);
    exit;
}

echo json_encode(['error' => 'Unknown action', 'actions' => ['pages', 'catalog', 'song']]);
