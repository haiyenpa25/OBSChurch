<?php
/**
 * OBSChurch — Thánh Ca SQLite Database API
 * Lưu toàn bộ lời bài hát + metadata từ thanhcatinlanh.com
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$DB_FILE = __DIR__ . '/../data/thanh-ca.db';

// ── Khởi tạo SQLite ──────────────────────────────────────────────
function getDB() {
    global $DB_FILE;
    $dir = dirname($DB_FILE);
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    
    $db = new PDO("sqlite:$DB_FILE");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // WAL mode for better performance
    $db->exec("PRAGMA journal_mode=WAL");
    $db->exec("PRAGMA synchronous=NORMAL");
    
    // Tạo bảng nếu chưa có
    $db->exec("
        CREATE TABLE IF NOT EXISTS songs (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            slug        TEXT UNIQUE NOT NULL,
            number      INTEGER DEFAULT 0,
            title       TEXT NOT NULL,
            author      TEXT DEFAULT '',
            key_sig     TEXT DEFAULT '',
            cat_top     TEXT DEFAULT '',
            cat_sub     TEXT DEFAULT '',
            collection  TEXT DEFAULT '',
            source_id   TEXT DEFAULT '',
            source_url  TEXT DEFAULT '',
            lyrics_raw  TEXT DEFAULT '',
            lyrics_json TEXT DEFAULT '[]',
            sections_json TEXT DEFAULT '[]',
            tags        TEXT DEFAULT '',
            saved_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at  DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
    $db->exec("CREATE INDEX IF NOT EXISTS idx_slug       ON songs(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_number     ON songs(number)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_collection ON songs(collection)");
    $db->exec("CREATE VIRTUAL TABLE IF NOT EXISTS songs_fts USING fts5(
        title, author, lyrics_raw, collection,
        content='songs', content_rowid='id'
    )");
    
    // Triggers để sync FTS
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS songs_ai AFTER INSERT ON songs BEGIN
            INSERT INTO songs_fts(rowid, title, author, lyrics_raw, collection)
            VALUES (new.id, new.title, new.author, new.lyrics_raw, new.collection);
        END
    ");
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS songs_au AFTER UPDATE ON songs BEGIN
            INSERT INTO songs_fts(songs_fts, rowid, title, author, lyrics_raw, collection)
            VALUES ('delete', old.id, old.title, old.author, old.lyrics_raw, old.collection);
            INSERT INTO songs_fts(rowid, title, author, lyrics_raw, collection)
            VALUES (new.id, new.title, new.author, new.lyrics_raw, new.collection);
        END
    ");
    $db->exec("
        CREATE TRIGGER IF NOT EXISTS songs_ad AFTER DELETE ON songs BEGIN
            INSERT INTO songs_fts(songs_fts, rowid, title, author, lyrics_raw, collection)
            VALUES ('delete', old.id, old.title, old.author, old.lyrics_raw, old.collection);
        END
    ");
    
    return $db;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'stats';
$db = getDB();

// ── SAVE SONG ────────────────────────────────────────────────────
if ($action === 'save') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    
    $slug       = trim($body['slug'] ?? $_POST['slug'] ?? '');
    $title      = trim($body['title'] ?? '');
    $author     = trim($body['author'] ?? '');
    $key_sig    = trim($body['key'] ?? '');
    $number     = intval($body['number'] ?? 0);
    $collection = trim($body['collection'] ?? '');
    $source_url = trim($body['source'] ?? '');
    $sections   = $body['sections'] ?? [];
    $rawLines   = $body['rawLines'] ?? [];
    
    if (!$slug || !$title) {
        echo json_encode(['error' => 'Missing slug or title']); exit;
    }
    
    // Build lyrics_raw from rawLines
    $lyricsRaw = implode("\n", $rawLines);
    $lyricsJson = json_encode($rawLines, JSON_UNESCAPED_UNICODE);
    $sectionsJson = json_encode($sections, JSON_UNESCAPED_UNICODE);
    
    $stmt = $db->prepare("
        INSERT INTO songs (slug, number, title, author, key_sig, collection, source_url, lyrics_raw, lyrics_json, sections_json, updated_at)
        VALUES (:slug, :number, :title, :author, :key_sig, :collection, :source_url, :lyrics_raw, :lyrics_json, :sections_json, CURRENT_TIMESTAMP)
        ON CONFLICT(slug) DO UPDATE SET
            title       = excluded.title,
            author      = excluded.author,
            key_sig     = excluded.key_sig,
            number      = excluded.number,
            collection  = excluded.collection,
            source_url  = excluded.source_url,
            lyrics_raw  = excluded.lyrics_raw,
            lyrics_json = excluded.lyrics_json,
            sections_json = excluded.sections_json,
            updated_at  = CURRENT_TIMESTAMP
    ");
    
    $stmt->execute([
        ':slug' => $slug, ':number' => $number, ':title' => $title,
        ':author' => $author, ':key_sig' => $key_sig,
        ':collection' => $collection, ':source_url' => $source_url,
        ':lyrics_raw' => $lyricsRaw, ':lyrics_json' => $lyricsJson,
        ':sections_json' => $sectionsJson,
    ]);
    
    $id = $db->lastInsertId() ?: $db->query("SELECT id FROM songs WHERE slug='$slug'")->fetchColumn();
    echo json_encode(['ok' => true, 'id' => $id, 'slug' => $slug, 'title' => $title]);
    exit;
}

// ── GET SONG ─────────────────────────────────────────────────────
if ($action === 'get') {
    $slug = $_GET['slug'] ?? '';
    $id   = $_GET['id'] ?? '';
    
    if ($slug) {
        $stmt = $db->prepare("SELECT * FROM songs WHERE slug = ?");
        $stmt->execute([$slug]);
    } else {
        $stmt = $db->prepare("SELECT * FROM songs WHERE id = ?");
        $stmt->execute([$id]);
    }
    
    $song = $stmt->fetch();
    if (!$song) { echo json_encode(['error' => 'Not found']); exit; }
    
    $song['sections'] = json_decode($song['sections_json'] ?? '[]', true);
    $song['rawLines'] = json_decode($song['lyrics_json'] ?? '[]', true);
    echo json_encode($song, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── LIST SONGS ───────────────────────────────────────────────────
if ($action === 'list') {
    $collection = $_GET['collection'] ?? '';
    $q          = $_GET['q'] ?? '';
    $limit      = min(500, intval($_GET['limit'] ?? 200));
    $offset     = intval($_GET['offset'] ?? 0);
    
    if ($q) {
        // Full text search
        $stmt = $db->prepare("
            SELECT s.id, s.slug, s.number, s.title, s.author, s.key_sig, s.collection,
                   length(s.lyrics_raw) as lyrics_len
            FROM songs s
            JOIN songs_fts f ON s.id = f.rowid
            WHERE songs_fts MATCH ?
            " . ($collection ? "AND s.collection = ?" : "") . "
            ORDER BY s.number ASC
            LIMIT ? OFFSET ?
        ");
        $params = [$q . '*'];
        if ($collection) $params[] = $collection;
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
    } else {
        $where = $collection ? "WHERE collection = ?" : "";
        $params = $collection ? [$collection] : [];
        $stmt = $db->prepare("
            SELECT id, slug, number, title, author, key_sig, collection,
                   length(lyrics_raw) as lyrics_len
            FROM songs $where
            ORDER BY number ASC
            LIMIT ? OFFSET ?
        ");
        $params[] = $limit;
        $params[] = $offset;
        $stmt->execute($params);
    }
    
    $songs = $stmt->fetchAll();
    
    // Total count
    $countStmt = $db->prepare("SELECT COUNT(*) FROM songs " . ($collection ? "WHERE collection = ?" : ""));
    $countStmt->execute($collection ? [$collection] : []);
    $total = $countStmt->fetchColumn();
    
    echo json_encode([
        'total'  => (int)$total,
        'count'  => count($songs),
        'offset' => $offset,
        'songs'  => $songs,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CHECK SLUGS (batch check which slugs are in DB) ──────────────
if ($action === 'check-slugs') {
    $body = json_decode(file_get_contents('php://input'), true) ?? [];
    $slugs = $body['slugs'] ?? [];
    
    if (empty($slugs)) { echo json_encode(['saved' => []]); exit; }
    
    $placeholders = implode(',', array_fill(0, count($slugs), '?'));
    $stmt = $db->prepare("SELECT slug FROM songs WHERE slug IN ($placeholders)");
    $stmt->execute($slugs);
    $saved = array_column($stmt->fetchAll(), 'slug');
    echo json_encode(['saved' => $saved], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── DELETE SONG ──────────────────────────────────────────────────
if ($action === 'delete') {
    $slug = $_GET['slug'] ?? '';
    if (!$slug) { echo json_encode(['error' => 'No slug']); exit; }
    
    $stmt = $db->prepare("DELETE FROM songs WHERE slug = ?");
    $stmt->execute([$slug]);
    echo json_encode(['ok' => true, 'deleted' => $slug]);
    exit;
}

// ── STATS ────────────────────────────────────────────────────────
if ($action === 'stats') {
    $stmt = $db->query("
        SELECT collection, COUNT(*) as count
        FROM songs GROUP BY collection ORDER BY count DESC
    ");
    $byCollection = $stmt->fetchAll();
    
    $total = array_sum(array_column($byCollection, 'count'));
    
    echo json_encode([
        'total' => (int)$total,
        'byCollection' => $byCollection,
        'dbFile' => basename($DB_FILE),
        'dbSize' => file_exists($DB_FILE) ? round(filesize($DB_FILE)/1024, 1) . ' KB' : '0 KB',
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── EXPORT ───────────────────────────────────────────────────────
if ($action === 'export') {
    $collection = $_GET['collection'] ?? '';
    $format     = $_GET['format'] ?? 'json';
    
    $where = $collection ? "WHERE collection = ?" : "";
    $stmt = $db->prepare("SELECT * FROM songs $where ORDER BY number ASC");
    $stmt->execute($collection ? [$collection] : []);
    $songs = $stmt->fetchAll();
    
    foreach ($songs as &$s) {
        $s['sections'] = json_decode($s['sections_json'], true);
        $s['rawLines'] = json_decode($s['lyrics_json'], true);
        unset($s['lyrics_json'], $s['sections_json']);
    }
    
    if ($format === 'json') {
        header('Content-Disposition: attachment; filename="thanh-ca-export.json"');
        echo json_encode(['songs' => $songs, 'exported' => count($songs)], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
    exit;
}

echo json_encode(['error' => 'Unknown action', 'actions' => ['save','get','list','check-slugs','delete','stats','export']]);
