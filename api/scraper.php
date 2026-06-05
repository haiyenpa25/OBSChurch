<?php
/**
 * OBSChurch — Multi-Source Thánh Ca Scraper
 * 
 * Nguồn 1: https://thanhca.httlvn.org/ — HTTL VN Chính Thức (ưu tiên)
 * Nguồn 2: http://www.thanhcatinlanh.com/ — 9 tuyển tập khác
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$CACHE_DIR = __DIR__ . '/../data/songcache/';
if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);

// ── Nguồn dữ liệu ────────────────────────────────────────────────
const SOURCES = [
    'httlvn' => [
        'label'    => 'Thánh Ca HTTL VN (Chính Thức)',
        'base_url' => 'https://thanhca.httlvn.org',
        'list_url' => 'https://thanhca.httlvn.org/thanh-ca',
        'total'    => 553,
        'has_categories' => true,
    ],
    'thanhcatinlanh' => [
        'label'    => 'ThanhCaTinLanh.com',
        'base_url' => 'http://www.thanhcatinlanh.com',
        'total'    => 0,
        'has_categories' => false,
    ],
];

// Các tuyển tập từ thanhcatinlanh.com
const COLLECTIONS_TCL = [
    'thanh-ca-httl-viet-nam'    => ['label' => 'Thánh Ca HTTL VN',      'total' => 553],
    'thanh-ca-baptist-bac-my'   => ['label' => 'Thánh Ca Báp-tít BM',   'total' => 0],
    'thanh-ca-tin-lanh-bac-my'  => ['label' => 'Thánh Ca Tin Lành BM',  'total' => 0],
    'ton-vinh-chua-hang-huu'    => ['label' => 'Tôn Vinh Chúa Hằng Hữu','total' => 0],
    'ca-khuc-chuc-ton'           => ['label' => 'Ca Khúc Chúc Tôn',     'total' => 0],
    'bai-ca-moi'                => ['label' => 'Bài Ca Mới',             'total' => 0],
    'nhac-si-david-dong-psalm-music' => ['label' => 'NS David Dong',    'total' => 0],
    'nhac-si-le-anh-dong'       => ['label' => 'NS Lê Anh Đông',        'total' => 0],
    'nhung-ban-hoa-am-moi'      => ['label' => 'Những Bản Hòa Âm',      'total' => 0],
];

// Phân loại chính thức HTTLVN (từ homepage)
const CATEGORIES_HTTLVN = [
    'THỜ PHƯỢNG'          => 'Thờ Phượng',
    'ĐỨC CHÚA TRỜI'       => 'Đức Chúa Trời',
    'CHÚA JÊSUS CHRIST'   => 'Chúa Jêsus Christ',
    'ĐỨC THÁNH LINH'      => 'Đức Thánh Linh',
    'HỘI THÁNH'           => 'Hội Thánh',
    'KINH THÁNH'          => 'Kinh Thánh',
    'TIN LÀNH'            => 'Tin Lành',
    'ĐỜI TÍN ĐỒ'          => 'Đời Tín Đồ',
    'CÕI LAI SANH'        => 'Cõi Lai Sanh',
    'TRỌNG TRÁCH HỘI THÁNH' => 'Trọng Trách Hội Thánh',
    'THIẾU NHI'           => 'Thiếu Nhi',
    'THANH NIÊN'          => 'Thanh Niên',
    'ĐƠN CA - SONG CA'    => 'Đơn Ca / Song Ca',
    'LỄ NGHI'             => 'Lễ Nghi',
    'BIỆT LỄ CA'          => 'Biệt Lễ Ca',
    'VIỆT NAM CA'         => 'Việt Nam Ca',
    'HỢP CA'              => 'Hợp Ca',
    'KINH TIẾT CA'        => 'Kinh Tiết Ca',
    'ĐOẢN CA'             => 'Đoản Ca',
    'BÀI THEO THƠ THÁNH CŨ' => 'Bài Theo Thơ Thánh Cũ',
];

// ── HTTP helper ──────────────────────────────────────────────────
function fetch($url, $timeout = 20) {
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header'  => implode("\r\n", [
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36",
            "Accept: text/html,application/xhtml+xml,*/*",
            "Accept-Language: vi,en;q=0.9",
        ]),
        'ignore_errors' => true,
    ]]);
    return @file_get_contents($url, false, $ctx) ?: '';
}

function cache($key, $data = null) {
    global $CACHE_DIR;
    $file = $CACHE_DIR . md5($key) . '.json';
    if ($data === null) {
        return file_exists($file) ? json_decode(file_get_contents($file), true) : null;
    }
    file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    return $data;
}

function cached($key, $maxAge = 86400) {
    global $CACHE_DIR;
    $file = $CACHE_DIR . md5($key) . '.json';
    return file_exists($file) && (time() - filemtime($file)) < $maxAge;
}

// ── ROUTER ───────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'sources';

// ═══ SOURCES: Danh sách nguồn/tuyển tập ═════════════════════════
if ($action === 'sources') {
    $result = [
        [
            'id'    => 'httlvn',
            'label' => 'Thánh Ca HTTL VN (Chính Thức)',
            'source'=> 'httlvn.org',
            'total' => 553,
            'has_categories' => true,
            'color' => '#4edea3',
            'icon'  => '🏛️',
            'collections' => ['httlvn'],
        ],
        [
            'id'    => 'thanhcatinlanh',
            'label' => 'ThanhCaTinLanh.com — 9 Tuyển Tập',
            'source'=> 'thanhcatinlanh.com',
            'total' => 0,
            'has_categories' => false,
            'color' => '#4d8eff',
            'icon'  => '📚',
            'collections' => array_keys(COLLECTIONS_TCL),
        ],
    ];
    echo json_encode(['ok' => true, 'sources' => $result, 'collections' => COLLECTIONS_TCL], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══ CATALOG: Lấy danh sách bài theo nguồn/tuyển tập ════════════
if ($action === 'catalog') {
    $source     = $_GET['source'] ?? 'httlvn';
    $collection = $_GET['collection'] ?? '';
    $page       = max(1, intval($_GET['page'] ?? 1));
    $cat        = urldecode($_GET['cat'] ?? '');
    
    if ($source === 'httlvn') {
        echo json_encode(catalogHTTLVN($page, $cat), JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(catalogTCL($collection, $page), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ═══ SONG: Lấy lời bài hát ══════════════════════════════════════
if ($action === 'song') {
    $source = $_GET['source'] ?? 'httlvn';
    $slug   = $_GET['slug'] ?? '';
    $id     = intval($_GET['id'] ?? 0);
    
    if (!$slug && !$id) { echo json_encode(['error' => 'Missing slug or id']); exit; }
    
    if ($source === 'httlvn') {
        echo json_encode(songHTTLVN($id, $slug), JSON_UNESCAPED_UNICODE);
    } else {
        $collection = $_GET['collection'] ?? 'thanh-ca-httl-viet-nam';
        echo json_encode(songTCL($slug, $collection), JSON_UNESCAPED_UNICODE);
    }
    exit;
}

// ═══ CATEGORIES: Phân loại HTTLVN ════════════════════════════════
if ($action === 'categories') {
    $cats = [];
    foreach (CATEGORIES_HTTLVN as $key => $label) {
        $cats[] = ['id' => $key, 'label' => $label];
    }
    echo json_encode(['categories' => $cats], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══ LIST-CACHED ═════════════════════════════════════════════════
if ($action === 'list-cached') {
    global $CACHE_DIR;
    $source     = $_GET['source'] ?? '';
    $collection = $_GET['collection'] ?? '';
    
    $files = glob($CACHE_DIR . '*.json');
    $songs = [];
    foreach ($files as $f) {
        $base = basename($f);
        if (strpos($base, 'catalog_') === 0) continue;
        $d = json_decode(file_get_contents($f), true);
        if (!$d || !isset($d['title'])) continue;
        if ($source && ($d['source_id'] ?? '') !== $source) continue;
        if ($collection && ($d['collection'] ?? '') !== $collection) continue;
        $songs[] = [
            'id' => $d['id'] ?? '', 'slug' => $d['slug'] ?? '',
            'number' => $d['number'] ?? 0, 'title' => $d['title'] ?? '',
            'author' => $d['author'] ?? '', 'key' => $d['key'] ?? '',
            'collection' => $d['collection'] ?? '', 'source_id' => $d['source_id'] ?? '',
            'cat_top' => $d['cat_top'] ?? '', 'sections' => count($d['sections'] ?? []),
        ];
    }
    usort($songs, fn($a,$b) => ($a['number']??9999) - ($b['number']??9999));
    echo json_encode(['count' => count($songs), 'songs' => $songs], JSON_UNESCAPED_UNICODE);
    exit;
}

// ═══════════════════════════════════════════════════════════════════
// HTTLVN SCRAPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════

function catalogHTTLVN($page = 1, $cat = '') {
    $cacheKey = "httlvn_catalog_p{$page}_" . md5($cat);
    if (cached($cacheKey, 43200)) return cache($cacheKey);
    
    $url = 'https://thanhca.httlvn.org/thanh-ca';
    $params = ['page' => $page];
    if ($cat) $params['cat_top'] = $cat;
    if ($page > 1) $url .= '?' . http_build_query($params);
    elseif ($cat) $url .= '?' . http_build_query(['cat_top' => $cat]);
    
    $html = fetch($url);
    if (!$html) return ['error' => 'Cannot fetch HTTLVN catalog', 'songs' => []];
    
    // Parse hymn items: <div class="hymn-item mb-1 tc-{id}">
    $songs = [];
    preg_match_all(
        '/<div class="hymn-item[^"]*tc-(\d+)[^"]*">\s*<a href="([^"]+)"[^>]*>\s*([\d]+)\s*<\/a>\s*<div[^>]*>\s*<h3[^>]*><a href="([^"]+)">(.*?)<\/a><\/h3>/si',
        $html, $matches, PREG_SET_ORDER
    );
    
    foreach ($matches as $m) {
        $id   = intval($m[1]);
        $href = $m[2];
        $num  = intval($m[3]);
        $title = html_entity_decode(strip_tags($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $slug = ltrim(parse_url($href, PHP_URL_PATH), '/');
        
        // Get cat from context
        $songs[] = [
            'id'         => "httlvn-{$id}",
            'slug'       => $slug,
            'number'     => $num ?: $id,
            'title'      => trim($title),
            'source_id'  => 'httlvn',
            'collection' => 'httl-vn',
            'url'        => 'https://thanhca.httlvn.org' . $href,
        ];
    }
    
    // Check pagination
    $hasNext = preg_match('/href="[^"]*page=' . ($page+1) . '[^"]*"/', $html);
    
    $result = [
        'source'  => 'httlvn',
        'page'    => $page,
        'hasNext' => (bool)$hasNext,
        'count'   => count($songs),
        'songs'   => $songs,
    ];
    return cache($cacheKey, $result);
}

function songHTTLVN($id = 0, $slug = '') {
    $cacheKey = "httlvn_song_{$id}_{$slug}";
    if (cached($cacheKey, PHP_INT_MAX)) return cache($cacheKey);
    
    // Build URL: /thanh-ca-{id}/{slug}
    if ($slug && strpos($slug, 'thanh-ca-') === 0) {
        $url = 'https://thanhca.httlvn.org/' . $slug;
    } elseif ($id) {
        // Need to find slug — try from catalog first
        // Or use redirect: httlvn redirects /Home/Go?id=N
        $redirectUrl = "https://thanhca.httlvn.org/Home/Go?id={$id}&type_book=thanh-ca";
        $html2 = fetch($redirectUrl);
        // Extract current URL from meta refresh or location
        if (preg_match('/href="(\/thanh-ca-\d+\/[^"]+)"/', $html2, $m)) {
            $url = 'https://thanhca.httlvn.org' . $m[1];
            $slug = ltrim($m[1], '/');
        } else {
            return ['error' => "Cannot resolve id $id"];
        }
    } else {
        return ['error' => 'Need id or slug'];
    }
    
    $html = fetch($url);
    if (!$html) return ['error' => 'Cannot fetch song', 'url' => $url];
    
    return parseHTTLVNSong($html, $url, $id, $slug);
}

function parseHTTLVNSong($html, $url, $id = 0, $slug = '') {
    // Extract song number from title or URL
    preg_match('/thanh-ca-(\d+)\//', $url, $nm);
    $num = $id ?: (isset($nm[1]) ? intval($nm[1]) : 0);
    
    // Extract slug
    if (!$slug && preg_match('/\/thanh-ca-\d+\/(.+?)(?:\?|$)/', $url, $sm)) {
        $slug = 'thanh-ca-' . $num . '/' . $sm[1];
    }
    
    // Title from <title> tag
    $title = '';
    if (preg_match('/<title>Thánh Ca \d+:\s*([^-]+)/u', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    } elseif (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $html, $m)) {
        $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    
    // Categories from breadcrumb
    $catTop = ''; $catSub = '';
    if (preg_match_all('/cat_top=([^"&]+)"[^>]*>([^<]+)</u', $html, $cats)) {
        $catTop = html_entity_decode(urldecode($cats[1][0] ?? ''), ENT_QUOTES, 'UTF-8');
        $catSub = html_entity_decode(strip_tags($cats[2][1] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    
    // Extract lyrics
    // HTTLVN uses <pre> or div.lyric or similar
    $lines = [];
    
    // Try pre.lyric
    if (preg_match('/<pre[^>]*class="[^"]*lyric[^"]*"[^>]*>(.*?)<\/pre>/si', $html, $m)) {
        $raw = $m[1];
    }
    // Try div with lyric class  
    elseif (preg_match('/<div[^>]*class="[^"]*lyric[^"]*"[^>]*>(.*?)<\/div>\s*<div/si', $html, $m)) {
        $raw = $m[1];
    }
    // Try main content area
    elseif (preg_match('/<div class="container-fluid">(.*?)<div class="row row-control/si', $html, $m)) {
        $raw = $m[1];
    }
    // Fallback: get main text block
    else {
        if (preg_match('/<div class="tab-content">(.*?)<\/div>\s*<\/div>\s*<\/div>/si', $html, $m)) {
            $raw = $m[1];
        } else {
            $raw = $html;
        }
    }
    
    // Clean and extract lines
    $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw ?? '');
    $raw = preg_replace('/<\/p>/i', "\n", $raw);
    $raw = strip_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $raw)),
        fn($l) => mb_strlen($l) > 1
    ));
    
    // Group into sections
    $sections = groupSections($lines);
    
    $song = [
        'id'         => "httlvn-{$num}",
        'slug'       => $slug,
        'number'     => $num,
        'title'      => $title ?: "Bài {$num}",
        'author'     => '',
        'key'        => '',
        'cat_top'    => $catTop,
        'cat_sub'    => $catSub,
        'source_id'  => 'httlvn',
        'source_label' => 'Thánh Ca HTTLVN.org',
        'collection' => 'httl-vn',
        'source'     => $url,
        'sections'   => $sections,
        'rawLines'   => $lines,
        'fetchedAt'  => date('Y-m-d H:i:s'),
    ];
    
    $cacheKey = "httlvn_song_{$num}_{$slug}";
    cache($cacheKey, $song);
    return $song;
}

// ═══════════════════════════════════════════════════════════════════
// THANHCATINLANH.COM SCRAPER FUNCTIONS
// ═══════════════════════════════════════════════════════════════════

function catalogTCL($collection, $page = 1) {
    $cacheKey = "tcl_catalog_{$collection}_p{$page}";
    if (cached($cacheKey, 86400)) return cache($cacheKey);
    
    $url = "http://www.thanhcatinlanh.com/index.php/{$collection}";
    if ($page > 1) $url .= "?start=" . (($page - 1) * 20);
    
    $html = fetch($url);
    if (!$html) return ['error' => 'Cannot fetch TCL catalog', 'songs' => []];
    
    // Extract song links
    preg_match_all(
        '/<a\s+href="\/index\.php\/' . preg_quote($collection, '/') . '\/(\d+-[^"]+)"[^>]*>([^<]+)</si',
        $html, $matches, PREG_SET_ORDER
    );
    
    $songs = []; $seen = [];
    foreach ($matches as $m) {
        $slug  = trim($m[1]);
        $title = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (strlen($title) < 2 || isset($seen[$slug])) continue;
        $seen[$slug] = true;
        
        preg_match('/^(\d+)-/', $slug, $nm);
        $num = isset($nm[1]) ? intval($nm[1]) : 0;
        
        $songs[] = [
            'id'         => "tcl-{$collection}-{$slug}",
            'slug'       => $slug,
            'number'     => $num,
            'title'      => $title,
            'source_id'  => 'thanhcatinlanh',
            'collection' => $collection,
            'url'        => "http://www.thanhcatinlanh.com/index.php/{$collection}/{$slug}",
        ];
    }
    
    usort($songs, fn($a,$b) => $a['number'] - $b['number']);
    $result = ['source' => 'thanhcatinlanh', 'collection' => $collection, 'page' => $page, 'count' => count($songs), 'songs' => $songs];
    return cache($cacheKey, $result);
}

function songTCL($slug, $collection) {
    $cacheKey = "tcl_song_" . md5($collection . '_' . $slug);
    if (cached($cacheKey, PHP_INT_MAX)) return cache($cacheKey);
    
    $url  = "http://www.thanhcatinlanh.com/index.php/{$collection}/{$slug}";
    $html = fetch($url);
    if (!$html) return ['error' => 'Cannot fetch song'];
    
    // Title
    $title = '';
    if (preg_match('/<h1 itemprop="name">([^<]+)/si', $html, $m)) $title = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Author
    $author = '';
    if (preg_match('/itemprop="name">([^<]+)<\/span>/si', $html, $m)) $author = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Number
    $num = 0;
    preg_match('/^(\d+)-/', $slug, $nm);
    if (isset($nm[1])) $num = intval($nm[1]);
    
    // Key
    $key = '';
    if (preg_match('/Hợp âm.*?\(([A-Gb#m]+)\)/si', $html, $m)) $key = trim($m[1]);
    
    // Lyrics — Joomla content
    $lines = extractLyricsTCL($html);
    $sections = groupSections($lines);
    
    $song = [
        'id'         => "tcl-{$collection}-{$slug}",
        'slug'       => $slug,
        'number'     => $num,
        'title'      => $title ?: $slug,
        'author'     => $author,
        'key'        => $key,
        'cat_top'    => '',
        'cat_sub'    => '',
        'source_id'  => 'thanhcatinlanh',
        'source_label' => 'ThanhCaTinLanh.com',
        'collection' => $collection,
        'source'     => $url,
        'sections'   => $sections,
        'rawLines'   => $lines,
        'fetchedAt'  => date('Y-m-d H:i:s'),
    ];
    
    cache($cacheKey, $song);
    return $song;
}

function extractLyricsTCL($html) {
    // Try tab "Lời Bài Hát"
    if (preg_match('/Lời Bài Hát.*?<\/h2>(.*?)<\/div>\s*<div class="jwts_tabbertab"/si', $html, $m)) {
        $raw = $m[1];
    } elseif (preg_match('/<div class="description"[^>]*>(.*?)(?:<div class="clr|<div class="jwts_tabber)/si', $html, $m)) {
        $raw = $m[1];
    } elseif (preg_match('/<div class="item-page"[^>]*>(.*?)<div class="clr/si', $html, $m)) {
        $raw = $m[1];
    } else {
        // Generic article content
        if (preg_match('/<div class="article-content"[^>]*>(.*?)<\/div>/si', $html, $m)) {
            $raw = $m[1];
        } else {
            return [];
        }
    }
    return cleanToLines($raw);
}

function cleanToLines($raw) {
    $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
    $raw = preg_replace('/<\/p>/i', "\n", $raw);
    $raw = strip_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_values(array_filter(
        array_map('trim', explode("\n", $raw)),
        fn($l) => mb_strlen(trim($l)) > 1
    ));
    return $lines;
}

function groupSections($lines) {
    $sections = []; $cur = []; $idx = 1;
    foreach ($lines as $line) {
        if (preg_match('/^(\d+[\.\)]|ĐK\s*:|Điệp khúc|Bridge|Chorus|Cầu nối)/ui', $line)) {
            if (!empty($cur)) { $sections[] = ['label' => labelFor($idx++), 'lines' => $cur]; $cur = []; }
        } else {
            $cur[] = $line;
            if (count($cur) >= 4) { $sections[] = ['label' => labelFor($idx++), 'lines' => $cur]; $cur = []; }
        }
    }
    if (!empty($cur)) $sections[] = ['label' => labelFor($idx), 'lines' => $cur];
    return $sections ?: [['label' => 'Câu 1', 'lines' => $lines]];
}

function labelFor($idx) {
    return $idx === 1 ? 'Câu 1' : ($idx === 2 ? 'Điệp Khúc' : "Câu $idx");
}

echo json_encode(['error' => 'Unknown action: ' . htmlspecialchars($_GET['action'] ?? ''),
    'actions' => ['sources', 'catalog', 'song', 'categories', 'list-cached']]);
