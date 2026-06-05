# 📋 ENCODING RULES — OBSChurch Project

## Rule bất thành văn: **TẤT CẢ dùng UTF-8, không ngoại lệ**

---

### ✅ PHP Files
```php
<?php
// Dòng đầu tiên LUÔN có:
header('Content-Type: application/json; charset=utf-8');

// Khi fetch HTTP bên ngoài:
$ctx = stream_context_create(['http' => [
    'header' => "Accept-Charset: utf-8\r\nAccept-Language: vi-VN,vi;q=0.9\r\n",
]]);
$raw = file_get_contents($url, false, $ctx);

// Kiểm tra và ép UTF-8:
if ($raw && !mb_check_encoding($raw, 'UTF-8')) {
    $raw = mb_convert_encoding($raw, 'UTF-8', 'auto');
}

// HTML entity decode luôn dùng:
html_entity_decode($str, ENT_QUOTES | ENT_HTML5, 'UTF-8')

// strip_tags xong luôn decode:
$text = html_entity_decode(strip_tags($raw), ENT_QUOTES | ENT_HTML5, 'UTF-8');

// json_encode luôn có:
json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)

// PDO SQLite:
$db = new PDO("sqlite:$file");
$db->exec("PRAGMA encoding = 'UTF-8'");

// mb_ functions thay vì str_ functions:
mb_strlen($str, 'UTF-8')     // thay cho strlen()
mb_substr($str, 0, 10, 'UTF-8') // thay cho substr()
mb_strtolower($str, 'UTF-8') // thay cho strtolower()
```

### ✅ HTML Files
```html
<!-- Dòng đầu tiên trong <head>: -->
<meta charset="UTF-8">
```

### ✅ JavaScript Files
```js
// fetch API response:
const r = await fetch(url);
const text = await r.text(); // UTF-8 by default

// JSON stringify:
JSON.stringify(obj) // UTF-8 by default in browsers
```

### ✅ SQLite Database
```sql
-- Khi tạo DB:
PRAGMA encoding = 'UTF-8';
-- TEXT fields luôn lưu UTF-8 string (PHP PDO xử lý tự động)
```

### ✅ Git / Files
```gitattributes
# .gitattributes
* text=auto eol=lf
*.php text eol=lf
*.html text eol=lf
*.js text eol=lf
*.json text eol=lf
*.css text eol=lf
```

---

## Vấn đề hay gặp & Cách fix

| Triệu chứng | Nguyên nhân | Fix |
|---|---|---|
| `C? ?i Xin Vua` | PHP đọc sai encoding | Thêm `mb_convert_encoding($raw, 'UTF-8', 'auto')` |
| `Th? Phu?ng` | HTML entity không decode | Dùng `html_entity_decode(..., ENT_HTML5, 'UTF-8')` |
| Chuỗi JSON escaped `\u1ef1` | Thiếu flag | Thêm `JSON_UNESCAPED_UNICODE` |
| PowerShell hiển thị vỡ | Console CP | `[Console]::OutputEncoding = [Text.Encoding]::UTF8` — chỉ là hiển thị, data vẫn đúng |

---

## Áp dụng cho: thanhca.httlvn.org scraper

Site HTTLVN.org **gửi HTML dạng UTF-8** nhưng cần:
1. Không dùng `curl` qua PowerShell (console encoding vỡ)  
2. Dùng PHP `file_get_contents` với header đúng  
3. Kiểm tra `mb_check_encoding` trước khi xử lý  
4. Dùng `preg_match` với flag `/u` cho Unicode regex  

---

*Tạo: 2026-06-05 | Dự án: OBSChurch | Tác giả: Antigravity*
