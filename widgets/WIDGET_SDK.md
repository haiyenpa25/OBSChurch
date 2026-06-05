# OBSChurch Widget SDK — Hướng Dẫn Viết Widget

> Phiên bản: 1.0 · Tác giả: OBSChurch Team

---

## Widget là gì?

Widget là một **thành phần giao diện tái sử dụng** có thể:
- Kéo thả trên canvas trong **Layout Builder**
- Chỉnh kích thước (resize)
- Cấu hình thông qua form tự động sinh ra từ `props`
- Hiển thị trực tiếp trong **Overlay** của OBS Studio

---

## Cấu Trúc Thư Mục

```
widgets/
└── tên-widget-của-bạn/
    ├── widget.json      ← BẮT BUỘC: Khai báo metadata và props
    ├── template.html    ← BẮT BUỘC: HTML + CSS + JS của widget
    └── preview.png      ← Tùy chọn: Ảnh thumbnail 320x180px
```

**Quy tắc đặt tên:** Chỉ dùng chữ thường, số, và dấu gạch ngang (`-`).
Ví dụ: `my-widget`, `countdown-timer`, `church-logo`

---

## File 1: `widget.json`

```json
{
  "id":          "my-widget",
  "name":        "Tên Widget Của Tôi",
  "icon":        "🎯",
  "version":     "1.0.0",
  "author":      "Tên Tác Giả",
  "category":    "text",
  "description": "Mô tả ngắn gọn về widget này làm gì",
  "tags":        ["tag1", "tag2"],

  "defaultSize": { "w": 30, "h": 20 },
  "minSize":     { "w": 10, "h": 8 },
  "resizable":   true,
  "lockAR":      false,

  "props": [
    {
      "key":     "myText",
      "label":   "Nội dung",
      "type":    "text",
      "default": "Xin chào!"
    }
  ]
}
```

### Tất Cả Loại `type` Cho Props

| Type | Form UI | Ví dụ Dùng |
|------|---------|-----------|
| `"text"` | Input 1 dòng | Tiêu đề, nhãn |
| `"textarea"` | Textarea nhiều dòng | Câu Kinh Thánh, thông báo |
| `"number"` | Input số | Số phút, offset |
| `"range"` | Thanh trượt (slider) | Font size, opacity, border |
| `"color"` | Color picker | Màu chữ, màu nền |
| `"boolean"` | Toggle On/Off | Bật/tắt tính năng |
| `"select"` | Dropdown menu | Chọn font, chọn kiểu |
| `"time"` | Chọn giờ (HH:MM) | Giờ bắt đầu, deadline |
| `"url"` | Input URL | Link ảnh, link website |
| `"image"` | Upload file ảnh | Logo, banner |

#### Ví dụ `range`:
```json
{
  "key": "fontSize", "label": "Cỡ chữ", "type": "range",
  "min": 10, "max": 80, "step": 2, "default": 24
}
```

#### Ví dụ `select`:
```json
{
  "key": "style", "label": "Kiểu", "type": "select",
  "options": ["modern", "classic", "minimal"],
  "default": "modern"
}
```

---

## File 2: `template.html`

```html
<!-- Đây là file HTML đầy đủ của widget -->
<!-- Sẽ được nhúng vào overlay khi hiển thị -->

<style>
  /* CSS của bạn ở đây */
  * { box-sizing: border-box; margin: 0; padding: 0; }

  .my-widget {
    width: 100%;       /* ← luôn dùng 100% để fill frame */
    height: 100%;      /* ← luôn dùng 100% để fill frame */
    display: flex;
    align-items: center;
    justify-content: center;
    /* ... style của bạn ... */
  }
</style>

<div class="my-widget" id="w-root">
  <!-- HTML của bạn -->
  <div id="w-content">Xin chào!</div>
</div>

<script>
(function() {
  // ── Props được inject qua window.__WIDGET_PROPS ──────────────
  const P = window.__WIDGET_PROPS || {
    // Giá trị mặc định (dùng khi preview trong builder)
    myText: 'Xin chào!',
    color: '#ffffff',
  };

  // ── Áp dụng props vào DOM ────────────────────────────────────
  const content = document.getElementById('w-content');
  content.textContent = P.myText;
  content.style.color = P.color;

  // ── Logic của widget (chạy mỗi giây, fetch data, v.v.) ──────
  // setInterval(..., 1000);

})();
</script>
```

### ⚠️ Quy Tắc Quan Trọng

1. **Luôn wrap trong IIFE**: `(function() { ... })();` để tránh xung đột biến
2. **Luôn dùng `window.__WIDGET_PROPS`** để lấy props, và có giá trị mặc định fallback
3. **`width: 100%; height: 100%`** — widget sẽ tự fill theo kích thước frame trên canvas
4. **Không dùng `position: fixed`** — sẽ gây lỗi trong overlay
5. **Có thể import Google Fonts** bằng `@import url(...)` trong CSS

---

## Ví Dụ Widget Đơn Giản: Thông Báo

**`widget.json`**:
```json
{
  "id": "announcement-box",
  "name": "Khung Thông Báo",
  "icon": "📢",
  "version": "1.0.0",
  "author": "Tôi",
  "category": "text",
  "description": "Hiển thị khung thông báo với tiêu đề và nội dung",
  "defaultSize": { "w": 40, "h": 20 },
  "minSize": { "w": 20, "h": 10 },
  "resizable": true,
  "props": [
    { "key": "title",   "label": "Tiêu đề",   "type": "text",    "default": "📢 THÔNG BÁO" },
    { "key": "body",    "label": "Nội dung",   "type": "textarea","default": "Nội dung thông báo ở đây" },
    { "key": "color",   "label": "Màu nhấn",   "type": "color",   "default": "#fbbf24" },
    { "key": "bgAlpha", "label": "Độ mờ nền",  "type": "range",   "min": 0, "max": 100, "default": 90 }
  ]
}
```

**`template.html`**:
```html
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  .ann {
    width: 100%; height: 100%;
    background: rgba(5, 5, 15, var(--alpha));
    border-left: 4px solid var(--color);
    border-radius: 0 8px 8px 0;
    padding: 12px 16px;
    display: flex; flex-direction: column;
    justify-content: center;
    font-family: 'Inter', sans-serif;
  }
  .ann-title {
    font-size: 13px; font-weight: 800;
    color: var(--color); margin-bottom: 6px;
    text-transform: uppercase; letter-spacing: 0.08em;
  }
  .ann-body { font-size: 12px; color: #fff; line-height: 1.5; }
</style>

<div class="ann" id="root">
  <div class="ann-title" id="title">Thông Báo</div>
  <div class="ann-body"  id="body">Nội dung</div>
</div>

<script>
(function() {
  const P = window.__WIDGET_PROPS || { title:'Thông Báo', body:'Nội dung', color:'#fbbf24', bgAlpha:90 };
  const root = document.getElementById('root');
  root.style.setProperty('--color', P.color || '#fbbf24');
  root.style.setProperty('--alpha', (P.bgAlpha||90)/100);
  document.getElementById('title').textContent = P.title || '';
  document.getElementById('body').textContent  = P.body  || '';
})();
</script>
```

---

## Cách Import Widget Vào Builder

### Cách 1: Đặt vào thư mục `/widgets/`
Tạo thư mục `widgets/tên-widget/` với 2 file bắt buộc.
Builder tự động tìm và hiển thị trong **Widget Library**.

### Cách 2: Import file `.zip`
Nén thư mục widget thành `.zip` → trong Builder click **"Import Widget"** → chọn file.

### Cách 3: Paste JSON
Trong Builder → **"Import Widget"** → paste nội dung `widget.json` trực tiếp.

---

## Danh Sách Widget Có Sẵn

| Widget | Thư mục | Mô tả |
|--------|---------|-------|
| ⏱ Countdown | `widgets/countdown/` | Đếm ngược đến giờ bắt đầu |
| 🖼 Media Frame | `widgets/media-frame/` | Khung ảnh/logo |
| 📖 Scripture | `widgets/scripture-verse/` | Câu Kinh Thánh |
| 🕐 Live Clock | `widgets/live-clock/` | Đồng hồ thực tế |

---

## Câu Hỏi Thường Gặp

**Q: Widget có thể fetch dữ liệu từ internet không?**
A: Có, dùng `fetch()` trong script. Lưu ý CORS.

**Q: Widget có thể nhận WebSocket events từ OBS không?**
A: Có, overlay engine sẽ inject `window.__OBS_WS` nếu cần.

**Q: Prop `image` hoạt động như thế nào?**
A: Builder hiển thị file picker. File được lưu tại `/widgets/assets/` và path được inject vào prop.

**Q: Làm thế nào để widget update khi prop thay đổi?**
A: Builder gọi lại `window.__widgetReload()` nếu function đó được export.
```javascript
window.__widgetReload = function(newProps) {
  // cập nhật DOM với props mới
};
```
