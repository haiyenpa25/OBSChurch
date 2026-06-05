<?php
/**
 * Test: Tải 1 bài từ thanhca.httlvn.org và kiểm tra UTF-8
 * Rule: TẤT CẢ UTF-8, json_encode với JSON_UNESCAPED_UNICODE
 */
header('Content-Type: text/html; charset=utf-8');
?><!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<title>Test 1 Bài — HTTLVN</title>
<style>
body{font-family:'Segoe UI',sans-serif;background:#07070f;color:#f0f0ff;margin:0;padding:20px;line-height:1.6}
.card{background:#0f0f1e;border:1px solid rgba(255,255,255,.08);border-radius:8px;padding:20px;margin-bottom:16px;max-width:700px}
.ok{color:#4edea3} .bad{color:#ef4444} .info{color:#4d8eff} .warn{color:#fbbf24}
h1{font-size:22px;font-weight:800;margin-bottom:4px;letter-spacing:-0.02em}
h2{font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#555570;margin-bottom:12px}
.num{display:inline-block;width:32px;height:22px;border-radius:4px;background:#1a1a34;font-size:10px;font-weight:700;text-align:center;line-height:22px;color:#a855f7;margin-right:8px}
.verse{margin-bottom:14px}
.verse-lbl{font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:#555570;margin-bottom:4px}
.line{padding:6px 12px;border-left:2px solid rgba(168,85,247,.3);margin-bottom:2px;font-size:15px;font-family:'Georgia',serif}
.check{display:flex;align-items:center;gap:8px;margin:4px 0;font-size:13px}
.tag{padding:2px 8px;border-radius:3px;font-size:10px;font-weight:700;background:rgba(168,85,247,.15);color:#a855f7}
pre{background:#080818;border:1px solid rgba(255,255,255,.05);padding:12px;border-radius:5px;font-size:11px;overflow-x:auto;white-space:pre-wrap}
.sep{height:1px;background:rgba(255,255,255,.05);margin:8px 0}
</style>
</head>
<body>
<?php
$song_id = intval($_GET['id'] ?? 1);

// ── FETCH from HTTLVN ─────────────────────────────────────────────
$url = "https://thanhca.httlvn.org/thanh-ca-{$song_id}/";
// Get listing to find slug first
$listCtx = stream_context_create(['http' => [
    'timeout' => 20,
    'header'  => implode("\r\n", [
        "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)",
        "Accept: text/html",
        "Accept-Language: vi-VN,vi;q=0.9",
    ]),
    'ignore_errors' => true, 'follow_location' => 1,
]]);

// Use redirect to resolve actual URL
$redirectUrl = "https://thanhca.httlvn.org/Home/Go?id={$song_id}&type_book=thanh-ca";
$html = file_get_contents($redirectUrl, false, $listCtx);

// ── Check encoding ────────────────────────────────────────────────
$isUTF8 = mb_check_encoding($html ?: '', 'UTF-8');
if ($html && !$isUTF8) {
    $html = mb_convert_encoding($html, 'UTF-8', 'auto');
}

$songUrl = $redirectUrl;
// Try to get real URL from redirect chain
if (preg_match('#(/thanh-ca-\d+/[^"\'?\s]+)#i', $html ?? '', $m)) {
    $songSlug = $m[1];
    $songUrl  = 'https://thanhca.httlvn.org' . $songSlug;
    // Fetch the actual song page
    $html = file_get_contents($songUrl, false, $listCtx);
    if ($html && !mb_check_encoding($html, 'UTF-8')) {
        $html = mb_convert_encoding($html, 'UTF-8', 'auto');
    }
} else {
    $songSlug = "bai-{$song_id}";
}

$htmlSize  = strlen($html ?? '');
$isUTF8OK  = mb_check_encoding($html ?? '', 'UTF-8');

// ── Extract title ─────────────────────────────────────────────────
$title = '';
if (preg_match('#<title>([^<]+)</title>#i', $html ?? '', $m)) {
    $raw = html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (preg_match('/Thánh Ca \d+:\s*(.+?)(?:\s*-\s*Thánh Ca Tin Lành|$)/ui', $raw, $tm)) {
        $title = trim($tm[1]);
    }
}

// ── Extract cat ───────────────────────────────────────────────────
$catTop = ''; $catSub = '';
if (preg_match_all('#cat_top=([^"&]+)"[^>]*>([^<]+)<#u', $html ?? '', $cats)) {
    $catTop = html_entity_decode(trim($cats[2][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
if (preg_match_all('#cat_sub=([^"&]+)"[^>]*>([^<]+)<#u', $html ?? '', $csubs)) {
    $catSub = html_entity_decode(trim($csubs[2][0] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ── Extract lyrics from div#lyric-content ─────────────────────────
// Bước 5: Lấy lời từ div#lyric-content (strpos approach, UTF-8)
$rawLines = []; $sections = [];
$lyricPos = strpos($html, 'id="lyric-content"');
$lyricFound = $lyricPos !== false;

if ($lyricFound) {
    $startPos = strpos($html, '>', $lyricPos) + 1;
    $chunk = substr($html, $startPos, 6000);
    $chunk = preg_replace('#</p>\s*<p[^>]*>#si', "\n", $chunk);
    $chunk = preg_replace('#<p[^>]*>#si', '', $chunk);
    $chunk = preg_replace('#</p>#si', "\n", $chunk);
    $chunk = preg_replace('#<br\s*/?>#si', "\n", $chunk);
    $text = strip_tags($chunk);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    foreach (explode("\n", $text) as $line) {
        $line = trim($line);
        if (!$line) continue;
        // Dừng khi gặp nav/UI junk
        if (preg_match('/^(Thánh ca\s*$|KTĐ|Kinh Thánh Đối Đáp|Mới truy cập|Cỡ chữ|#\d{3}\.|Fullscreen)/ui', $line)) break;
        if (preg_match('/^[←→\s\d]+$/', $line)) continue;
        if (mb_strlen($line, 'UTF-8') < 2) continue;
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

// ── SAVE TO DB ────────────────────────────────────────────────────
$dbPath = __DIR__ . '/../data/thanh-ca.db';
$saved  = false; $dbError = '';
if ($lyricFound && !empty($sections)) {
    try {
        $db = new PDO("sqlite:$dbPath");
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec("PRAGMA encoding='UTF-8'; PRAGMA journal_mode=WAL;");
        $db->exec("CREATE TABLE IF NOT EXISTS songs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            slug TEXT UNIQUE NOT NULL, number INTEGER DEFAULT 0,
            title TEXT NOT NULL DEFAULT '', author TEXT DEFAULT '',
            cat_top TEXT DEFAULT '', cat_sub TEXT DEFAULT '',
            collection TEXT DEFAULT '', source_id TEXT DEFAULT '',
            source_url TEXT DEFAULT '', lyrics_raw TEXT DEFAULT '',
            lyrics_json TEXT DEFAULT '[]', sections_json TEXT DEFAULT '[]',
            saved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        $dbSlug = "httlvn-{$song_id}";
        $stmt = $db->prepare("INSERT INTO songs (slug,number,title,cat_top,cat_sub,collection,source_id,source_url,lyrics_raw,lyrics_json,sections_json)
            VALUES (:slug,:num,:title,:cat_top,:cat_sub,'httl-vn','httlvn',:src,:raw,:json,:secs)
            ON CONFLICT(slug) DO UPDATE SET title=excluded.title,cat_top=excluded.cat_top,cat_sub=excluded.cat_sub,
            lyrics_raw=excluded.lyrics_raw,lyrics_json=excluded.lyrics_json,sections_json=excluded.sections_json,updated_at=CURRENT_TIMESTAMP");
        $stmt->execute([
            ':slug' => $dbSlug, ':num' => $song_id, ':title' => $title,
            ':cat_top' => $catTop, ':cat_sub' => $catSub,
            ':src' => $songUrl, ':raw' => implode("\n", $rawLines),
            ':json' => json_encode($rawLines, JSON_UNESCAPED_UNICODE),
            ':secs' => json_encode($sections, JSON_UNESCAPED_UNICODE),
        ]);
        $saved = true;
    } catch (Exception $e) { $dbError = $e->getMessage(); }
}

// ── DISPLAY ───────────────────────────────────────────────────────
$nav = "<p style='margin-bottom:12px;font-size:12px;color:#555570'>";
if ($song_id > 1) $nav .= "<a href='?id=".($song_id-1)."' style='color:#4d8eff;margin-right:10px'>← Bài ".($song_id-1)."</a>";
$nav .= "<a href='?id=".($song_id+1)."' style='color:#4d8eff'>Bài ".($song_id+1)." →</a></p>";

echo $nav;
?>

<div class="card">
  <h2>🔍 Kết Quả Tải — Bài <?= $song_id ?></h2>
  
  <div class="check">
    <span><?= $htmlSize > 10000 ? '✅' : '❌' ?></span>
    <span>HTML size: <strong><?= number_format($htmlSize) ?> bytes</strong> <?= $htmlSize < 5000 ? '(quá nhỏ, có thể lỗi)' : '' ?></span>
  </div>
  <div class="check">
    <span><?= $isUTF8OK ? '✅' : '⚠️' ?></span>
    <span>Encoding: <strong class="<?= $isUTF8OK ? 'ok' : 'bad' ?>"><?= $isUTF8OK ? 'UTF-8 ✓' : 'Cần convert' ?></strong></span>
  </div>
  <div class="check">
    <span><?= $lyricFound ? '✅' : '❌' ?></span>
    <span>Lyric block: <strong class="<?= $lyricFound ? 'ok' : 'bad' ?>"><?= $lyricFound ? 'Tìm thấy div#lyric-content' : 'KHÔNG TÌM THẤY' ?></strong></span>
  </div>
  <div class="check">
    <span><?= count($sections) ? '✅' : '⚠️' ?></span>
    <span>Sections: <strong class="<?= count($sections) ? 'ok' : 'warn' ?>"><?= count($sections) ?> câu</strong> (<?= count($rawLines) ?> dòng raw)</span>
  </div>
  <div class="check">
    <span><?= $saved ? '✅' : ($dbError ? '❌' : '⚠️') ?></span>
    <span>Lưu DB: <strong class="<?= $saved ? 'ok' : ($dbError ? 'bad' : 'warn') ?>"><?= $saved ? 'Đã lưu vào SQLite' : ($dbError ?: 'Không lưu (thiếu lời)') ?></strong></span>
  </div>
</div>

<?php if ($title): ?>
<div class="card">
  <p style="font-size:11px;font-weight:600;color:#a855f7;letter-spacing:.06em;margin-bottom:4px">THÁNH CA <?= $song_id ?></p>
  <h1><?= htmlspecialchars($title) ?></h1>
  <?php if ($catTop): ?>
  <div style="margin-top:6px;display:flex;gap:6px;align-items:center">
    <span class="tag"><?= htmlspecialchars($catTop) ?></span>
    <?php if ($catSub): ?><span style="font-size:10px;color:#555570"><?= htmlspecialchars($catSub) ?></span><?php endif; ?>
  </div>
  <?php endif; ?>
  <div class="sep" style="margin-top:12px"></div>
  
  <?php foreach ($sections as $sec): ?>
  <div class="verse">
    <div class="verse-lbl"><?= htmlspecialchars($sec['label']) ?></div>
    <?php foreach ($sec['lines'] as $line): ?>
    <div class="line"><?= htmlspecialchars($line) ?></div>
    <?php endforeach; ?>
  </div>
  <?php endforeach; ?>
</div>
<?php elseif (!$lyricFound): ?>
<div class="card">
  <p class="bad">❌ Không tìm thấy lời bài hát. URL: <?= htmlspecialchars($songUrl) ?></p>
  <p style="font-size:11px;color:#555570;margin-top:8px">HTML snippet đầu tiên:</p>
  <pre><?= htmlspecialchars(substr($html ?? '', 0, 500)) ?></pre>
</div>
<?php endif; ?>

<div class="card" style="font-size:11px;color:#555570">
  <h2>🔗 Test Bài Khác</h2>
  <div style="display:flex;gap:8px;flex-wrap:wrap">
    <?php for($i=1;$i<=10;$i++): ?>
    <a href="?id=<?= $i ?>" style="padding:4px 10px;border:1px solid rgba(255,255,255,.1);border-radius:4px;color:<?= $i==$song_id?'#a855f7':'#a0a0c0'?>;text-decoration:none">Bài <?= $i ?></a>
    <?php endfor; ?>
    <a href="?id=50" style="padding:4px 10px;border:1px solid rgba(255,255,255,.1);border-radius:4px;color:#a0a0c0;text-decoration:none">Bài 50</a>
    <a href="?id=100" style="padding:4px 10px;border:1px solid rgba(255,255,255,.1);border-radius:4px;color:#a0a0c0;text-decoration:none">Bài 100</a>
    <a href="?id=500" style="padding:4px 10px;border:1px solid rgba(255,255,255,.1);border-radius:4px;color:#a0a0c0;text-decoration:none">Bài 500</a>
    <a href="?id=903" style="padding:4px 10px;border:1px solid rgba(255,255,255,.1);border-radius:4px;color:#a0a0c0;text-decoration:none">Bài 903</a>
  </div>
</div>

<?= $nav ?>
</body>
</html>
