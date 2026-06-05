<?php
/**
 * HTTLVN Bulk Download — Tải 903 bài Thánh Ca vào SQLite
 * 
 * Quy trình:
 * 1. Fetch all pages (/thanh-ca?page=N) → collect slug list
 * 2. For each slug: fetch song page, extract lyrics, save to DB
 * 
 * Usage:
 *   ?action=start   — Start background download
 *   ?action=stop    — Stop
 *   ?action=progress — Check progress
 *   ?action=reset   — Reset progress file
 *   ?action=stats   — Count saved songs
 */

ignore_user_abort(true);
set_time_limit(7200);
ini_set('max_execution_time', 7200);
ini_set('memory_limit', '256M');

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$PROGRESS_FILE = __DIR__ . '/../data/dl-progress.json';
$DB_FILE       = __DIR__ . '/../data/thanh-ca.db';
$CACHE_DIR     = __DIR__ . '/../data/httlvn-cache/';
if (!is_dir($CACHE_DIR)) mkdir($CACHE_DIR, 0755, true);

// ── Helpers ───────────────────────────────────────────────────────
function hfetch($url, $timeout = 25) {
    static $lastFetch = 0;
    $delay = 380000;
    $since = microtime(true) * 1e6 - $lastFetch;
    if ($since < $delay) usleep((int)($delay - $since));
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: text/html\r\nAccept-Language: vi-VN,vi;q=0.9\r\n",
        'ignore_errors' => true, 'follow_location' => 1, 'max_redirects' => 5,
    ]]);
    $lastFetch = microtime(true) * 1e6;
    $raw = @file_get_contents($url, false, $ctx) ?: '';
    if ($raw && !mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'auto');
    }
    return $raw;
}

function saveProgress($d) {
    global $PROGRESS_FILE;
    file_put_contents($PROGRESS_FILE, json_encode($d, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function loadProgress() {
    global $PROGRESS_FILE;
    return file_exists($PROGRESS_FILE) ? json_decode(file_get_contents($PROGRESS_FILE), true) : [];
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
        title TEXT NOT NULL DEFAULT '', author TEXT DEFAULT '',
        key_sig TEXT DEFAULT '', cat_top TEXT DEFAULT '', cat_sub TEXT DEFAULT '',
        collection TEXT DEFAULT '', source_id TEXT DEFAULT '',
        source_url TEXT DEFAULT '', lyrics_raw TEXT DEFAULT '',
        lyrics_json TEXT DEFAULT '[]', sections_json TEXT DEFAULT '[]',
        saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_slug ON songs(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_num  ON songs(number)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_src  ON songs(source_id)");
    return $db;
}

// ── Lyrics extraction ─────────────────────────────────────────────
function extractLyrics($html) {
    // Lyrics live in <div id="lyric-content">...</div>
    if (!preg_match('#<div\s+id="lyric-content"[^>]*>([\s\S]+?)(?:</div>\s*</div>\s*</div>|<div class="row row-control)#si', $html, $m)) return [];
    
    $raw = $m[1];
    $raw = preg_replace('#</p>\s*<p>#si', "\n", $raw);
    $raw = preg_replace('#<p>#si', '', $raw);
    $raw = preg_replace('#</p>#si', "\n", $raw);
    $raw = preg_replace('#<br\s*/?>#si', "\n", $raw);
    $text = strip_tags($raw);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    $lines = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if (!$line) continue;
        // Skip navigation artifacts (← 001  003 →)
        if (preg_match('/^[←→\d\s]+$/', $line)) continue;
        // Skip chord-only lines (e.g. "C  G  Am  F")
        if (preg_match('/^[A-G][#b]?[\w\/]*(\s+[A-G][#b]?[\w\/]*)+\s*$/', $line) && !preg_match('/\p{Ll}{3}/u', $line)) continue;
        $lines[] = $line;
    }
    return $lines;
}

function parseSections($lines) {
    $sections = []; $label = null; $cur = [];
    foreach ($lines as $line) {
        if (preg_match('/^(Câu\s*\d+|Điệp\s*[Kk]húc|ĐK\s*:|Bridge|Chorus|Verse\s*\d+|Cầu\s*nối)/ui', $line)) {
            if (!empty($cur)) $sections[] = ['label' => $label ?? 'Câu 1', 'lines' => $cur];
            $label = preg_replace('/[:\s]+$/', '', trim($line));
            $cur   = [];
        } else {
            $cur[] = $line;
        }
    }
    if (!empty($cur)) $sections[] = ['label' => $label ?? 'Câu 1', 'lines' => $cur];
    return $sections ?: [['label' => 'Câu 1', 'lines' => $lines]];
}

function parseMeta($html) {
    $title = ''; $catTop = ''; $catSub = '';
    if (preg_match('#<title>([^<]+)</title>#i', $html, $m)) {
        $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (preg_match('/Thánh Ca \d+:\s*(.+?)(?:\s*-\s*Thánh Ca Tin Lành|$)/ui', $raw, $tm)) {
            $title = trim($tm[1]);
        }
    }
    if (preg_match_all('#cat_top=([^"&]+)"[^>]*>([^<]+)<#u', $html, $cats)) {
        $catTop = html_entity_decode(trim($cats[2][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    if (preg_match_all('#cat_sub=([^"&]+)"[^>]*>([^<]+)<#u', $html, $csubs)) {
        $catSub = html_entity_decode(trim($csubs[2][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return compact('title', 'catTop', 'catSub');
}

function saveSong($db, $num, $slug, $title, $catTop, $catSub, $lines, $sections, $url) {
    $raw  = implode("\n", $lines);
    $json = json_encode($lines, JSON_UNESCAPED_UNICODE);
    $secs = json_encode($sections, JSON_UNESCAPED_UNICODE);
    $dbSlug = "httlvn-{$num}";
    $stmt = $db->prepare("
        INSERT INTO songs (slug,number,title,cat_top,cat_sub,collection,source_id,source_url,lyrics_raw,lyrics_json,sections_json,updated_at)
        VALUES (:slug,:num,:title,:cat_top,:cat_sub,'httl-vn','httlvn',:src,:raw,:json,:secs,CURRENT_TIMESTAMP)
        ON CONFLICT(slug) DO UPDATE SET
            title=excluded.title, number=excluded.number,
            cat_top=excluded.cat_top, cat_sub=excluded.cat_sub,
            lyrics_raw=excluded.lyrics_raw, lyrics_json=excluded.lyrics_json,
            sections_json=excluded.sections_json, updated_at=CURRENT_TIMESTAMP
    ");
    $stmt->execute([':slug'=>$dbSlug,':num'=>$num,':title'=>$title,':cat_top'=>$catTop,':cat_sub'=>$catSub,':src'=>$url,':raw'=>$raw,':json'=>$json,':secs'=>$secs]);
    return true;
}

// ── ACTIONS ───────────────────────────────────────────────────────
$action = $_GET['action'] ?? 'progress';

if ($action === 'progress') {
    $p = loadProgress();
    echo json_encode($p ?: ['status' => 'idle']);
    exit;
}

if ($action === 'stop') {
    $p = loadProgress(); $p['status'] = 'stopped';
    saveProgress($p);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'reset') {
    @unlink($PROGRESS_FILE);
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'stats') {
    try {
        $db    = getDB();
        $total = $db->query("SELECT COUNT(*) FROM songs WHERE source_id='httlvn'")->fetchColumn();
        $cats  = $db->query("SELECT cat_top, COUNT(*) c FROM songs WHERE source_id='httlvn' GROUP BY cat_top ORDER BY c DESC")->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['total' => $total, 'by_category' => $cats]);
    } catch(Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
    exit;
}

if ($action === 'start') {
    $p = loadProgress();
    if (($p['status'] ?? '') === 'running') {
        echo json_encode(['status' => 'already-running']);
        exit;
    }
    
    saveProgress(['status' => 'starting', 'done' => 0, 'total' => 0, 'failed' => 0, 'percent' => 0, 'current' => 'Khởi động...', 'startedAt' => date('c')]);
    
    echo json_encode(['ok' => true, 'status' => 'started']);
    if (ob_get_level()) { ob_flush(); flush(); }
    if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();
    else { header('Connection: close'); header('Content-Length: ' . ob_get_length()); @ob_end_flush(); flush(); }
    
    // ════════════════════════════════════
    // BACKGROUND WORK
    // ════════════════════════════════════
    $db   = getDB();
    $done = 0; $failed = 0;
    
    // ── Phase 1: Collect all song slugs from all listing pages ──
    saveProgress(['status' => 'running', 'phase' => 'Đang quét danh sách...', 'done' => 0, 'total' => 0, 'failed' => 0, 'percent' => 0, 'current' => '']);
    
    $allSongs = [];
    $page     = 1;
    $maxPages = 25; // safety limit
    
    do {
        $p = loadProgress();
        if (($p['status'] ?? '') === 'stopped') exit;
        
        $listUrl  = "https://thanhca.httlvn.org/thanh-ca?page={$page}";
        $cacheKey = "list_p{$page}";
        $cacheFile = $CACHE_DIR . $cacheKey . ".json";
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $pageSongs = json_decode(file_get_contents($cacheFile), true);
        } else {
            $listHtml = hfetch($listUrl);
            if (!$listHtml) break;
            
            // Parse: <div class="hymn-item mb-1 tc-{id}">
            //        <a href="/thanh-ca-{id}/{slug}"> NNN </a>
            //        <h3><a>TITLE</a></h3>
            preg_match_all(
                '#tc-(\d+)">\s*<a href="(/thanh-ca-(\d+)/([^"]+))"[^>]*>\s*\d+\s*</a>\s*<div[^>]*>\s*<h3[^>]*><a[^>]*>([^<]+)</a>#si',
                $listHtml, $ms, PREG_SET_ORDER
            );
            
            $pageSongs = [];
            foreach ($ms as $m) {
                $pageSongs[] = [
                    'id'    => intval($m[1]),
                    'num'   => intval($m[3]),
                    'slug'  => ltrim($m[2], '/'),
                    'title' => html_entity_decode(trim($m[5]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'url'   => 'https://thanhca.httlvn.org' . $m[2],
                ];
            }
            
            file_put_contents($cacheFile, json_encode($pageSongs, JSON_UNESCAPED_UNICODE));
            
            // Check if next page exists
            if (!preg_match('/page=' . ($page+1) . '/', $listHtml) || empty($pageSongs)) break;
        }
        
        $allSongs = array_merge($allSongs, $pageSongs);
        saveProgress(['status' => 'running', 'phase' => "Quét trang {$page}...", 'done' => 0, 'total' => count($allSongs), 'failed' => 0, 'percent' => 0, 'current' => "Trang {$page}: " . count($pageSongs) . " bài"]);
        
        $page++;
    } while ($page <= $maxPages);
    
    $total = count($allSongs);
    
    // ── Phase 2: Fetch each song and save ──────────────────────
    saveProgress(['status' => 'running', 'phase' => 'Đang tải lời bài hát...', 'done' => 0, 'total' => $total, 'failed' => 0, 'percent' => 0, 'current' => '']);
    
    foreach ($allSongs as $s) {
        $p = loadProgress();
        if (($p['status'] ?? '') === 'stopped') break;
        
        $num  = $s['num'];
        $slug = $s['slug'];
        $url  = $s['url'];
        
        // Song cache
        $songCache = $CACHE_DIR . "song_{$num}.json";
        
        if (file_exists($songCache)) {
            // Already downloaded — just save to DB
            $cached = json_decode(file_get_contents($songCache), true);
            try {
                saveSong($db, $num, $slug, $cached['title'], $cached['catTop'], $cached['catSub'], $cached['lines'], $cached['sections'], $url);
                $done++;
            } catch (Exception $e) { $failed++; }
        } else {
            // Fetch from HTTLVN
            $html = hfetch($url);
            if (!$html) {
                // Retry once
                usleep(1000000);
                $html = hfetch($url);
            }
            
            if (!$html) { $failed++; continue; }
            
            $meta     = parseMeta($html);
            $lines    = extractLyrics($html);
            $sections = parseSections($lines);
            
            $title  = $meta['title'] ?: $s['title'];
            $catTop = $meta['catTop'];
            $catSub = $meta['catSub'];
            
            // Save to cache
            file_put_contents($songCache, json_encode(compact('title','catTop','catSub','lines','sections'), JSON_UNESCAPED_UNICODE));
            
            // Save to DB
            try {
                saveSong($db, $num, $slug, $title, $catTop, $catSub, $lines, $sections, $url);
                $done++;
            } catch (Exception $e) { $failed++; }
        }
        
        // Update progress every 5 songs
        if (($done + $failed) % 5 === 0 || $done === $total) {
            $pct = round($done / max($total, 1) * 100, 1);
            saveProgress([
                'status'  => 'running',
                'phase'   => 'Tải lời bài hát',
                'done'    => $done,
                'total'   => $total,
                'failed'  => $failed,
                'percent' => $pct,
                'current' => "Bài {$num}: " . mb_substr($s['title'], 0, 40),
            ]);
        }
    }
    
    saveProgress([
        'status'     => 'done',
        'done'       => $done,
        'total'      => $total,
        'failed'     => $failed,
        'percent'    => 100,
        'current'    => "Hoàn tất!",
        'finishedAt' => date('c'),
    ]);
    exit;
}

echo json_encode(['error' => 'Unknown action. Use: start|stop|progress|reset|stats']);
