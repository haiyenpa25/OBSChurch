# OBSChurch — Broadcast Command System

> Hệ thống quản lý trình chiếu thờ phượng thời gian thực qua WebSocket cho OBS Studio.

---

## 📋 Tổng quan Dự án

| Thuộc tính     | Giá trị                               |
| -------------- | ------------------------------------- |
| Tên            | OBSChurch Broadcast Command           |
| Phiên bản      | 1.0.0                                 |
| Công nghệ      | HTML5, Vanilla JS (ES Modules), CSS3  |
| Backend        | Node.js + `ws` (WebSocket library)    |
| Server         | XAMPP (Apache) + Node.js :3000        |
| Tương thích    | OBS Studio 28+ (Browser Source)       |
| Ngôn ngữ       | Tiếng Việt (Unicode UTF-8)            |

---

## 🏗️ Kiến trúc Hệ thống

```
┌──────────────────────────────────────────────┐
│  CONTROL PANEL  (Operator)                   │
│  http://localhost/OBSChurch/index.html       │
│  ├─ Col1: Master Scenes                      │
│  ├─ Col2: Event Items (contextual)           │
│  ├─ Col3: Input Panel + Template Picker      │
│  └─ Col4: PGM Monitor + Switcher Grid        │
└─────────────────┬────────────────────────────┘
                  │ WebSocket ws://localhost:3000
        ┌─────────▼──────────┐
        │   WS SERVER         │
        │ server/ws-server.js │  ← Node.js hub
        └─────────┬──────────┘
                  │ Broadcast → all clients
┌─────────────────▼────────────────────────────┐
│  OBS BROWSER SOURCE                          │
│  http://localhost/OBSChurch/overlay/         │
│  ├─ TemplateEngine (factory + polymorphism)  │
│  ├─ TransitionController                     │
│  └─ Templates: Clean / Vintage / SciFi / Glass│
└──────────────────────────────────────────────┘
```

---

## 📁 Cấu trúc Thư mục

```
OBSChurch/
│
├── INFO.md                         ← Tài liệu này
├── mota.html                       ← File mô tả UI gốc (tham chiếu)
├── index.html                      ← Control Panel chính
│
├── overlay/                        ← OBS Browser Source
│   ├── index.html                  ← Entry point OBS load
│   ├── overlay.css                 ← Styles overlay (alpha transparent)
│   └── templates/                  ← Lower Third templates
│       ├── clean-dark.html
│       ├── vintage.html
│       ├── scifi.html
│       └── glassmorphism.html
│
├── server/                         ← Node.js backend
│   ├── package.json
│   ├── ws-server.js                ← WebSocket server + state
│   └── data/
│       └── scenes.json             ← Dữ liệu cảnh & tiết mục
│
└── src/
    ├── css/
    │   ├── base.css                ← Design tokens & reset
    │   ├── components.css          ← Panel, button, input UI
    │   └── animations.css          ← Micro-animations
    │
    └── js/
        ├── core/
        │   ├── EventBus.js         ← Pub/Sub (Observer pattern)
        │   ├── StateManager.js     ← Single source of truth
        │   └── WSClient.js         ← WebSocket client + auto-reconnect
        │
        ├── models/
        │   ├── Scene.js            ← Scene data model
        │   ├── EventItem.js        ← EventItem data model
        │   └── OverlayPayload.js   ← WS payload model
        │
        ├── controllers/
        │   ├── SceneController.js  ← Logic Cột 1
        │   ├── ItemController.js   ← Logic Cột 2
        │   ├── InputController.js  ← Logic Cột 3 + validation
        │   └── SwitcherController.js ← Logic Cột 4 + hotkeys
        │
        ├── views/
        │   ├── SceneView.js        ← Render Cột 1
        │   ├── ItemView.js         ← Render Cột 2
        │   ├── InputView.js        ← Render Cột 3
        │   └── MonitorView.js      ← Render Cột 4 preview
        │
        └── app.js                  ← Bootstrap & DI wiring
```

---

## 🚀 Hướng dẫn Chạy

### 1. Cài đặt WebSocket Server
```bash
cd OBSChurch/server
npm install
node ws-server.js
# Server chạy tại ws://localhost:3000
```

### 2. Mở Control Panel
```
http://localhost/OBSChurch/index.html
```

### 3. Cấu hình OBS Browser Source
```
URL: http://localhost/OBSChurch/overlay/
Width: 1920
Height: 1080
Custom CSS: body { background: transparent; }
```

---

## ⌨️ Phím tắt (Keyboard Shortcuts)

| Phím   | Chức năng                          |
| ------ | ---------------------------------- |
| F1     | Show Overlay                       |
| F2     | Hide Overlay                       |
| F3     | Trigger Transition                 |
| F4     | Clear All Overlays                 |
| F5     | Next Event Item                    |
| F6     | Previous Event Item                |
| F12    | **Safety Cam Cut** (ưu tiên cao)  |

---

## 📡 WebSocket Message Schema

```json
// Hiện overlay với nội dung
{ "type": "OVERLAY_SHOW",   "payload": { "templateId": "clean-dark", "songName": "Thánh Nhạc 1", "speaker": "", "scripture": "Giăng 3:16", "announcement": "" } }

// Ẩn overlay
{ "type": "OVERLAY_HIDE",   "payload": {} }

// Hiệu ứng chuyển cảnh
{ "type": "OVERLAY_TRANS",  "payload": { "effect": "slide-up" } }

// Xóa tất cả overlay
{ "type": "CLEAR_ALL",      "payload": {} }

// Cắt camera an toàn (khẩn cấp)
{ "type": "SAFETY_CUT",     "payload": {} }

// Chuyển cảnh OBS
{ "type": "SCENE_CHANGE",   "payload": { "sceneId": "chinh-le" } }
```

---

## 🎨 Template Lower Third

| ID               | Phong cách          | Animation          |
| ---------------- | ------------------- | ------------------ |
| `clean-dark`     | Tối giản, chuyên nghiệp | Slide từ trái   |
| `vintage`        | Ấm áp, retro        | Fade in            |
| `scifi`          | Công nghệ, neon     | Glitch reveal      |
| `glassmorphism`  | Kính mờ, hiện đại   | Scale + blur in    |

---

## 🔑 Nguyên tắc Code

### Quy ước đặt tên
| Loại              | Convention         | Ví dụ                  |
| ----------------- | ------------------ | ---------------------- |
| Class             | PascalCase         | `SceneController`      |
| Method / Variable | camelCase          | `getActiveScene()`     |
| Private           | _underscore        | `_broadcastAll()`      |
| Constant          | SCREAMING_SNAKE    | `WS_EVENTS.SHOW`       |
| HTML ID           | kebab-case         | `btn-push-pgm`         |
| CSS Variable      | kebab-case         | `--color-primary`      |

### Giới hạn độ dài
- **Function**: tối đa 30 dòng
- **File JS**: tối đa 150 dòng
- **File CSS**: tối đa 200 dòng

### Patterns áp dụng
- **Observer** — `EventBus` tách biệt Controller & View
- **Factory** — `TemplateEngine` tạo template đúng loại
- **Singleton** — `StateManager`, `WSClient`
- **MVC** — Model / View / Controller tách rời
- **Inheritance** — `BaseController`, `BaseModel`, `BaseTemplate`
- **Polymorphism** — Mỗi template override `render()` và `animate()`

---

## 📋 Lịch sử Thay đổi

| Phiên bản | Ngày       | Mô tả                          |
| --------- | ---------- | ------------------------------ |
| 1.0.0     | 2026-06-04 | Khởi tạo dự án, build P0 + P1 |
