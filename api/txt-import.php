<?php
/**
 * Parser: Chuyển TXT bài hát → JSON + SQLite
 * Rule: UTF-8 toàn bộ
 * 
 * Format file TXT:
 *   Dòng 1: <số> <TÊN BÀI HÁT>
 *   Dòng 2: <Tác giả / lời dịch> (nếu có)
 *   Dòng 3: (trống)
 *   Dòng 4+: <câu 1>, <câu 2>, <Điệp khúc>, ... + lời
 *
 * Collections:
 *   Ca Khúc Chúc Tôn   (300 bài) — source_id: ca-khuc-chuc-ton
 *   Thánh Ca Tin Lành  (853 bài) — source_id: thanh-ca-tin-lanh
 *   Tôn Vinh Chúa Hằng Hữu (100 bài) — source_id: ton-vinh-chua
 */
header('Content-Type: application/json; charset=utf-8');

// ── Config ────────────────────────────────────────────────────────
$BASE = 'D:/Xampp/htdocs/OBSChurch/docs/';
$DB_FILE = 'D:/Xampp/htdocs/OBSChurch/data/thanh-ca.db';
$OUTPUT_DIR = 'D:/Xampp/htdocs/OBSChurch/data/songs-json/';

$COLLECTIONS = [
    [
        'dir'       => $BASE . 'Ca khuc chuc ton/',
        'name'      => 'Ca Khúc Chúc Tôn',
        'source_id' => 'ca-khuc-chuc-ton',
        'cat_top'   => 'Ca Khúc Chúc Tôn',
    ],
    [
        'dir'       => $BASE . 'Thanh ca tin lanh/',
        'name'      => 'Thánh Ca Tin Lành',
        'source_id' => 'thanh-ca-tin-lanh',
        'cat_top'   => 'Thánh Ca Tin Lành',
    ],
    [
        'dir'       => $BASE . 'Ton vinh chua hang huu/',
        'name'      => 'Tôn Vinh Chúa Hằng Hữu',
        'source_id' => 'ton-vinh-chua',
        'cat_top'   => 'Tôn Vinh Chúa Hằng Hữu',
    ],
];

if (!is_dir($OUTPUT_DIR)) mkdir($OUTPUT_DIR, 0755, true);

// ── DB Setup ──────────────────────────────────────────────────────
function getDB($dbFile) {
    $db = new PDO("sqlite:$dbFile");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->exec("PRAGMA encoding='UTF-8'; PRAGMA journal_mode=WAL; PRAGMA synchronous=NORMAL;");
    $db->exec("CREATE TABLE IF NOT EXISTS songs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT UNIQUE NOT NULL,
        number INTEGER DEFAULT 0,
        title TEXT NOT NULL DEFAULT '',
        author TEXT DEFAULT '',
        cat_top TEXT DEFAULT '',
        cat_sub TEXT DEFAULT '',
        collection TEXT DEFAULT '',
        source_id TEXT DEFAULT '',
        source_url TEXT DEFAULT '',
        lyrics_raw TEXT DEFAULT '',
        lyrics_json TEXT DEFAULT '[]',
        sections_json TEXT DEFAULT '[]',
        saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_slug   ON songs(slug)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_src    ON songs(source_id)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_num    ON songs(number)");
    $db->exec("CREATE INDEX IF NOT EXISTS idx_songs_cat    ON songs(cat_top)");
    return $db;
}

// ── Parse 1 file TXT ──────────────────────────────────────────────
// Rule: UTF-8 — đọc file với mb_ functions, strip BOM nếu có
function parseSongFile($filePath) {
    // Đọc file — UTF-8
    $raw = file_get_contents($filePath);
    if ($raw === false) return null;

    // Strip UTF-8 BOM nếu có
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") $raw = substr($raw, 3);

    // Đảm bảo UTF-8
    if (!mb_check_encoding($raw, 'UTF-8')) {
        $raw = mb_convert_encoding($raw, 'UTF-8', 'auto');
    }

    // Normalize line endings → \n
    $raw = str_replace(["\r\n", "\r"], "\n", $raw);
    $lines = explode("\n", $raw);

    // ── Dòng 1: <số> <TÊN> hoặc <số> <TÊN> ──────────────────────
    $num    = 0;
    $title  = '';
    $author = '';

    $line1 = trim($lines[0] ?? '');
    // Pattern: <123> <TÊN BÀI HÁT>  hoặc  <123> <TÊN>
    if (preg_match('/^<(\d+)>\s*<([^>]+)>/u', $line1, $m)) {
        $num   = intval($m[1]);
        $title = trim($m[2]);
    } elseif (preg_match('/^(\d+)\s+(.+)$/u', $line1, $m)) {
        // Fallback: "1 TÊN BÀI"
        $num   = intval($m[1]);
        $title = trim($m[2]);
    }

    // ── Dòng 2: <tác giả / lời dịch> ────────────────────────────
    $line2 = trim($lines[1] ?? '');
    if (preg_match('/^<(.+)>$/u', $line2, $m)) {
        $author = trim($m[1]);
    } elseif ($line2 && !preg_match('/^<câu|^<Điệp/ui', $line2)) {
        $author = $line2;
    }

    // ── Parse sections từ dòng 3 trở đi ─────────────────────────
    $sections = [];
    $curLabel = null;
    $curLines = [];

    foreach (array_slice($lines, 2) as $line) {
        $line = trim($line);

        // Detect section label: <câu 1>, <câu 2>, <Điệp khúc>, <ĐK>, <Cầu nối>, ...
        if (preg_match('/^<(câu\s*\d*|Điệp\s*khúc|ĐK|Bridge|Chorus|Verse\s*\d*|Cầu\s*nối|Coda|Intro|Outro|R&B|Bộ phận|\d+)>$/ui', $line, $m)) {
            if ($curLabel !== null && (!empty($curLines) || $curLabel)) {
                $sections[] = [
                    'label' => $curLabel,
                    'lines' => array_filter($curLines, fn($l) => trim($l) !== ''),
                ];
            }

            // Chuẩn hóa label
            $rawLabel = trim($m[1]);
            if (preg_match('/^câu\s*(\d+)$/ui', $rawLabel, $cm)) {
                $curLabel = 'Câu ' . $cm[1];
            } elseif (preg_match('/^Điệp\s*khúc$/ui', $rawLabel)) {
                $curLabel = 'Điệp Khúc';
            } elseif (preg_match('/^ĐK$/ui', $rawLabel)) {
                $curLabel = 'Điệp Khúc';
            } elseif (preg_match('/^câu\s*$/ui', $rawLabel)) {
                $curLabel = 'Câu'; // Câu không số (hiếm)
            } else {
                $curLabel = mb_convert_case($rawLabel, MB_CASE_TITLE, 'UTF-8');
            }
            $curLines = [];
            continue;
        }

        // Lời bài hát — một số file gộp lời + "Điệp khúc" trên cùng 1 dòng (Ca Khúc Chúc Tôn)
        // Tách "Điệp khúcLời..." thành section riêng
        if (preg_match('/^(Điệp khúc|ĐK:?)\s*(.*)$/ui', $line, $dm)) {
            if (!empty($curLines)) {
                $sections[] = [
                    'label' => $curLabel ?? 'Câu 1',
                    'lines' => array_values(array_filter($curLines, fn($l) => trim($l) !== '')),
                ];
            }
            $curLabel = 'Điệp Khúc';
            $curLines = $dm[2] ? [trim($dm[2])] : [];
            continue;
        }

        // Dòng bình thường
        if ($line !== '') {
            // Một số file "Ca Khúc Chúc Tôn" có lời dài trên 1 dòng — giữ nguyên
            $curLines[] = $line;
        }
    }

    // Lưu section cuối
    if ($curLabel !== null && !empty($curLines)) {
        $sections[] = [
            'label' => $curLabel,
            'lines' => array_values(array_filter($curLines, fn($l) => trim($l) !== '')),
        ];
    }

    // Nếu không parse được sections → coi tất cả là Câu 1
    if (empty($sections)) {
        $allLines = array_filter(array_map('trim', array_slice($lines, 2)), fn($l) => $l !== '');
        if (!empty($allLines)) {
            $sections = [['label' => 'Câu 1', 'lines' => array_values($allLines)]];
        }
    }

    // Raw text
    $rawText = '';
    foreach ($sections as $sec) {
        $rawText .= $sec['label'] . "\n" . implode("\n", $sec['lines']) . "\n\n";
    }

    return [
        'number'   => $num,
        'title'    => $title,
        'author'   => $author,
        'sections' => $sections,
        'rawText'  => trim($rawText),
    ];
}

// ── Save to DB ────────────────────────────────────────────────────
function saveSong($db, $song, $collection) {
    $slug     = $collection['source_id'] . '-' . $song['number'];
    $rawText  = $song['rawText'];
    $linesArr = [];
    foreach ($song['sections'] as $s) {
        $linesArr[] = $s['label'];
        foreach ($s['lines'] as $l) $linesArr[] = $l;
        $linesArr[] = '';
    }
    $linesJson = json_encode(array_values(array_filter($linesArr, fn($l) => $l !== '')), JSON_UNESCAPED_UNICODE);
    $secsJson  = json_encode($song['sections'], JSON_UNESCAPED_UNICODE);

    $stmt = $db->prepare("
        INSERT INTO songs (slug,number,title,author,cat_top,cat_sub,collection,source_id,source_url,lyrics_raw,lyrics_json,sections_json,updated_at)
        VALUES (:slug,:num,:title,:author,:cat_top,'', :coll,:src,'local',:raw,:json,:secs,CURRENT_TIMESTAMP)
        ON CONFLICT(slug) DO UPDATE SET
            title=excluded.title, author=excluded.author,
            cat_top=excluded.cat_top, lyrics_raw=excluded.lyrics_raw,
            lyrics_json=excluded.lyrics_json, sections_json=excluded.sections_json,
            updated_at=CURRENT_TIMESTAMP
    ");
    $stmt->execute([
        ':slug'    => $slug,
        ':num'     => $song['number'],
        ':title'   => $song['title'],
        ':author'  => $song['author'],
        ':cat_top' => $collection['cat_top'],
        ':coll'    => $collection['name'],
        ':src'     => $collection['source_id'],
        ':raw'     => $rawText,
        ':json'    => $linesJson,
        ':secs'    => $secsJson,
    ]);
}

// ═══════════════════════════════════════════════════
// ROUTER
// ═══════════════════════════════════════════════════
$action     = $_GET['action'] ?? 'import';
$collection = $_GET['col'] ?? 'all'; // all | ca-khuc-chuc-ton | thanh-ca-tin-lanh | ton-vinh-chua
$format     = $_GET['format'] ?? 'json'; // json | stats

// ── STATS ─────────────────────────────────────────────────────────
if ($action === 'stats') {
    $db     = getDB($DB_FILE);
    $result = ['collections' => []];
    foreach ($COLLECTIONS as $col) {
        $count = $db->query("SELECT COUNT(*) FROM songs WHERE source_id='{$col['source_id']}'")->fetchColumn();
        $result['collections'][] = ['name' => $col['name'], 'source_id' => $col['source_id'], 'count' => intval($count)];
    }
    $result['total'] = $db->query("SELECT COUNT(*) FROM songs")->fetchColumn();
    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ── IMPORT ────────────────────────────────────────────────────────
$db = getDB($DB_FILE);
$results = [];

foreach ($COLLECTIONS as $col) {
    if ($collection !== 'all' && $collection !== $col['source_id']) continue;

    $dir = $col['dir'];
    if (!is_dir($dir)) {
        $results[] = ['collection' => $col['name'], 'error' => "Không tìm thấy thư mục: $dir"];
        continue;
    }

    $files   = glob($dir . '*.txt');
    $songs   = [];
    $saved   = 0;
    $failed  = 0;
    $errors  = [];

    foreach ($files as $file) {
        $song = parseSongFile($file);
        if (!$song || !$song['title']) {
            $failed++;
            $errors[] = basename($file) . ': parse failed';
            continue;
        }

        // Thêm info collection vào song
        $song['source_id']  = $col['source_id'];
        $song['collection'] = $col['name'];
        $song['cat_top']    = $col['cat_top'];
        $song['slug']       = $col['source_id'] . '-' . $song['number'];

        $songs[] = $song;

        try {
            saveSong($db, $song, $col);
            $saved++;
        } catch (Exception $e) {
            $failed++;
            $errors[] = basename($file) . ': ' . $e->getMessage();
        }
    }

    // Sắp xếp theo số bài
    usort($songs, fn($a, $b) => $a['number'] <=> $b['number']);

    // Lưu JSON ra file (mỗi collection 1 file)
    $jsonOut = [
        'collection' => $col['name'],
        'source_id'  => $col['source_id'],
        'count'      => count($songs),
        'exported_at'=> date('Y-m-d H:i:s'),
        'songs'      => $songs,
    ];

    $outFile = $OUTPUT_DIR . $col['source_id'] . '.json';
    file_put_contents($outFile, json_encode($jsonOut, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    $results[] = [
        'collection' => $col['name'],
        'source_id'  => $col['source_id'],
        'files'      => count($files),
        'saved'      => $saved,
        'failed'     => $failed,
        'json_file'  => 'data/songs-json/' . $col['source_id'] . '.json',
        'errors'     => array_slice($errors, 0, 10),
        'sample'     => count($songs) > 0 ? [
            'num'      => $songs[0]['number'],
            'title'    => $songs[0]['title'],
            'author'   => $songs[0]['author'],
            'sections' => count($songs[0]['sections']),
            'preview'  => $songs[0]['sections'][0] ?? null,
        ] : null,
    ];
}

echo json_encode([
    'ok'         => true,
    'imported_at'=> date('Y-m-d H:i:s'),
    'results'    => $results,
    'db'         => $DB_FILE,
    'json_dir'   => $OUTPUT_DIR,
    'total_saved'=> array_sum(array_column($results, 'saved')),
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
