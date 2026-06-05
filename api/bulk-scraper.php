<?php
/**
 * OBSChurch — Bulk Scraper (Background)
 * Tải toàn bộ bài hát từ thanhcatinlanh.com → SQLite DB
 * 
 * Hỗ trợ: ignore_user_abort để chạy nền dù browser đóng
 * Progress được ghi vào file JSON để UI poll realtime
 */

// Cho phép chạy nền ngay cả khi browser đóng
ignore_user_abort(true);
set_time_limit(3600); // 1 tiếng max
ini_set('max_execution_time', 3600);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$PROGRESS_FILE = __DIR__ . '/../data/bulk-progress.json';
$DB_SCRIPT     = __DIR__ . '/songs-db.php';
$DB_FILE       = __DIR__ . '/../data/thanh-ca.db';

// ── Helpers ──────────────────────────────────────────────────────
function fetchUrl($url, $timeout = 20) {
    $ctx = stream_context_create(['http' => [
        'timeout' => $timeout,
        'header'  => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36\r\n",
        'ignore_errors' => true,
    ]]);
    $result = @file_get_contents($url, false, $ctx);
    return $result ?: '';
}

function extractLyrics($html) {
    // Tab "Lời Bài Hát" section
    if (preg_match('/Lời Bài Hát.*?<\/h2>(.*?)<\/div>\s*<div class="jwts_tabbertab"/si', $html, $m)) {
        $raw = $m[1];
    } elseif (preg_match('/<div class="description"[^>]*>(.*?)<\/div>\s*<\/div>\s*<\/div>/si', $html, $m)) {
        $raw = $m[1];
    } elseif (preg_match('/<div class="description"[^>]*>(.*?)<div class="clr/si', $html, $m)) {
        $raw = $m[1];
    } else {
        // Try getting text from item-page
        if (preg_match('/<div class="item-page"[^>]*>(.*?)<div class="clr/si', $html, $m)) {
            $raw = $m[1];
        } else {
            return [];
        }
    }
    
    $raw  = preg_replace('/<br\s*\/?>/i', "\n", $raw);
    $text = strip_tags($raw);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $lines = array_filter(array_map('trim', explode("\n", $text)), fn($l) => mb_strlen($l) > 1);
    return array_values($lines);
}

function extractMeta($html) {
    $m = ['title' => '', 'author' => '', 'number' => 0, 'key' => ''];
    
    if (preg_match('/<h1 itemprop="name">([^<]+)/si', $html, $x))
        $m['title'] = trim(strip_tags($x[1]));
    if (preg_match('/itemprop="name">([^<]+)<\/span>/si', $html, $x))
        $m['author'] = trim($x[1]);
    if (preg_match('/Bài số.*?<span[^>]*>(\d+)/si', $html, $x))
        $m['number'] = (int)$x[1];
    if (preg_match('/Hợp âm.*?\(([A-Gb#m]+)\)/si', $html, $x))
        $m['key'] = trim($x[1]);
    
    return $m;
}

function groupSections($lines) {
    $sections = []; $current = []; $idx = 1;
    foreach ($lines as $line) {
        if (preg_match('/^(\d+[\.\)]|ĐK:|Điệp khúc|Bridge|Chorus|Cầu nối)/ui', $line)) {
            if (!empty($current)) {
                $sections[] = ['label' => 'Câu ' . $idx++, 'lines' => $current];
                $current = [];
            }
        } else {
            $current[] = $line;
            if (count($current) >= 4) {
                $sections[] = ['label' => 'Câu ' . $idx++, 'lines' => $current];
                $current = [];
            }
        }
    }
    if (!empty($current)) $sections[] = ['label' => 'Câu ' . $idx, 'lines' => $current];
    return $sections ?: [['label' => 'Câu 1', 'lines' => $lines]];
}

function saveProgress($data) {
    global $PROGRESS_FILE;
    file_put_contents($PROGRESS_FILE, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function saveSongToDB($song) {
    global $DB_FILE;
    
    $dir = dirname($DB_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    try {
        $db = new PDO("sqlite:$DB_FILE");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
        
        // Init table if needed
        $db->exec("CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL, number INTEGER DEFAULT 0,
            title TEXT NOT NULL, author TEXT DEFAULT '', key_sig TEXT DEFAULT '',
            collection TEXT DEFAULT '', source_url TEXT DEFAULT '',
            lyrics_raw TEXT DEFAULT '', lyrics_json TEXT DEFAULT '[]',
            sections_json TEXT DEFAULT '[]', tags TEXT DEFAULT '',
            saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        $lyricsRaw   = implode("\n", $song['rawLines'] ?? []);
        $lyricsJson  = json_encode($song['rawLines'] ?? [], JSON_UNESCAPED_UNICODE);
        $sectionsJson= json_encode($song['sections'] ?? [], JSON_UNESCAPED_UNICODE);
        
        $stmt = $db->prepare("
            INSERT INTO songs (slug, number, title, author, key_sig, collection, source_url, lyrics_raw, lyrics_json, sections_json, updated_at)
            VALUES (:slug, :number, :title, :author, :key_sig, :collection, :source_url, :lyrics_raw, :lyrics_json, :sections_json, CURRENT_TIMESTAMP)
            ON CONFLICT(slug) DO UPDATE SET
                title=excluded.title, author=excluded.author, key_sig=excluded.key_sig,
                number=excluded.number, collection=excluded.collection,
                source_url=excluded.source_url, lyrics_raw=excluded.lyrics_raw,
                lyrics_json=excluded.lyrics_json, sections_json=excluded.sections_json,
                updated_at=CURRENT_TIMESTAMP
        ");
        
        $stmt->execute([
            ':slug' => $song['slug'], ':number' => $song['number'],
            ':title' => $song['title'], ':author' => $song['author'],
            ':key_sig' => $song['key'], ':collection' => $song['collection'],
            ':source_url' => $song['source'], ':lyrics_raw' => $lyricsRaw,
            ':lyrics_json' => $lyricsJson, ':sections_json' => $sectionsJson,
        ]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ── GET PROGRESS ──────────────────────────────────────────────────
$action = $_GET['action'] ?? 'start';

if ($action === 'progress') {
    if (file_exists($PROGRESS_FILE)) {
        echo file_get_contents($PROGRESS_FILE);
    } else {
        echo json_encode(['status' => 'idle', 'total' => 0, 'done' => 0, 'failed' => 0]);
    }
    exit;
}

// ── STOP ─────────────────────────────────────────────────────────
if ($action === 'stop') {
    $p = json_decode(file_exists($PROGRESS_FILE) ? file_get_contents($PROGRESS_FILE) : '{}', true);
    $p['status'] = 'stopped';
    saveProgress($p);
    echo json_encode(['ok' => true, 'status' => 'stopped']);
    exit;
}

// ── RESET ─────────────────────────────────────────────────────────
if ($action === 'reset') {
    @unlink($PROGRESS_FILE);
    echo json_encode(['ok' => true]);
    exit;
}

// ── START BULK DOWNLOAD ────────────────────────────────────────────
if ($action === 'start') {
    $collections = [
        'thanh-ca-httl-viet-nam',
        'thanh-ca-baptist-bac-my',
        'thanh-ca-tin-lanh-bac-my',
        'ton-vinh-chua-hang-huu',
        'ca-khuc-chuc-ton',
        'bai-ca-moi',
    ];
    
    // Check if already running
    if (file_exists($PROGRESS_FILE)) {
        $p = json_decode(file_get_contents($PROGRESS_FILE), true);
        if (($p['status'] ?? '') === 'running') {
            echo json_encode(['status' => 'already-running', 'progress' => $p]);
            exit;
        }
    }
    
    // Collect all song URLs from all collections
    $allSongs = [];
    
    saveProgress([
        'status' => 'scanning', 'total' => 0, 'done' => 0, 'failed' => 0,
        'current' => 'Đang quét danh sách...', 'collections' => [],
        'startedAt' => date('Y-m-d H:i:s'),
    ]);
    
    // Flush output early so client gets a response
    echo json_encode(['ok' => true, 'status' => 'started']);
    if (ob_get_level()) { ob_flush(); flush(); }
    
    // Close connection to client but keep running
    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    } else {
        // Try to close connection manually
        header('Connection: close');
        header('Content-Length: ' . ob_get_length());
        ob_end_flush();
        flush();
    }
    
    // ── Now run background scraping ──────────────────
    $cacheDir = __DIR__ . '/../data/songcache/';
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    
    // Step 1: Get catalogs
    foreach ($collections as $coll) {
        $progress = json_decode(file_exists($PROGRESS_FILE) ? file_get_contents($PROGRESS_FILE) : '{}', true);
        if (($progress['status'] ?? '') === 'stopped') break;
        
        $cacheFile = $cacheDir . "catalog_{$coll}.json";
        
        if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $data = json_decode(file_get_contents($cacheFile), true);
        } else {
            $url  = "http://thanhcatinlanh.com/index.php/{$coll}";
            $html = fetchUrl($url);
            
            preg_match_all(
                '/<a href="\/index\.php\/' . preg_quote($coll, '/') . '\/(\d+-[^"]+)"[^>]*>([^<]+)</',
                $html, $matches
            );
            
            $songs = [];
            for ($i = 0; $i < count($matches[1]); $i++) {
                $slug  = $matches[1][$i];
                $title = trim($matches[2][$i]);
                if (strlen($title) < 2) continue;
                preg_match('/^(\d+)-/', $slug, $nm);
                $songs[] = [
                    'slug'  => $slug, 'title' => $title,
                    'number' => isset($nm[1]) ? (int)$nm[1] : 0,
                    'collection' => $coll,
                    'url'   => "http://thanhcatinlanh.com/index.php/{$coll}/{$slug}",
                ];
            }
            
            // Dedup
            $seen = []; $songs = array_values(array_filter($songs, function($s) use (&$seen) {
                if (isset($seen[$s['slug']])) return false;
                $seen[$s['slug']] = true; return true;
            }));
            
            $data = ['songs' => $songs, 'count' => count($songs)];
            file_put_contents($cacheFile, json_encode($data, JSON_UNESCAPED_UNICODE));
            usleep(500000);
        }
        
        foreach ($data['songs'] ?? [] as $s) {
            $allSongs[] = $s;
        }
    }
    
    $total = count($allSongs);
    saveProgress([
        'status' => 'running', 'total' => $total, 'done' => 0, 'failed' => 0,
        'current' => "Chuẩn bị tải {$total} bài hát...",
        'startedAt' => date('Y-m-d H:i:s'), 'collections' => array_unique(array_column($allSongs, 'collection')),
        'songs' => [],
    ]);
    
    // Step 2: Scrape each song
    $done = 0; $failed = 0; $recent = [];
    
    foreach ($allSongs as $s) {
        // Check stop signal
        $p = json_decode(file_exists($PROGRESS_FILE) ? file_get_contents($PROGRESS_FILE) : '{}', true);
        if (($p['status'] ?? '') === 'stopped') break;
        
        $slug = $s['slug']; $coll = $s['collection'];
        $songCacheFile = $cacheDir . md5($coll . '_' . $slug) . '.json';
        
        $song = null;
        
        // Use cache if available
        if (file_exists($songCacheFile)) {
            $song = json_decode(file_get_contents($songCacheFile), true);
        } else {
            $html = fetchUrl($s['url']);
            if (!$html) { $failed++; usleep(1000000); continue; }
            
            $meta     = extractMeta($html);
            $lines    = extractLyrics($html);
            $sections = groupSections($lines);
            
            $song = [
                'id' => $slug, 'slug' => $slug,
                'title'    => $meta['title'] ?: $s['title'],
                'author'   => $meta['author'],
                'number'   => $s['number'] ?: $meta['number'],
                'key'      => $meta['key'],
                'collection' => $coll,
                'source'   => $s['url'],
                'sections' => $sections,
                'rawLines' => $lines,
                'fetchedAt' => date('Y-m-d H:i:s'),
            ];
            
            file_put_contents($songCacheFile, json_encode($song, JSON_UNESCAPED_UNICODE));
            usleep(400000); // 0.4s delay per song
        }
        
        if ($song) {
            $saved = saveSongToDB($song);
            if ($saved) $done++;
            else $failed++;
            
            $recent[] = ['title' => $song['title'], 'number' => $song['number'], 'slug' => $slug];
            if (count($recent) > 10) array_shift($recent);
        }
        
        // Update progress every 5 songs
        if (($done + $failed) % 5 === 0) {
            $pct = $total > 0 ? round(($done + $failed) / $total * 100, 1) : 0;
            saveProgress([
                'status'  => 'running', 'total' => $total,
                'done'    => $done, 'failed' => $failed,
                'percent' => $pct,
                'current' => $song['title'] ?? '',
                'recent'  => $recent,
                'startedAt' => date('Y-m-d H:i:s'),
            ]);
        }
    }
    
    // Done!
    saveProgress([
        'status'    => 'done', 'total' => $total,
        'done'      => $done, 'failed' => $failed, 'percent' => 100,
        'current'   => 'Hoàn tất!',
        'finishedAt' => date('Y-m-d H:i:s'),
    ]);
    exit;
}

echo json_encode(['error' => 'Unknown action: ' . $action, 'actions' => ['start', 'progress', 'stop', 'reset']]);
