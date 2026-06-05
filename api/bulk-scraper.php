<?php
/**
 * Bulk Scraper — Tải toàn bộ từ cả 2 nguồn, lưu vào SQLite
 * Chạy nền, progress tracking qua JSON file
 */

ignore_user_abort(true);
set_time_limit(7200); // 2 tiếng
ini_set('max_execution_time', 7200);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$PROGRESS_FILE = __DIR__ . '/../data/bulk-progress.json';
$DB_FILE       = __DIR__ . '/../data/thanh-ca.db';
$CACHE_DIR     = __DIR__ . '/../data/songcache/';
if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);

// ── Sources plan ─────────────────────────────────────────────────
// Source 1: httlvn.org — 553 bài chính thức (tải từng bài by number)
// Source 2: thanhcatinlanh.com — 9 tuyển tập
const HTTLVN_TOTAL = 553;
const TCL_COLLECTIONS = [
    'thanh-ca-httl-viet-nam', 'thanh-ca-baptist-bac-my',
    'thanh-ca-tin-lanh-bac-my', 'ton-vinh-chua-hang-huu',
    'ca-khuc-chuc-ton', 'bai-ca-moi',
    'nhac-si-david-dong-psalm-music', 'nhac-si-le-anh-dong',
    'nhung-ban-hoa-am-moi',
];

function http($url, $t = 20) {
    $ctx = stream_context_create(['http' => [
        'timeout' => $t,
        'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\n",
        'ignore_errors' => true,
    ]]);
    return @file_get_contents($url, false, $ctx) ?: '';
}

function saveProgress($d) {
    global $PROGRESS_FILE;
    file_put_contents($PROGRESS_FILE, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

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
        saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_slug ON songs(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_source ON songs(source_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collection ON songs(collection)");
    return $db;
}

function saveSong($db, $song) {
    $lyricsRaw   = implode("\n", $song['rawLines'] ?? []);
    $lyricsJson  = json_encode($song['rawLines'] ?? [], JSON_UNESCAPED_UNICODE);
    $sectionsJson= json_encode($song['sections'] ?? [], JSON_UNESCAPED_UNICODE);
    $slug = $song['id'] ?? ($song['slug'] ?? '');
    
    if (!$slug) return false;
    
    try {
        $stmt = $db->prepare("
            INSERT INTO songs (slug,number,title,author,key_sig,cat_top,cat_sub,collection,source_id,source_url,lyrics_raw,lyrics_json,sections_json,updated_at)
            VALUES (:slug,:number,:title,:author,:key_sig,:cat_top,:cat_sub,:collection,:source_id,:source_url,:lyrics_raw,:lyrics_json,:sections_json,CURRENT_TIMESTAMP)
            ON CONFLICT(slug) DO UPDATE SET
                title=excluded.title, author=excluded.author, number=excluded.number,
                cat_top=excluded.cat_top, cat_sub=excluded.cat_sub,
                lyrics_raw=excluded.lyrics_raw, lyrics_json=excluded.lyrics_json,
                sections_json=excluded.sections_json, updated_at=CURRENT_TIMESTAMP
        ");
        $stmt->execute([
            ':slug' => $slug, ':number' => $song['number'] ?? 0,
            ':title' => $song['title'] ?? '', ':author' => $song['author'] ?? '',
            ':key_sig' => $song['key'] ?? '', ':cat_top' => $song['cat_top'] ?? '',
            ':cat_sub' => $song['cat_sub'] ?? '', ':collection' => $song['collection'] ?? '',
            ':source_id' => $song['source_id'] ?? '', ':source_url' => $song['source'] ?? '',
            ':lyrics_raw' => $lyricsRaw, ':lyrics_json' => $lyricsJson,
            ':sections_json' => $sectionsJson,
        ]);
        return true;
    } catch (Exception $e) { return false; }
}

// ── ACTIONS ──────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'start';

if ($action === 'progress') {
    echo file_exists($PROGRESS_FILE) ? file_get_contents($PROGRESS_FILE) : json_encode(['status' => 'idle']);
    exit;
}

if ($action === 'stop') {
    $p = json_decode(file_exists($PROGRESS_FILE) ? file_get_contents($PROGRESS_FILE) : '{}', true);
    $p['status'] = 'stopped';
    saveProgress($p);
    echo json_encode(['ok' => true, 'status' => 'stopped']);
    exit;
}

if ($action === 'reset') {
    @unlink($PROGRESS_FILE);
    echo json_encode(['ok' => true]);
    exit;
}

// Which sources to download
$doHTTLVN = filter_var($_GET['httlvn'] ?? 'true', FILTER_VALIDATE_BOOLEAN);
$doTCL    = filter_var($_GET['tcl'] ?? 'true', FILTER_VALIDATE_BOOLEAN);

// ── START ────────────────────────────────────────────────────────
if ($action === 'start') {
    // Check if already running
    if (file_exists($PROGRESS_FILE)) {
        $p = json_decode(file_get_contents($PROGRESS_FILE), true);
        if (($p['status'] ?? '') === 'running') {
            echo json_encode(['status' => 'already-running', 'progress' => $p]);
            exit;
        }
    }
    
    saveProgress(['status' => 'starting', 'done' => 0, 'total' => 0, 'failed' => 0, 'current' => 'Khởi động...', 'startedAt' => date('Y-m-d H:i:s')]);
    
    echo json_encode(['ok' => true, 'status' => 'started']);
    if (ob_get_level()) { ob_flush(); flush(); }
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { header('Connection: close'); header('Content-Length: ' . ob_get_length()); @ob_end_flush(); flush(); }
    
    // ── BACKGROUND WORK ──────────────────────────────────────────
    $db   = getDB();
    $done = 0; $failed = 0; $total = 0;
    
    // Phase 1: Count total
    if ($doHTTLVN) $total += HTTLVN_TOTAL;
    
    // Collect TCL catalogs
    $tclAllSongs = [];
    if ($doTCL) {
        saveProgress(['status' => 'running', 'phase' => 'Đang quét danh sách...', 'done' => 0, 'total' => $total, 'failed' => 0, 'current' => '']);
        
        foreach (TCL_COLLECTIONS as $coll) {
            $p = json_decode(@file_get_contents($PROGRESS_FILE), true);
            if (($p['status'] ?? '') === 'stopped') break;
            
            $cacheFile = $CACHE_DIR . "catalog_tcl_{$coll}.json";
            if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
                $data = json_decode(file_get_contents($cacheFile), true);
            } else {
                $url  = "http://www.thanhcatinlanh.com/index.php/{$coll}";
                $html = http($url);
                preg_match_all('/<a\s+href="\/index\.php\/' . preg_quote($coll, '/') . '\/(\d+-[^"]+)"[^>]*>([^<]+)</si', $html, $ms, PREG_SET_ORDER);
                $songs = []; $seen = [];
                foreach ($ms as $m) {
                    $slug  = trim($m[1]); $title = html_entity_decode(trim($m[2]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                    if (strlen($title) < 2 || isset($seen[$slug])) continue;
                    $seen[$slug] = true;
                    preg_match('/^(\d+)-/', $slug, $nm);
                    $songs[] = ['slug' => $slug, 'title' => $title, 'number' => isset($nm[1]) ? intval($nm[1]) : 0, 'collection' => $coll, 'url' => "http://www.thanhcatinlanh.com/index.php/{$coll}/{$slug}"];
                }
                $data = ['songs' => $songs];
                file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
                usleep(500000);
            }
            $tclAllSongs = array_merge($tclAllSongs, $data['songs'] ?? []);
        }
        $total += count($tclAllSongs);
    }
    
    saveProgress(['status' => 'running', 'phase' => 'Đang tải lời bài hát...', 'done' => 0, 'total' => $total, 'failed' => 0, 'current' => '']);
    
    // Phase 2a: HTTLVN songs (1 to 553 by number)
    if ($doHTTLVN) {
        for ($num = 1; $num <= HTTLVN_TOTAL; $num++) {
            $p = json_decode(@file_get_contents($PROGRESS_FILE), true);
            if (($p['status'] ?? '') === 'stopped') break;
            
            $cacheFile = $CACHE_DIR . "httlvn_song_{$num}.json";
            if (file_exists($cacheFile)) {
                $song = json_decode(file_get_contents($cacheFile), true);
                saveSong($db, $song);
                $done++;
            } else {
                // Need to find the slug first via listing
                // Use the Go redirect: /Home/Go?id=N
                $redirectHtml = http("https://thanhca.httlvn.org/Home/Go?id={$num}&type_book=thanh-ca");
                if (preg_match('/(\/thanh-ca-\d+\/[^"\'?\s]+)/i', $redirectHtml, $m)) {
                    $path = $m[1];
                    $songUrl = 'https://thanhca.httlvn.org' . $path;
                    $songHtml = http($songUrl);
                    
                    $song = parseHTTLVNSong($songHtml, $songUrl, $num);
                    file_put_contents($cacheFile, json_encode($song, JSON_UNESCAPED_UNICODE));
                    saveSong($db, $song);
                    $done++;
                    usleep(400000); // 0.4s
                } else {
                    $failed++;
                }
            }
            
            if (($done + $failed) % 10 === 0) {
                $pct = round($done / max($total,1) * 100, 1);
                saveProgress(['status' => 'running', 'phase' => 'HTTLVN', 'done' => $done, 'total' => $total, 'failed' => $failed, 'percent' => $pct, 'current' => "Bài {$num}/".HTTLVN_TOTAL]);
            }
        }
    }
    
    // Phase 2b: TCL songs
    if ($doTCL) {
        foreach ($tclAllSongs as $s) {
            $p = json_decode(@file_get_contents($PROGRESS_FILE), true);
            if (($p['status'] ?? '') === 'stopped') break;
            
            $cacheFile = $CACHE_DIR . "tcl_song_" . md5($s['collection'] . '_' . $s['slug']) . '.json';
            $song = null;
            
            if (file_exists($cacheFile)) {
                $song = json_decode(file_get_contents($cacheFile), true);
            } else {
                $html = http($s['url']);
                if (!$html) { $failed++; usleep(1000000); continue; }
                
                $title = ''; $author = ''; $key = '';
                if (preg_match('/<h1 itemprop="name">([^<]+)/si', $html, $m)) $title = html_entity_decode(trim(strip_tags($m[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/itemprop="name">([^<]+)<\/span>/si', $html, $m)) $author = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
                if (preg_match('/Hợp âm.*?\(([A-Gb#m]+)\)/si', $html, $m)) $key = trim($m[1]);
                
                $lines = extractLyricsTCL($html);
                $song = [
                    'id' => "tcl-{$s['collection']}-{$s['slug']}",
                    'slug' => $s['slug'], 'number' => $s['number'],
                    'title' => $title ?: $s['title'], 'author' => $author, 'key' => $key,
                    'cat_top' => '', 'cat_sub' => '',
                    'source_id' => 'thanhcatinlanh', 'collection' => $s['collection'],
                    'source' => $s['url'],
                    'sections' => groupSections($lines), 'rawLines' => $lines,
                    'fetchedAt' => date('Y-m-d H:i:s'),
                ];
                file_put_contents($cacheFile, json_encode($song, JSON_UNESCAPED_UNICODE));
                usleep(400000);
            }
            
            if ($song && saveSong($db, $song)) $done++;
            else $failed++;
            
            if (($done + $failed) % 10 === 0) {
                $pct = round($done / max($total,1) * 100, 1);
                saveProgress(['status' => 'running', 'phase' => 'TCL - ' . $s['collection'], 'done' => $done, 'total' => $total, 'failed' => $failed, 'percent' => $pct, 'current' => $song['title'] ?? '']);
            }
        }
    }
    
    saveProgress(['status' => 'done', 'done' => $done, 'total' => $total, 'failed' => $failed, 'percent' => 100, 'finishedAt' => date('Y-m-d H:i:s')]);
    exit;
}

// ── Helpers shared with scraper.php ──────────────────────────────
function parseHTTLVNSong($html, $url, $id = 0) {
    $num = $id;
    $title = '';
    if (preg_match('/Thánh Ca \d+:\s*([^-<]+)/u', $html, $m)) $title = html_entity_decode(trim($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $catTop = ''; $catSub = '';
    if (preg_match_all('/cat_top=([^"&]+)"[^>]*>([^<]+)</u', $html, $cats)) {
        $catTop = html_entity_decode(urldecode($cats[1][0] ?? ''), ENT_QUOTES, 'UTF-8');
    }
    
    $raw = '';
    if (preg_match('/<pre[^>]*class="[^"]*lyric[^"]*"[^>]*>(.*?)<\/pre>/si', $html, $m)) $raw = $m[1];
    elseif (preg_match('/<div[^>]*class="[^"]*lyric[^"]*"[^>]*>(.*?)<\/div>/si', $html, $m)) $raw = $m[1];
    elseif (preg_match('/<div class="container-fluid">(.*?)<div class="row row-control/si', $html, $m)) $raw = $m[1];
    
    $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
    $raw = strip_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_values(array_filter(array_map('trim', explode("\n", $raw)), fn($l) => mb_strlen($l) > 1));
    
    return [
        'id' => "httlvn-{$num}", 'slug' => "httlvn-{$num}", 'number' => $num,
        'title' => $title ?: "Bài {$num}", 'author' => '', 'key' => '',
        'cat_top' => $catTop, 'cat_sub' => $catSub,
        'source_id' => 'httlvn', 'collection' => 'httl-vn', 'source' => $url,
        'sections' => groupSections($lines), 'rawLines' => $lines,
        'fetchedAt' => date('Y-m-d H:i:s'),
    ];
}

function extractLyricsTCL($html) {
    $raw = '';
    if (preg_match('/Lời Bài Hát.*?<\/h2>(.*?)<\/div>\s*<div class="jwts_tabbertab"/si', $html, $m)) $raw = $m[1];
    elseif (preg_match('/<div class="description"[^>]*>(.*?)(?:<div class="clr|<div class="jwts_tabber)/si', $html, $m)) $raw = $m[1];
    else return [];
    
    $raw = preg_replace('/<br\s*\/?>/i', "\n", $raw);
    $raw = strip_tags($raw);
    $raw = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return array_values(array_filter(array_map('trim', explode("\n", $raw)), fn($l) => mb_strlen($l) > 1));
}

function groupSections($lines) {
    $sections = []; $cur = []; $idx = 1;
    foreach ($lines as $line) {
        if (preg_match('/^(\d+[\.\)]|ĐK\s*:|Điệp khúc|Bridge|Chorus)/ui', $line)) {
            if (!empty($cur)) { $sections[] = ['label' => "Câu {$idx}", 'lines' => $cur]; $cur = []; $idx++; }
        } else {
            $cur[] = $line;
            if (count($cur) >= 4) { $sections[] = ['label' => "Câu {$idx}", 'lines' => $cur]; $cur = []; $idx++; }
        }
    }
    if (!empty($cur)) $sections[] = ['label' => "Câu {$idx}", 'lines' => $cur];
    return $sections ?: [['label' => 'Câu 1', 'lines' => $lines]];
}

echo json_encode(['error' => 'Unknown action']);
