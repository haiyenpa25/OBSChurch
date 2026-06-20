# 📖 Hướng Dẫn Sử Dụng OBSChurch

> **Phiên bản:** 2.0 · **Ngày cập nhật:** 06/2026
> Web app điều khiển buổi lễ trực tiếp — đồng bộ với OBS Studio qua WebSocket

---

## Mục Lục

1. [Kiến trúc tổng quan](#1-kiến-trúc-tổng-quan)
2. [Cài đặt & Khởi động](#2-cài-đặt--khởi-động)
3. [Đồng bộ với OBS Studio](#3-đồng-bộ-với-obs-studio)
4. [Broadcast Command Center (index.html)](#4-broadcast-command-center-indexhtml)
5. [Layout Builder (builder.html)](#5-layout-builder-builderhtml)
6. [Scene Designer (scene-types.html)](#6-scene-designer-scene-typeshtml)
7. [Widget SDK](#7-widget-sdk)
8. [Dữ liệu & Thư mục](#8-dữ-liệu--thư-mục)
9. [Phím tắt tổng hợp](#9-phím-tắt-tổng-hợp)
10. [Quy trình điều hành buổi lễ](#10-quy-trình-điều-hành-buổi-lễ)

---

## 1. Kiến trúc tổng quan

```
┌─────────────────────────────────────────────────────────────────┐
│                     MÁY TÍNH ĐIỀU KHIỂN                         │
│                                                                 │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────────────────┐  │
│  │  index.html  │  │ builder.html │  │  scene-types.html     │  │
│  │  (Broadcast  │  │  (Layout     │  │  (Scene Designer)     │  │
│  │   Command)   │  │   Studio)    │  │                       │  │
│  └──────┬───────┘  └──────┬───────┘  └──────────┬────────────┘  │
│         │                │                      │               │
│         └────────────────┴──────────────────────┘               │
│                          │ WebSocket (ws://localhost:8080)       │
│                  ┌───────▼────────┐                             │
│                  │  ws-server.js  │  ← Hub trung tâm            │
│                  │  (Node.js)     │  ← REST API + WS Broadcast  │
│                  └───────┬────────┘                             │
│                          │ Broadcast tới tất cả clients         │
└──────────────────────────┼──────────────────────────────────────┘
                           │
         ┌─────────────────┼────────────────────────┐
         │                 │                        │
┌────────▼────────┐ ┌──────▼──────────┐ ┌──────────▼──────────┐
│  overlay/       │ │  Widget files   │ │  Máy chiếu / TV      │
│  index.html     │ │  (Browser Src)  │ │  (Preview monitor)   │
│  (OBS Browser   │ │                 │ │                       │
│   Source)       │ │                 │ │                       │
└─────────────────┘ └─────────────────┘ └───────────────────────┘
       ▲ OBS đọc URL này và hiển thị
       │ đồ hoạ lên livestream
```

### Luồng hoạt động cốt lõi

```
Người điều hành bấm "Chiếu" trên index.html
        ↓
WebSocket message gửi tới ws-server.js (port 3000)
        ↓
Server broadcast tới TẤT CẢ clients đang kết nối
        ↓
overlay/index.html (đang chạy bên trong OBS Browser Source)
nhận được message và cập nhật đồ hoạ ngay lập tức
        ↓
Khán giả xem livestream thấy Lower Third / đồ hoạ xuất hiện
```

---

## 2. Cài đặt & Khởi động

### Yêu cầu hệ thống

| Phần mềm | Phiên bản | Mục đích |
|----------|-----------|----------|
| Node.js | ≥ 18 | Chạy WebSocket server |
| XAMPP / Apache | bất kỳ | Serve file HTML |
| OBS Studio | ≥ 29 | Phần mềm livestream |
| Trình duyệt | Chrome/Edge mới nhất | Web app |

### Bước 1: Clone/Copy project

```
d:\Xampp\htdocs\OBSChurch\   ← Thư mục gốc
```

Hoặc clone từ GitHub:
```bash
git clone https://github.com/haiyenpa25/OBSChurch.git
cd OBSChurch
```

### Bước 2: Khởi động server

**Cách 1 — Double-click file:**
```
START-SERVER.bat      ← Windows Batch
START-SERVER.ps1      ← PowerShell
```

**Cách 2 — Terminal:**
```bash
node server/ws-server.js
```

**Kết quả khi thành công:**
```
🎛️  OBSChurch WS Server v2.0
   WebSocket  : ws://localhost:8080
   REST API   : http://localhost:8080/api/
   Endpoints  : scenes | layouts | scene-types | bindings | config | ...
```

### Bước 3: Mở web app

Truy cập bằng trình duyệt:

| Trang | URL |
|-------|-----|
| Broadcast Command | `http://localhost/OBSChurch/index.html` |
| Layout Builder | `http://localhost/OBSChurch/builder.html` |
| Scene Designer | `http://localhost/OBSChurch/scene-types.html` |
| Overlay Preview | `http://localhost/OBSChurch/overlay/index.html` |

---

## 3. Đồng bộ với OBS Studio

Đây là phần quan trọng nhất — **cơ chế đồng bộ từ web app về OBS**.

### 3.1 Cách hoạt động

Web app **KHÔNG gửi lệnh trực tiếp** vào OBS. Thay vào đó:

1. Web app gửi message qua **WebSocket** → `ws-server.js` (port 3000)
2. Server **broadcast** tới tất cả clients, bao gồm `overlay/index.html`
3. `overlay/index.html` được **thêm vào OBS** dưới dạng **Browser Source**
4. Browser Source nhận message và **cập nhật đồ hoạ** trực tiếp

> **Kết quả:** Khi người điều hành bấm nút trên web app, đồ hoạ trong OBS thay đổi ngay lập tức (< 50ms).

### 3.2 Thiết lập OBS Browser Source

**Bước 1: Tạo Scene trong OBS**
- Mở OBS Studio
- Tạo Scene mới (ví dụ: `Overlay Layer`)

**Bước 2: Thêm Browser Source**
- Nhấn `+` trong Sources
- Chọn **Browser**
- Đặt tên: `OBSChurch Overlay`

**Bước 3: Cấu hình Browser Source**

```
URL:    http://localhost/OBSChurch/overlay/index.html
Width:  1920
Height: 1080
☑ Shutdown source when not visible
☑ Refresh browser when scene becomes active
Custom CSS: (để trống)
```

**Bước 4: Đặt Scene Overlay lên trên**
- Đặt Scene `Overlay Layer` (chứa Browser Source) lên trên tất cả scenes khác
- Hoặc dùng **Scene Collection** với overlay là layer riêng

> ⚠️ **Lưu ý quan trọng:** Browser Source phải cùng mạng LAN với máy chạy server. Nếu OBS ở máy khác, đổi `localhost` thành IP của máy chủ.

### 3.3 Các loại message WebSocket

| Message Type | Mô tả | Ai gửi |
|-------------|-------|--------|
| `OVERLAY_SHOW` | Hiển thị đồ hoạ với nội dung mới | index.html |
| `OVERLAY_HIDE` | Ẩn đồ hoạ (có animation) | index.html |
| `OVERLAY_TRANS` | Chuyển cảnh có transition effect | index.html |
| `OVERLAY_SWITCH` | Đổi bố cục overlay (Scene Type) | index.html |
| `CLEAR_ALL` | Tắt tất cả đồ hoạ ngay | index.html |
| `SAFETY_CUT` | Tắt khẩn cấp (không animation) | index.html |
| `SCENE_CHANGE` | Báo cảnh OBS đã đổi | index.html |
| `STATE_SYNC` | Đồng bộ trạng thái khi client mới kết nối | Server → Client |

### 3.4 Kết nối OBS WebSocket (tùy chọn)

Ngoài Browser Source, app cũng hỗ trợ kết nối **OBS WebSocket** để điều khiển cảnh:

**Trong index.html:**
1. Bấm nút **OBS Studio** (góc trên phải)
2. Nhập:
   - Host: `localhost` (hoặc IP máy OBS)
   - Port: `4455` (mặc định OBS WebSocket)
   - Password: (nếu đã cấu hình trong OBS)
3. Bấm **Kết nối**

**Trong OBS Studio:**
- Vào `Tools → WebSocket Server Settings`
- Bật `Enable WebSocket server`
- Port: `4455`
- Đặt Password nếu cần bảo mật

---

## 4. Broadcast Command Center (index.html)

> **URL:** `http://localhost/OBSChurch/index.html`
> Trang điều khiển chính trong buổi lễ — người MC/kỹ thuật dùng

### 4.1 Giao diện tổng quan

```
┌────────────────────────────────────────────────────────────────┐
│ TOPBAR: Logo | Timecode | OBS Status | Nút điều khiển nhanh    │
├───────────────┬──────────────────────┬─────────────────────────┤
│  TRÁI         │  GIỮA                │  PHẢI                   │
│  Lộ Trình     │  Overlay Switcher    │  OBS Connection         │
│  Buổi Lễ      │  ─────────────       │  ─────────────────       │
│  (Timeline)   │  Tiết Mục Hiện Tại  │  Program Monitor        │
│               │  Template Picker     │  (Preview)              │
│  Scene 1      │  Fields Input        │                         │
│  ├ Tiết mục 1 │  [Chiếu] [Ẩn] [...]│                         │
│  ├ Tiết mục 2 │                      │                         │
│  Scene 2      │                      │                         │
│  └ ...        │                      │                         │
└───────────────┴──────────────────────┴─────────────────────────┘
```

### 4.2 Lộ Trình Buổi Lễ (Timeline bên trái)

- Hiển thị tất cả scenes và tiết mục từ `data/scenes.json`
- **Click** vào tiết mục để chọn (highlight màu xanh)
- **Scene Group** có thể expand/collapse
- Tiết mục đang chiếu: badge đỏ `ON AIR`

### 4.3 Overlay Switcher (xuất hiện khi chọn tiết mục có Scene Type)

Khi tiết mục được gán **Scene Type** (ví dụ: Giảng Luận):
```
Overlay Nhanh                    📖 Giảng Luận
[ 🅐 Bình Thường ] [ 🅑 PPTX Lớn ] [ 🅒 Cam Full ] [ 🅓 Kinh Thánh ]
```
- **Bấm vào button** → đổi bố cục ngay (WS `OVERLAY_SWITCH`)
- Nội dung vẫn giữ nguyên, chỉ thay đổi bố cục
- Trạng thái được lưu vào `data/widget-bindings.json`

### 4.4 Template Picker & Fields

Chọn **template đồ hoạ**:
| Template | Màu | Dùng cho |
|----------|-----|----------|
| Clean Dark | Xanh dương | Tổng quát |
| Vintage | Vàng ấm | Thờ phượng |
| SciFi | Xanh lá | Hiện đại |
| Glassmorphism | Trắng mờ | Sang trọng |
| Sermon Topic | Tím | Tiêu đề bài giảng |
| Scripture | Lam | Kinh Thánh fullscreen |

**Điền nội dung:**
- **Tên Bài Hát / Chủ Đề** — dòng chính
- **Sách / Câu gốc** — kinh thánh
- **Câu** — số câu cụ thể
- **Tên Diễn Giả / Ca Sĩ**
- **Thông Báo / Tiêu Đề**

### 4.5 Các nút điều khiển chính

| Nút | Phím tắt | Chức năng |
|-----|----------|-----------|
| **Chiếu** | `Space` | Hiển thị overlay lên OBS |
| **Ẩn** | `Escape` | Ẩn overlay (có fade) |
| **Transition** | `T` | Chuyển cảnh có hiệu ứng |
| **Tiếp theo** | `→` | Chuyển sang tiết mục kế |
| **Quay lại** | `←` | Quay về tiết mục trước |
| **Safety Cut** | `Ctrl+Shift+X` | Tắt khẩn cấp, ẩn ngay |

### 4.6 Auto-Pilot

Chế độ tự động chạy theo lịch buổi lễ:
1. Bấm **Bắt Đầu** (Start Service)
2. Auto-pilot sẽ tự động chiếu theo tiết mục
3. Banner Auto-Pilot hiển thị ở trên: tiết mục tiếp theo, nút Dừng/Bỏ qua/Thoát

---

## 5. Layout Builder (builder.html)

> **URL:** `http://localhost/OBSChurch/builder.html`
> Thiết kế bố cục đồ hoạ — làm TRƯỚC buổi lễ

### 5.1 Mục đích

Builder dùng để **thiết kế template overlay** — bố cục Camera Frame + Lower Third cho từng template (Clean Dark, Vintage...). Kết quả lưu vào `data/layouts.json`.

### 5.2 Bố cục giao diện

```
┌──────────────┬──────────────────────────────────┬───────────────┐
│ LEFT PANEL   │        CANVAS 16:9               │  RIGHT PANEL  │
│              │                                  │               │
│ Templates    │  [Camera]  [Text]  [Ticker]  ... │  Properties   │
│ ─────────    │                                  │  X: [  ]      │
│ Presets      │  ┌────────────────────────────┐  │  Y: [  ]      │
│ ─────────    │  │                            │  │  W: [  ]      │
│ Layers       │  │       16:9 Canvas          │  │  H: [  ]      │
│              │  │  (drag & resize elements)  │  │  R: [  ]      │
│              │  │                            │  │  Opacity      │
│              │  └────────────────────────────┘  │               │
│              │                                  │  Label        │
│              │  [Undo] [Redo] [Snap] [Save]     │  Custom CSS   │
└──────────────┴──────────────────────────────────┴───────────────┘
```

### 5.3 Elements có thể thêm

| Element | Biểu tượng | Dùng để |
|---------|------------|---------|
| **Camera** | 📹 | Đánh dấu vùng Camera trong OBS |
| **Text** | Tt | Lower Third với dòng chính + phụ |
| **Ticker** | — | Dòng chạy tin tức |
| **Logo** | ⛪ | Logo nhà thờ |
| **Widget** | 🧩 | Nhúng widget từ Widget Library |

### 5.4 Thao tác trên Canvas

| Thao tác | Cách làm |
|----------|---------|
| **Chọn element** | Click vào element |
| **Di chuyển** | Kéo element |
| **Resize** | Kéo handle góc (●) |
| **Xóa** | Chọn → Del, hoặc nút X trong Layers |
| **Ẩn/hiện** | Nút 👁 trong Layers |
| **Khóa** | Nút 🔒 trong Layers (không drag được) |

### 5.5 Lưu & Phát trực tiếp

- **Lưu Layout**: `Ctrl+S` hoặc nút `Save Layout` → lưu vào `data/layouts.json`
- **Phát thử**: Nút `Live Preview` → gửi layout lên OBS ngay
- **Gán vào Scene**: Chọn scene từ dropdown → `Assign to Scene`

---

## 6. Scene Designer (scene-types.html)

> **URL:** `http://localhost/OBSChurch/scene-types.html`
> Tạo và thiết kế các "Scene Types" với nhiều overlay layout — làm TRƯỚC buổi lễ

### 6.1 Khái niệm Scene Type

**Scene Type** = Loại cảnh với nhiều bố cục thay nhau:

```
Scene Type "Giảng Luận"
├── Overlay "Bình Thường"  → Cam nhỏ góc phải + Lower Third bên dưới
├── Overlay "PPTX Lớn"     → Cam mini + vùng PPTX chiếm 70%
└── Overlay "Kinh Thánh"   → Fullscreen text Kinh Thánh
```

Trong buổi lễ, khi đang ở tiết mục Giảng Luận, có thể **chuyển nhanh** giữa các overlay này mà **nội dung vẫn giữ nguyên**.

### 6.2 Giao diện 3 cột

```
┌──────────────┬──────────────────────────────────┬───────────────┐
│ TRÁI         │        CANVAS 16:9               │  RIGHT        │
│              │                                  │  PANEL        │
│ SCENE TYPES  │  [📹 Cam] [T Text] [🖼 Hình]    │               │
│ ┌ Giảng Luận│  [■ Shape] [🧩 Widget]           │  Props của    │
│ └ Thờ Phượng│  [↩] [↪] [🗑]                   │  element đang │
│              │  ┌────────────────────────────┐  │  được chọn:  │
│ OVERLAYS     │  │                            │  │               │
│ ┌ Bình Thường│  │       CANVAS               │  │  • Vị trí    │
│ └ PPTX Lớn  │  │  (kéo thả, resize)         │  │  • Kích thước│
│              │  │                            │  │  • Màu sắc   │
│ LAYERS       │  └────────────────────────────┘  │  • Nội dung  │
│ ├ 📹 Camera  │                                  │  • File hình  │
│ └ Tt Text    │                                  │  • OBS Source│
└──────────────┴──────────────────────────────────┴───────────────┘
```

### 6.3 Tạo Scene Type mới

1. Bấm **`+ Mới`** trong phần Scene Types
2. Điền: Tên (`Giảng Luận`), Icon (`📖`), Màu (`#a855f7`)
3. Bấm ✓ Tạo

### 6.4 Tạo Overlay mới

1. Chọn Scene Type
2. Bấm **`+ Mới`** trong phần Overlays
3. Điền: Tên (`Bình Thường`), Icon (`🅐`)
4. Bắt đầu thêm elements vào Canvas

### 6.5 Elements và Properties

#### 📹 Camera
- **Tên nguồn OBS**: Tên chính xác của Source trong OBS (ví dụ: `Camera Chính`)
- **Nhãn hiển thị**: Tên hiển thị trong canvas

#### Tt Text (Lower Third)
- **Dòng chính**: Nội dung dòng lớn
- **Dòng phụ**: Nội dung dòng nhỏ
- **Cỡ chữ**: `6px` — `80px`
- **Màu chữ**: Color picker
- **Màu nhấn**: Màu đường viền trái + dòng phụ
- **Blur nền**: `0` — `30px`

#### 🖼 Hình ảnh
- **Chọn file từ máy**: Bấm vùng file picker → chọn ảnh
- **URL**: Nhập đường dẫn ảnh online

#### ■ Shape
- **Màu nền**: Màu nền solid (color picker)
- **Màu viền**: Màu border
- **Độ dày viền**: `0` — `20px`

#### 🧩 Widget
- Chọn từ danh sách Widget Library
- Widget chạy độc lập với logic riêng

### 6.6 Lưu

- **`Ctrl+S`** hoặc nút **Lưu** — lưu tất cả scene types vào `data/scene-types.json`
- Lưu tự động khi tắt overlay tab

---

## 7. Widget SDK

> Thư mục: `widgets/`
> Tài liệu đầy đủ: `widgets/WIDGET_SDK.md`

### 7.1 Widget là gì?

Widget là các **đồ hoạ độc lập** có logic riêng, không phụ thuộc vào payload của overlay. Ví dụ:
- ⏱ Đồng hồ đếm ngược Countdown
- 🕐 Đồng hồ thời gian thực
- 📜 Lower Third theo mẫu
- 📖 Hiển thị câu Kinh Thánh
- 🎵 Lời bài hát

### 7.2 Danh sách Widget hiện có

| Widget | Thư mục | Mô tả |
|--------|---------|-------|
| Countdown | `countdown/` | Đồng hồ đếm ngược |
| Live Clock | `live-clock/` | Đồng hồ giờ thực |
| Lower Third Speaker | `lower-third-speaker/` | Giới thiệu diễn giả |
| Scripture Verse | `scripture-verse/` | Câu Kinh Thánh |
| Song Lyric Display | `song-lyric-display/` | Lời bài hát |
| Media Frame | `media-frame/` | Khung media |

### 7.3 Cấu trúc một Widget

```
widgets/
└── my-widget/
    ├── widget.json      ← Metadata & schema
    └── template.html   ← HTML + CSS + JS (tự contained)
```

**`widget.json` mẫu:**
```json
{
  "id": "my-widget",
  "name": "Tên Widget",
  "category": "graphics",
  "icon": "🎯",
  "description": "Mô tả widget",
  "version": "1.0.0",
  "props": [
    { "name": "title", "type": "text", "label": "Tiêu đề", "default": "Hello" },
    { "name": "color", "type": "color", "label": "Màu sắc", "default": "#ffffff" }
  ]
}
```

### 7.4 Widget Categories

| ID | Tên | Biểu tượng |
|----|-----|------------|
| `lower-third` | Lower Third | 📝 |
| `graphics` | Đồ Hoạ | 🎨 |
| `countdown` | Countdown | ⏱ |
| `scripture` | Kinh Thánh | 📖 |
| `lyrics` | Lời Bài Hát | 🎵 |

### 7.5 Thêm vào OBS

```
OBS → Sources → + → Browser
URL: http://localhost/OBSChurch/widgets/my-widget/template.html
Width: 1920  Height: 1080
```

---

## 8. Dữ liệu & Thư mục

### 8.1 Cấu trúc thư mục

```
OBSChurch/
├── index.html              ← Broadcast Command Center
├── builder.html            ← Layout Builder  
├── scene-types.html        ← Scene Designer
├── mota.html               ← (Trang khác)
│
├── overlay/
│   ├── index.html          ← Browser Source cho OBS ← ĐÂY LÀ URL GẮN VÀO OBS
│   └── overlay.css
│
├── widgets/                ← Widget Library
│   ├── WIDGET_SDK.md       ← Hướng dẫn viết widget
│   ├── _categories.json    ← Registry phân loại
│   ├── countdown/
│   ├── live-clock/
│   ├── lower-third-speaker/
│   └── ...
│
├── data/                   ← CƠ SỞ DỮ LIỆU JSON (commit GitHub)
│   ├── scenes.json         ← Lộ trình buổi lễ
│   ├── layouts.json        ← Bố cục templates từ Builder
│   ├── scene-types.json    ← Scene Types từ Scene Designer
│   ├── widget-bindings.json← Gán widget vào tiết mục
│   ├── service-config.json ← Cấu hình nhà thờ
│   └── README.md
│
├── server/
│   └── ws-server.js        ← WebSocket + REST API server
│
├── START-SERVER.bat        ← Khởi động nhanh (Windows)
├── START-SERVER.ps1        ← Khởi động nhanh (PowerShell)
└── CLONE_SERVER.bat        ← Clone về máy mới
```

### 8.2 REST API Endpoints

Server chạy tại `http://localhost:8080`:

| Method | URL | Mô tả |
|--------|-----|-------|
| GET | `/api/scenes` | Lấy lộ trình buổi lễ |
| GET | `/api/layouts` | Lấy tất cả layouts |
| POST | `/api/layouts` | Lưu layouts |
| GET | `/api/scene-types` | Lấy Scene Types |
| POST | `/api/scene-types` | Lưu Scene Types |
| GET | `/api/bindings` | Lấy widget bindings |
| POST | `/api/bindings` | Lưu widget bindings |
| GET | `/api/config` | Lấy cấu hình |
| POST | `/api/config` | Lưu cấu hình |
| GET | `/api/widgets` | Danh sách tất cả widgets |
| GET | `/api/widget-categories` | Widgets theo phân loại |
| GET | `/api/widgets/:id/template` | HTML template của widget |

### 8.3 Sao lưu dữ liệu

Tất cả dữ liệu ở thư mục `/data/` — commit lên GitHub để:
- **Sao lưu** tự động
- **Đồng bộ** giữa nhiều máy
- **Phục hồi** khi có sự cố

```bash
git add data/
git commit -m "Cập nhật lộ trình buổi lễ"
git push origin main
```

### 8.4 Dùng trên máy mới

```bash
# Clone project về
git clone https://github.com/haiyenpa25/OBSChurch.git

# Hoặc chạy file
CLONE_SERVER.bat
```

---

## 9. Phím Tắt Tổng Hợp

### Broadcast Command Center (index.html)

| Phím | Chức năng |
|------|-----------|
| `Space` | Chiếu overlay |
| `Escape` | Ẩn overlay |
| `→` | Tiếp theo |
| `←` | Quay lại |
| `T` | Transition effect |
| `Ctrl+Shift+X` | Safety Cut (tắt khẩn cấp) |

### Layout Builder & Scene Designer

| Phím | Chức năng |
|------|-----------|
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Ctrl+S` | Lưu |
| `Delete` / `Backspace` | Xóa element đang chọn |
| `Escape` | Bỏ chọn element |

---

## 10. Quy Trình Điều Hành Buổi Lễ

### Trước buổi lễ (Setup — làm 1 lần)

```
1. Tạo Scene Types trong scene-types.html
   → Giảng Luận, Thờ Phượng, Thông Báo, Cầu Nguyện, ...
   → Mỗi loại có 2-4 overlay bố cục

2. Thiết kế layout trong builder.html
   → Chọn template (Clean Dark / Vintage / ...)
   → Sắp xếp Camera + Text + Logo
   → Lưu

3. Chuẩn bị lộ trình trong data/scenes.json
   → Danh sách Scene và Tiết mục
   → Gán scene type cho từng tiết mục

4. Thêm Browser Source vào OBS
   → URL: http://localhost/OBSChurch/overlay/index.html
   → 1920 × 1080, ở trên cùng layer stack
```

### Trong buổi lễ (Live)

```
1. Khởi động server (START-SERVER.bat)

2. Mở index.html trên máy điều khiển

3. Theo dõi Timeline bên trái
   → Click vào tiết mục đang diễn ra

4. Chọn template phù hợp, điền nội dung

5. Bấm CHIẾU → đồ hoạ xuất hiện trên livestream

6. Khi kết thúc tiết mục → bấm ẨN

7. Click tiếp theo → lặp lại

8. Nếu cần đổi bố cục (trong tiết mục Giảng Luận):
   → Dùng Overlay Switcher buttons phía trên
   → Bấm "PPTX Lớn" → bố cục đổi ngay, nội dung giữ
```

### Xử lý sự cố

| Vấn đề | Giải pháp |
|--------|-----------|
| Overlay không cập nhật | Kiểm tra server đang chạy? `ws://localhost:8080` |
| Browser Source trắng | Refresh Browser Source trong OBS |
| OBS và web app khác máy | Đổi `localhost` → IP máy chủ |
| Server crash | Chạy lại `START-SERVER.bat` |
| Layout không lưu | Kiểm tra thư mục `/data/` có quyền ghi |

---

## Ghi Chú Kỹ Thuật

### Kiến trúc Message (WebSocket)

```json
// OVERLAY_SHOW — hiển thị đồ hoạ
{
  "type": "OVERLAY_SHOW",
  "payload": {
    "templateId": "clean-dark",
    "songName": "Ngài Là Đức Chúa Trời",
    "speakerName": "Mục Sư Nguyễn Văn A",
    "scriptureRef": "Giăng 3",
    "scriptureVerse": "16",
    "announcement": "...",
    "camX": 5, "camY": 5, "camW": 40, "camH": 45, "camR": 8,
    "textX": 5, "textY": 78,
    "customCss": ""
  }
}

// OVERLAY_SWITCH — đổi bố cục trong scene type
{
  "type": "OVERLAY_SWITCH",
  "payload": {
    "sceneTypeId": "giang-luan",
    "overlayId": "ov-abc123",
    "overlayLabel": "PPTX Lớn",
    "layers": [...]
  }
}
```

### Thêm Widget mới

Xem `widgets/WIDGET_SDK.md` để hướng dẫn đầy đủ về:
- Cấu trúc file widget
- Schema `widget.json`
- API JavaScript nhận dữ liệu từ WebSocket
- Ví dụ widget mẫu

---

*Hướng dẫn được tạo tự động bởi hệ thống. Phiên bản mới nhất tại: `http://localhost/OBSChurch/`*
