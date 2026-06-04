# 🎛️ OBSChurch Broadcast Command

Hệ thống quản lý trình chiếu overlay thời gian thực cho các buổi thờ phượng.  
Kết nối với OBS qua Browser Source + WebSocket.

---

## ⚡ Cài Đặt Nhanh (3 bước)

### Bước 1 — Cài phần mềm

| Phần mềm | Link tải | Ghi chú |
|----------|----------|---------|
| XAMPP | https://www.apachefriends.org/ | Chỉ cần Apache |
| Node.js | https://nodejs.org/ | Bản LTS, v18+ |
| OBS Studio | https://obsproject.com/ | v30+ |

### Bước 2 — Copy thư mục

Copy toàn bộ thư mục `OBSChurch` vào:
```
C:\xampp\htdocs\OBSChurch\
```

> ⚠️ Xóa thư mục `server/node_modules/` trước khi copy (rất lớn, sẽ cài lại tự động)

### Bước 3 — Chạy lần đầu

1. Mở XAMPP Control Panel → **Start Apache**
2. Double-click file **`START-SERVER.bat`** trong thư mục này
3. Mở: `http://localhost/OBSChurch/index.html`

---

## 🎬 Sử Dụng Hàng Ngày

```
1. XAMPP Control Panel → Start Apache
2. Double-click START-SERVER.bat
3. Mở http://localhost/OBSChurch/index.html
```

---

## 📺 Cài OBS Browser Source

```
URL:    http://localhost/OBSChurch/overlay/index.html
Width:  1920
Height: 1080
```

Đặt source ở **lớp cao nhất** trong OBS scene stack.

---

## ⌨️ Phím Tắt

| Phím | Hành động |
|------|-----------|
| `F1` | Show overlay |
| `F2` | Hide overlay |
| `F3` | Transition |
| `F4` | Clear All |
| `F5` | Item tiếp theo |
| `F6` | Item trước |
| `F12` | Safety Cam Cut |

---

## 🔧 Sự Cố

| Vấn đề | Cách sửa |
|--------|----------|
| Không mở được `localhost` | XAMPP → Start Apache |
| WS Status = "Offline" | Chạy `START-SERVER.bat` |
| Cổng 80 bị chiếm | Đổi Apache sang 8080 trong XAMPP Config |
| Cổng 3000 bị chiếm | Sửa `PORT = 3001` trong `server/ws-server.js` |

---

## 📁 Cấu Trúc File

```
OBSChurch/
├── START-SERVER.bat      ← Chạy file này để khởi động
├── index.html            ← Control Panel
├── overlay/
│   └── index.html        ← OBS Browser Source URL
├── server/
│   ├── ws-server.js      ← WebSocket Hub
│   ├── package.json
│   └── data/scenes.json  ← Dữ liệu cảnh thờ phượng
└── src/
    ├── css/              ← Giao diện
    └── js/               ← Logic (MVC)
```

---

*OBSChurch Broadcast Command v2 — Built for worship teams*
