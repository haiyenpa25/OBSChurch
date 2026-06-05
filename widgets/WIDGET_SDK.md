# OBSChurch — Widget SDK

> **Phiên bản:** 1.0.0 &nbsp;·&nbsp; **Cập nhật:** 2026-06 &nbsp;·&nbsp; **Tác giả:** OBSChurch Team

---

## Mục lục

1. [Widget là gì?](#1-widget-là-gì)
2. [Cấu trúc thư mục](#2-cấu-trúc-thư-mục)
3. [File `widget.json` — Manifest](#3-file-widgetjson--manifest)
   - 3.1 [Các trường cơ bản](#31-các-trường-cơ-bản)
   - 3.2 [Cấu hình kích thước](#32-cấu-hình-kích-thước)
   - 3.3 [Khai báo Props](#33-khai-báo-props)
   - 3.4 [Tất cả loại Prop](#34-tất-cả-loại-prop)
4. [File `template.html` — Giao diện](#4-file-templatehtml--giao-diện)
   - 4.1 [Cấu trúc cơ bản](#41-cấu-trúc-cơ-bản)
   - 4.2 [Nhận Props](#42-nhận-props)
   - 4.3 [CSS với CSS Variables](#43-css-với-css-variables)
   - 4.4 [Tính năng live reload](#44-tính-năng-live-reload)
5. [Quy tắc & Best Practices](#5-quy-tắc--best-practices)
6. [Ví dụ mẫu từng bước](#6-ví-dụ-mẫu-từng-bước)
   - 6.1 [Ví dụ 1 — Đơn giản: Khung Thông Báo](#61-ví-dụ-1--đơn-giản-khung-thông-báo)
   - 6.2 [Ví dụ 2 — Trung bình: Đồng Hồ Đếm Ngược](#62-ví-dụ-2--trung-bình-đồng-hồ-đếm-ngược)
   - 6.3 [Ví dụ 3 — Nâng cao: Fetch dữ liệu từ API](#63-ví-dụ-3--nâng-cao-fetch-dữ-liệu-từ-api)
7. [Cách thêm widget vào Builder](#7-cách-thêm-widget-vào-builder)
8. [Widget có sẵn trong hệ thống](#8-widget-có-sẵn-trong-hệ-thống)
9. [Câu hỏi thường gặp (FAQ)](#9-câu-hỏi-thường-gặp-faq)
10. [Tham chiếu nhanh (Cheatsheet)](#10-tham-chiếu-nhanh-cheatsheet)

---

## 1. Widget là gì?

Widget là một **thành phần giao diện độc lập** chạy bên trong Layout Builder và Overlay của OBSChurch.

```
┌─────────────────────────────────────────────────────────────────┐
│  Layout Builder (builder.html)                                  │
│                                                                 │
│  ┌──────────────────────────┐     ┌─────────────────────────┐  │
│  │  Widget Library          │     │  Canvas (16:9 Preview)  │  │
│  │  ┌────┐ ┌────┐ ┌────┐   │     │                         │  │
│  │  │ ⏱  │ │ 📖 │ │ 🖼 │   │ ──► │  [ Widget trên canvas] │  │
│  │  └────┘ └────┘ └────┘   │     │     kéo / resize        │  │
│  └──────────────────────────┘     └─────────────────────────┘  │
│                                              │                  │
│                                              ▼                  │
│                                    [ Props Form tự sinh ]       │
└─────────────────────────────────────────────────────────────────┘
                                              │
                                              ▼ Sync OBS
┌─────────────────────────────────────────────────────────────────┐
│  OBS Overlay (Browser Source)                                   │
│  Nhận props qua WebSocket → render template.html                │
└─────────────────────────────────────────────────────────────────┘
```

Mỗi widget có thể:
- ✅ Kéo thả tự do trên canvas
- ✅ Thay đổi kích thước (resize) bằng tay hoặc slider
- ✅ Cấu hình qua form props tự động sinh ra
- ✅ Hiển thị real-time trong OBS qua Browser Source
- ✅ Chứa logic JavaScript (đồng hồ, fetch API, animation, ...)
- ✅ Sử dụng Google Fonts và thư viện CSS bên ngoài

---

## 2. Cấu Trúc Thư Mục

```
OBSChurch/
└── widgets/                         ← Thư mục gốc chứa tất cả widget
    ├── WIDGET_SDK.md                ← File này
    │
    ├── countdown/                   ← Mỗi widget là 1 thư mục
    │   ├── widget.json              ← BẮT BUỘC: Manifest
    │   ├── template.html            ← BẮT BUỘC: Giao diện
    │   └── preview.png              ← Tùy chọn: Ảnh thumbnail (320×180px)
    │
    ├── my-custom-widget/
    │   ├── widget.json
    │   └── template.html
    │
    └── ...
```

### Quy tắc đặt tên thư mục

| ✅ Đúng | ❌ Sai |
|---------|--------|
| `countdown` | `Count Down` |
| `my-widget` | `my_widget` |
| `church-logo-v2` | `ChurchLogo` |
| `live-clock` | `live clock` |

> **Chỉ dùng:** chữ thường (`a-z`), số (`0-9`), dấu gạch ngang (`-`)

---

## 3. File `widget.json` — Manifest

Đây là file khai báo **metadata** và **props** của widget. Builder đọc file này để:
- Hiển thị tên, icon, mô tả trong Widget Library
- Tự động sinh form chỉnh sửa props
- Biết kích thước mặc định khi thêm vào canvas

### 3.1 Các trường cơ bản

```json
{
  "id":          "my-widget",
  "name":        "Tên Hiển Thị Widget",
  "icon":        "🎯",
  "version":     "1.0.0",
  "author":      "Tên của bạn",
  "category":    "text",
  "description": "Mô tả ngắn gọn (1-2 câu)",
  "tags":        ["keyword1", "keyword2"]
}
```

| Trường | Kiểu | Bắt buộc | Mô tả |
|--------|------|----------|-------|
| `id` | string | ✅ | ID duy nhất, trùng tên thư mục |
| `name` | string | ✅ | Tên hiển thị trong Library |
| `icon` | string | ✅ | Emoji icon (1 ký tự) |
| `version` | string | ✅ | Theo format `major.minor.patch` |
| `author` | string | ❌ | Tên tác giả |
| `category` | string | ✅ | Xem bảng category bên dưới |
| `description` | string | ❌ | Tooltip trong Library |
| `tags` | string[] | ❌ | Dùng để tìm kiếm |

**Các `category` hợp lệ:**

| Category | Ý nghĩa |
|----------|---------|
| `timing` | Liên quan đến thời gian (đồng hồ, đếm ngược) |
| `text` | Văn bản, lower third, thông báo |
| `media` | Ảnh, logo, video |
| `data` | Fetch dữ liệu từ API, hiển thị thống kê |
| `interactive` | Có tương tác (poll, Q&A) |
| `effect` | Hiệu ứng, particle, animation |

### 3.2 Cấu hình kích thước

```json
{
  "defaultSize": { "w": 30, "h": 20 },
  "minSize":     { "w": 10, "h": 8  },
  "resizable":   true,
  "lockAR":      false
}
```

| Trường | Mô tả | Đơn vị |
|--------|-------|--------|
| `defaultSize.w` | Chiều rộng mặc định | % màn hình (0–100) |
| `defaultSize.h` | Chiều cao mặc định | % màn hình (0–100) |
| `minSize.w` | Chiều rộng tối thiểu | % màn hình |
| `minSize.h` | Chiều cao tối thiểu | % màn hình |
| `resizable` | Cho phép kéo resize? | boolean |
| `lockAR` | Giữ tỷ lệ khung hình? | boolean |

> **Ví dụ:** `"defaultSize": { "w": 25, "h": 18 }` → Widget xuất hiện chiếm 25% chiều rộng và 18% chiều cao của màn hình 1920×1080 (tức là 480×194 px).

### 3.3 Khai báo Props

```json
{
  "props": [
    {
      "key":     "myProp",
      "label":   "Tên hiển thị trong form",
      "type":    "text",
      "default": "Giá trị mặc định"
    }
  ]
}
```

| Trường | Bắt buộc | Mô tả |
|--------|----------|-------|
| `key` | ✅ | Tên biến (camelCase, không dấu) |
| `label` | ✅ | Nhãn hiển thị trong form |
| `type` | ✅ | Loại input (xem mục 3.4) |
| `default` | ✅ | Giá trị mặc định |

### 3.4 Tất cả loại Prop

#### `"text"` — Input văn bản 1 dòng
```json
{ "key": "title", "label": "Tiêu đề", "type": "text", "default": "Xin chào!" }
```
*Dùng cho:* Tên bài hát, tiêu đề, nhãn ngắn

---

#### `"textarea"` — Văn bản nhiều dòng
```json
{ "key": "verse", "label": "Câu Kinh Thánh", "type": "textarea", "default": "Vì Đức Chúa Trời yêu thương..." }
```
*Dùng cho:* Nội dung dài, Kinh Thánh, thông báo nhiều dòng

---

#### `"number"` — Số
```json
{ "key": "itemCount", "label": "Số mục", "type": "number", "default": 3 }
```
*Dùng cho:* Số lượng, offset, delay

---

#### `"range"` — Thanh trượt (Slider)
```json
{
  "key":     "fontSize",
  "label":   "Cỡ chữ",
  "type":    "range",
  "min":     10,
  "max":     80,
  "step":    2,
  "default": 24
}
```
*Dùng cho:* Font size, opacity, blur, border width, speed
> `min`, `max`, `step` là bắt buộc khi dùng `"range"`

---

#### `"color"` — Bộ chọn màu
```json
{ "key": "accentColor", "label": "Màu nhấn", "type": "color", "default": "#a855f7" }
```
*Dùng cho:* Màu chữ, màu nền, màu viền
> Giá trị luôn là hex 6 ký tự: `#rrggbb`

---

#### `"boolean"` — Bật/Tắt (Toggle)
```json
{ "key": "showDate", "label": "Hiện ngày tháng", "type": "boolean", "default": true }
```
*Dùng cho:* Bật/tắt tính năng
> Giá trị là `true` hoặc `false`

---

#### `"select"` — Menu thả xuống
```json
{
  "key":     "style",
  "label":   "Kiểu hiển thị",
  "type":    "select",
  "options": ["digital", "analog", "minimal"],
  "default": "digital"
}
```
*Dùng cho:* Chọn theme, chọn font, chọn kiểu layout
> `options` là bắt buộc khi dùng `"select"`

---

#### `"time"` — Chọn giờ
```json
{ "key": "targetTime", "label": "Giờ bắt đầu", "type": "time", "default": "09:00" }
```
*Dùng cho:* Giờ bắt đầu buổi lễ, giờ countdown
> Giá trị format `"HH:MM"` (24h)

---

#### `"url"` — Đường dẫn URL
```json
{ "key": "imageUrl", "label": "URL hình ảnh", "type": "url", "default": "" }
```
*Dùng cho:* Link ảnh online, link API

---

#### `"image"` — Upload file ảnh
```json
{ "key": "logo", "label": "Logo hội thánh", "type": "image", "default": "" }
```
*Dùng cho:* Logo, banner, ảnh nền
> File được lưu tại `/widgets/assets/` và path được inject vào prop

---

## 4. File `template.html` — Giao diện

Đây là file hiển thị thực tế của widget. Bao gồm: `<style>`, HTML, và `<script>`.

### 4.1 Cấu trúc cơ bản

```html
<!-- template.html — Skeleton tối thiểu -->
<style>
  /* Reset */
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  /* Root element PHẢI fill 100% */
  .w-root {
    width: 100%;
    height: 100%;
    /* ... style của bạn ... */
  }
</style>

<!-- HTML của widget -->
<div class="w-root" id="w-root">
  <div id="w-content">Nội dung mặc định</div>
</div>

<script>
(function() {
  // 1. Nhận props
  const P = window.__WIDGET_PROPS || {
    // Giá trị fallback khi chưa có props (preview trong builder)
    myText: 'Mặc định',
    color: '#ffffff',
  };

  // 2. Áp dụng vào DOM
  document.getElementById('w-content').textContent = P.myText;
  document.getElementById('w-content').style.color = P.color;

  // 3. Logic live (nếu cần)
  // setInterval(() => { ... }, 1000);

})(); // ← Luôn dùng IIFE (tự gọi ngay)
</script>
```

### 4.2 Nhận Props

Props được inject vào `window.__WIDGET_PROPS` **trước khi** script chạy:

```javascript
// Cách nhận props AN TOÀN (có fallback)
const P = window.__WIDGET_PROPS || {
  title:       'Giá trị mặc định',
  color:       '#ffffff',
  fontSize:    24,
  showDate:    true,
  style:       'digital',
  targetTime:  '09:00',
};

// Truy cập từng prop:
console.log(P.title);      // "Giá trị mặc định" hoặc giá trị user nhập
console.log(P.fontSize);   // 24 (number, đã được parse)
console.log(P.showDate);   // true (boolean)
```

> ⚠️ **Quan trọng:** Luôn có giá trị fallback (sau dấu `||`). Khi widget preview trong builder mà chưa có props, `window.__WIDGET_PROPS` sẽ là `undefined`.

### 4.3 CSS với CSS Variables

Cách tốt nhất để áp dụng props màu sắc là dùng **CSS Custom Properties** (CSS Variables):

```html
<style>
  .w-root {
    width: 100%; height: 100%;
    /* Khai báo biến với giá trị mặc định */
    --color:  #ffffff;
    --accent: #a855f7;
    --fs:     24px;
    --bg:     rgba(10, 10, 20, 0.85);
  }

  .w-title {
    color: var(--color);        /* Dùng biến */
    font-size: var(--fs);
  }
  .w-accent { color: var(--accent); }
</style>

<script>
(function() {
  const P = window.__WIDGET_PROPS || { color: '#fff', accentColor: '#a855f7', fontSize: 24 };
  const root = document.getElementById('w-root');

  /* Cập nhật CSS Variables từ props */
  root.style.setProperty('--color',  P.color  || '#fff');
  root.style.setProperty('--accent', P.accentColor || '#a855f7');
  root.style.setProperty('--fs',     (P.fontSize || 24) + 'px');
})();
</script>
```

**Lợi ích:** Toàn bộ CSS tự cập nhật chỉ bằng cách set 1 biến.

#### Chuyển HEX + Opacity sang RGBA

Khi cần `bgColor` kết hợp `bgOpacity`:

```javascript
function hexToRgba(hex, alpha) {
  if (!hex || hex.length < 7) return `rgba(10,10,20,${alpha})`;
  const r = parseInt(hex.slice(1, 3), 16);
  const g = parseInt(hex.slice(3, 5), 16);
  const b = parseInt(hex.slice(5, 7), 16);
  return `rgba(${r},${g},${b},${alpha})`;
}

// Ví dụ: bgColor="#0a0a14" + bgOpacity=85 → "rgba(10,10,20,0.85)"
root.style.setProperty('--bg', hexToRgba(P.bgColor, (P.bgOpacity || 85) / 100));
```

### 4.4 Tính năng live reload

Khi user thay đổi prop trong Builder, widget có thể tự cập nhật mà không cần reload:

```javascript
(function() {
  const P = window.__WIDGET_PROPS || { title: 'Mặc định', color: '#fff' };

  function applyProps(props) {
    document.getElementById('w-title').textContent = props.title;
    document.getElementById('w-root').style.setProperty('--color', props.color);
  }

  // Áp dụng ngay lần đầu
  applyProps(P);

  // Export function để Builder gọi khi props thay đổi
  window.__widgetReload = function(newProps) {
    applyProps(newProps);
  };
})();
```

> Builder sẽ gọi `window.__widgetReload(newProps)` mỗi khi user thay đổi prop.

---

## 5. Quy tắc & Best Practices

### ✅ BẮT BUỘC

```html
<!-- 1. Root element phải fill 100% -->
<style>
  .w-root { width: 100%; height: 100%; }
</style>

<!-- 2. Luôn wrap script trong IIFE -->
<script>
(function() {
  // code của bạn ở đây
})();
</script>
```

### ✅ NÊN làm

```javascript
// 3. Luôn có fallback cho props
const P = window.__WIDGET_PROPS || { title: 'Default' };

// 4. Dùng CSS Variables thay vì inline style trực tiếp
root.style.setProperty('--color', P.color);

// 5. Dùng pad() cho số đồng hồ
function pad(n) { return String(n).padStart(2, '0'); }

// 6. Cleanup interval khi widget unmount (nếu cần)
const timer = setInterval(tick, 1000);
window.addEventListener('beforeunload', () => clearInterval(timer));
```

### ❌ TRÁNH

```javascript
// ❌ Không dùng position: fixed
.w-root { position: fixed; } /* Sẽ bay ra ngoài overlay */

// ❌ Không hardcode màu sắc — hãy dùng props
element.style.color = '#a855f7'; /* Không thể tùy chỉnh */

// ❌ Không dùng document.write()
document.write('<div>...');

// ❌ Không dùng alert() / confirm() / prompt()
alert('Xin chào'); /* Sẽ block OBS */

// ❌ Không dùng localStorage trong overlay context
localStorage.setItem('key', 'value'); /* Có thể bị chặn */
```

### 📦 Fonts & Thư viện

```html
<style>
  /* ✅ Dùng @import để load Google Fonts */
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&family=JetBrains+Mono:wght@700&display=swap');
</style>

<!-- ✅ Hoặc dùng <link> -->
<link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet">

<!-- ✅ Load thư viện JS từ CDN (nếu cần) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
```

---

## 6. Ví dụ mẫu từng bước

### 6.1 Ví dụ 1 — Đơn giản: Khung Thông Báo

Một widget hiển thị khung thông báo với tiêu đề và nội dung. Không có logic JS phức tạp.

**Bước 1:** Tạo thư mục `widgets/announcement-box/`

**Bước 2:** Tạo `widget.json`

```json
{
  "id":          "announcement-box",
  "name":        "Khung Thông Báo",
  "icon":        "📢",
  "version":     "1.0.0",
  "author":      "OBSChurch",
  "category":    "text",
  "description": "Hiển thị khung thông báo với tiêu đề và nội dung chính",
  "tags":        ["thông báo", "text", "announcement"],

  "defaultSize": { "w": 45, "h": 18 },
  "minSize":     { "w": 20, "h": 10 },
  "resizable":   true,
  "lockAR":      false,

  "props": [
    { "key": "icon",    "label": "Icon",         "type": "text",    "default": "📢" },
    { "key": "title",   "label": "Tiêu đề",      "type": "text",    "default": "THÔNG BÁO" },
    { "key": "body",    "label": "Nội dung",      "type": "textarea","default": "Nhóm tuần sau vào lúc 7 giờ tối thứ Sáu tại Hội Thánh." },
    { "key": "color",   "label": "Màu nhấn",      "type": "color",   "default": "#fbbf24" },
    { "key": "bgColor", "label": "Màu nền",       "type": "color",   "default": "#080810" },
    { "key": "bgAlpha", "label": "Độ mờ nền (%)", "type": "range",   "min": 0, "max": 100, "default": 90 },
    { "key": "fontSize","label": "Cỡ chữ nội dung","type":"range",   "min": 10,"max": 24,  "default": 14 }
  ]
}
```

**Bước 3:** Tạo `template.html`

```html
<!-- OBSChurch Widget: Announcement Box -->
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap');
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  .w-ann {
    width: 100%; height: 100%;
    display: flex; align-items: center;
    background: var(--bg);
    border-left: 4px solid var(--color);
    border-radius: 0 10px 10px 0;
    padding: 12px 16px;
    font-family: 'Inter', sans-serif;
    position: relative; overflow: hidden;
  }

  /* Glow effect ở viền trái */
  .w-ann::before {
    content: '';
    position: absolute; left: 0; top: 0; bottom: 0; width: 4px;
    background: var(--color);
    box-shadow: 0 0 12px var(--color), 0 0 24px var(--color);
  }

  .w-ann-icon { font-size: 28px; margin-right: 14px; flex-shrink: 0; }

  .w-ann-text { flex: 1; }

  .w-ann-title {
    font-size: 11px; font-weight: 800;
    color: var(--color);
    text-transform: uppercase; letter-spacing: 0.12em;
    margin-bottom: 5px; display: block;
  }

  .w-ann-body {
    font-size: var(--fs);
    color: rgba(255,255,255,0.9);
    line-height: 1.5;
  }
</style>

<div class="w-ann" id="w-root">
  <span class="w-ann-icon" id="w-icon">📢</span>
  <div class="w-ann-text">
    <span class="w-ann-title" id="w-title">THÔNG BÁO</span>
    <div class="w-ann-body" id="w-body">Nội dung thông báo ở đây.</div>
  </div>
</div>

<script>
(function() {
  const P = window.__WIDGET_PROPS || {
    icon: '📢', title: 'THÔNG BÁO',
    body: 'Nội dung thông báo ở đây.',
    color: '#fbbf24', bgColor: '#080810', bgAlpha: 90, fontSize: 14
  };

  function hexToRgba(hex, alpha) {
    if (!hex || hex.length < 7) return `rgba(8,8,16,${alpha})`;
    return `rgba(${parseInt(hex.slice(1,3),16)},${parseInt(hex.slice(3,5),16)},${parseInt(hex.slice(5,7),16)},${alpha})`;
  }

  const root = document.getElementById('w-root');
  root.style.setProperty('--color', P.color || '#fbbf24');
  root.style.setProperty('--bg',    hexToRgba(P.bgColor || '#080810', (P.bgAlpha ?? 90) / 100));
  root.style.setProperty('--fs',    (P.fontSize || 14) + 'px');

  document.getElementById('w-icon').textContent  = P.icon  || '📢';
  document.getElementById('w-title').textContent = P.title || 'THÔNG BÁO';
  document.getElementById('w-body').textContent  = P.body  || '';

  // Live reload support
  window.__widgetReload = function(newProps) {
    Object.assign(P, newProps);
    root.style.setProperty('--color', P.color);
    root.style.setProperty('--bg',    hexToRgba(P.bgColor, (P.bgAlpha ?? 90) / 100));
    root.style.setProperty('--fs',    (P.fontSize || 14) + 'px');
    document.getElementById('w-icon').textContent  = P.icon;
    document.getElementById('w-title').textContent = P.title;
    document.getElementById('w-body').textContent  = P.body;
  };
})();
</script>
```

**Kết quả:** Widget hiển thị khung thông báo với icon, tiêu đề màu vàng và nội dung trắng trên nền tối bán trong suốt.

---

### 6.2 Ví dụ 2 — Trung bình: Đồng Hồ Đếm Ngược

Widget có logic JavaScript chạy theo thời gian thực.

**`widget.json`** *(chỉ phần props)*:
```json
"props": [
  { "key": "label",       "label": "Nhãn",          "type": "text",    "default": "Bắt đầu sau" },
  { "key": "targetTime",  "label": "Giờ bắt đầu",   "type": "time",    "default": "09:00" },
  { "key": "color",       "label": "Màu số",         "type": "color",   "default": "#ffffff" },
  { "key": "accentColor", "label": "Màu dấu chấm",   "type": "color",   "default": "#a855f7" },
  { "key": "fontSize",    "label": "Cỡ chữ (px)",    "type": "range",   "min": 20, "max": 100, "default": 48 },
  { "key": "showSeconds", "label": "Hiện giây",       "type": "boolean", "default": true }
]
```

**`template.html`** *(phần script)*:
```html
<script>
(function() {
  const P = window.__WIDGET_PROPS || {
    label: 'Bắt đầu sau', targetTime: '09:00',
    color: '#fff', accentColor: '#a855f7',
    fontSize: 48, showSeconds: true
  };

  // Hàm tiện ích
  function pad(n) { return String(n).padStart(2, '0'); }

  // Tính thời gian còn lại (giây)
  function getRemaining() {
    const now = new Date();
    const [h, m] = (P.targetTime || '09:00').split(':').map(Number);
    const end = new Date(now);
    end.setHours(h, m, 0, 0);
    // Nếu giờ đã qua → tính cho ngày mai
    if (end <= now) end.setDate(end.getDate() + 1);
    return Math.max(0, Math.floor((end - now) / 1000));
  }

  // Render đồng hồ
  function tick() {
    const total = getRemaining();
    const hh = Math.floor(total / 3600);
    const mm = Math.floor((total % 3600) / 60);
    const ss = total % 60;

    const timeStr = P.showSeconds
      ? `${pad(hh)}:${pad(mm)}:${pad(ss)}`
      : `${pad(hh)}:${pad(mm)}`;

    document.getElementById('cwd-time').textContent = timeStr;
  }

  // Áp dụng style
  const root = document.getElementById('w-root');
  root.style.setProperty('--color',  P.color || '#fff');
  root.style.setProperty('--accent', P.accentColor || '#a855f7');
  root.style.setProperty('--fs',     (P.fontSize || 48) + 'px');
  document.getElementById('cwd-label').textContent = P.label || '';

  // Bắt đầu đếm
  tick();
  const timer = setInterval(tick, 1000);

  // Dọn dẹp khi unmount
  window.addEventListener('beforeunload', () => clearInterval(timer));

  // Live reload
  window.__widgetReload = function(newProps) {
    Object.assign(P, newProps);
    root.style.setProperty('--color',  P.color);
    root.style.setProperty('--accent', P.accentColor);
    root.style.setProperty('--fs',     P.fontSize + 'px');
    document.getElementById('cwd-label').textContent = P.label;
  };
})();
</script>
```

---

### 6.3 Ví dụ 3 — Nâng cao: Fetch dữ liệu từ API

Widget tự động lấy dữ liệu từ server và hiển thị.

```html
<script>
(function() {
  const P = window.__WIDGET_PROPS || {
    apiUrl:       'http://localhost:3000/api/current-song',
    refreshRate:  5,   // giây
    showProgress: true,
  };

  async function fetchAndRender() {
    try {
      const response = await fetch(P.apiUrl);
      if (!response.ok) throw new Error('API Error');
      const data = await response.json();

      document.getElementById('w-title').textContent   = data.title  || '—';
      document.getElementById('w-artist').textContent  = data.artist || '—';
      document.getElementById('w-album').textContent   = data.album  || '';

      if (P.showProgress && data.progress !== undefined) {
        document.getElementById('w-bar').style.width = data.progress + '%';
      }
    } catch (err) {
      console.warn('[Widget] Lỗi fetch:', err.message);
      document.getElementById('w-title').textContent = 'Đang kết nối...';
    }
  }

  // Fetch ngay lần đầu
  fetchAndRender();

  // Refresh theo tần suất
  const interval = setInterval(fetchAndRender, (P.refreshRate || 5) * 1000);
  window.addEventListener('beforeunload', () => clearInterval(interval));
})();
</script>
```

---

## 7. Cách thêm widget vào Builder

### Cách 1: Đặt thư mục vào `/widgets/` *(Khuyến nghị)*

```
Bước 1: Tạo thư mục
  widgets/tên-widget/
    ├── widget.json
    └── template.html

Bước 2: Restart server (nếu cần)
  Nhấn Ctrl+C → Chạy lại START-SERVER.ps1

Bước 3: Mở Builder
  http://localhost/OBSChurch/builder.html

Bước 4: Click "+ Widget" ở thanh công cụ trên
  → Widget Library mở ra
  → Widget của bạn xuất hiện trong danh sách
  → Click để thêm vào canvas
```

### Cách 2: Cấu trúc file ZIP *(để chia sẻ)*

```
tên-widget.zip
├── widget.json
└── template.html
```

Trong Builder → **"+ Widget"** → **"Import ZIP"** → chọn file.

---

## 8. Widget có sẵn trong hệ thống

| Icon | Tên | Thư mục | Category | Props nổi bật |
|------|-----|---------|----------|---------------|
| ⏱ | Đồng Hồ Đếm Ngược | `countdown/` | timing | targetTime, style (digital/blocks/minimal) |
| 🖼 | Khung Hình / Logo | `media-frame/` | media | imageUrl, fit, borderRadius |
| 📖 | Câu Kinh Thánh | `scripture-verse/` | text | verse, reference, style (classic/centered/quote/card) |
| 🕐 | Đồng Hồ Trực Tiếp | `live-clock/` | timing | style (digital/analog/minimal), format24h |

---

## 9. Câu hỏi thường gặp (FAQ)

**Q: Widget có thể chạy JavaScript thực sự không?**
> ✅ Có. Template chạy trong `<iframe>` của Browser Source — bạn có thể dùng bất kỳ Web API nào: `setInterval`, `fetch`, Canvas, WebGL, Web Audio, v.v.

**Q: Làm sao tải Google Fonts?**
> Thêm vào đầu `<style>` trong template.html:
> ```css
> @import url('https://fonts.googleapis.com/css2?family=Inter:wght@700&display=swap');
> ```

**Q: Widget có thể nhận dữ liệu real-time từ OBS không?**
> ✅ Overlay engine inject `window.__OBS_WS` (WebSocket connection). Bạn có thể lắng nghe events từ OBS qua WebSocket.

**Q: Làm sao hiện preview ảnh trong Widget Library?**
> Thêm file `preview.png` (320×180px) vào cùng thư mục với `widget.json`.

**Q: Có thể dùng nhiều file JS/CSS riêng không?**
> ❌ Hiện tại chỉ hỗ trợ 1 file `template.html` duy nhất. Hãy viết tất cả CSS và JS trong file đó.

**Q: Làm sao debug widget?**
> Mở `template.html` trực tiếp trong trình duyệt để test. Thêm `console.log()` bình thường — log xuất hiện trong DevTools của OBS (hoặc trình duyệt).

**Q: Widget có thể có animation không?**
> ✅ Hoàn toàn. Dùng CSS `@keyframes` hoặc `requestAnimationFrame` trong JavaScript bình thường.

```css
@keyframes fade-in {
  from { opacity: 0; transform: translateY(10px); }
  to   { opacity: 1; transform: translateY(0);    }
}
.w-root { animation: fade-in 0.5s ease; }
```

**Q: Tối đa bao nhiêu props?**
> Không giới hạn chính thức, nhưng khuyến nghị **tối đa 12–15 props** để form không bị quá dài.

---

## 10. Tham chiếu nhanh (Cheatsheet)

### widget.json — Template đầy đủ

```json
{
  "id":          "widget-id",
  "name":        "Tên Widget",
  "icon":        "🎯",
  "version":     "1.0.0",
  "author":      "Tên bạn",
  "category":    "text",
  "description": "Mô tả ngắn",
  "tags":        ["tag1", "tag2"],

  "defaultSize": { "w": 30, "h": 20 },
  "minSize":     { "w": 10, "h":  8 },
  "resizable":   true,
  "lockAR":      false,

  "props": [
    { "key": "text",   "label": "Văn bản",    "type": "text",    "default": "Xin chào" },
    { "key": "long",   "label": "Dài",        "type": "textarea","default": "..." },
    { "key": "num",    "label": "Số",         "type": "number",  "default": 5 },
    { "key": "slide",  "label": "Slider",     "type": "range",   "min": 0, "max": 100, "step": 1, "default": 50 },
    { "key": "clr",    "label": "Màu",        "type": "color",   "default": "#a855f7" },
    { "key": "flag",   "label": "Bật/Tắt",   "type": "boolean", "default": true },
    { "key": "opt",    "label": "Chọn",       "type": "select",  "options": ["a","b","c"], "default": "a" },
    { "key": "t",      "label": "Giờ",        "type": "time",    "default": "09:00" },
    { "key": "link",   "label": "URL",        "type": "url",     "default": "" },
    { "key": "img",    "label": "Ảnh",        "type": "image",   "default": "" }
  ]
}
```

### template.html — Skeleton đầy đủ

```html
<!-- OBSChurch Widget: [Tên Widget] -->
<!-- Author: [Tên bạn] | Version: 1.0.0 -->
<style>
  @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap');
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  .w-root {
    width: 100%; height: 100%;
    font-family: 'Inter', sans-serif;
    /* CSS Variables — cập nhật từ JS */
    --color:  #fff;
    --accent: #a855f7;
    --fs:     16px;
    --bg:     rgba(10, 10, 20, 0.85);
  }
</style>

<div class="w-root" id="w-root">
  <!-- HTML của bạn -->
</div>

<script>
(function() {
  /* 1. Props */
  const P = window.__WIDGET_PROPS || {
    color: '#fff', accentColor: '#a855f7', fontSize: 16,
  };

  /* 2. Utils */
  function pad(n) { return String(n).padStart(2, '0'); }
  function hexToRgba(hex, alpha) {
    if (!hex || hex.length < 7) return `rgba(10,10,20,${alpha})`;
    return `rgba(${parseInt(hex.slice(1,3),16)},${parseInt(hex.slice(3,5),16)},${parseInt(hex.slice(5,7),16)},${alpha})`;
  }

  /* 3. Áp dụng CSS variables */
  const root = document.getElementById('w-root');
  function applyProps(props) {
    root.style.setProperty('--color',  props.color  || '#fff');
    root.style.setProperty('--accent', props.accentColor || '#a855f7');
    root.style.setProperty('--fs',     (props.fontSize || 16) + 'px');
    /* Áp dụng nội dung ... */
  }
  applyProps(P);

  /* 4. Live reload */
  window.__widgetReload = function(newProps) {
    Object.assign(P, newProps);
    applyProps(P);
  };

  /* 5. Logic live (nếu cần) */
  // const timer = setInterval(tick, 1000);
  // window.addEventListener('beforeunload', () => clearInterval(timer));
})();
</script>
```

---

*Cần hỗ trợ? Đọc các widget có sẵn trong `/widgets/` để tham khảo thêm ví dụ.*
