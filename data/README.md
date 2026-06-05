# /data — OBSChurch App Database

Thư mục này là **cơ sở dữ liệu JSON** của ứng dụng OBSChurch.  
Tất cả file được commit lên GitHub — **portable, không cần setup DB**.

## Files

| File | Mô tả |
|------|-------|
| `scenes.json` | Lộ trình buổi lễ (danh sách tiết mục) |
| `layouts.json` | Builder layouts cho mỗi template |
| `scene-types.json` | Scene Types với Inputs + Overlays |
| `widget-bindings.json` | Binding: tiết mục → scene type + overlay |
| `service-config.json` | Cấu hình nhà thờ (tên, giờ, OBS host) |

## Cách dùng

- Clone repo → dữ liệu mẫu có sẵn ngay
- Server đọc/ghi trực tiếp vào thư mục này
- Thêm tiết mục mới: chỉnh `scenes.json`
- Thêm scene type mới: chỉnh `scene-types.json`

## Backup

Copy toàn bộ thư mục `/data/` là đủ để khôi phục dữ liệu.
