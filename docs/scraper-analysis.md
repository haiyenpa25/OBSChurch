# Phân Tích & Kế Hoạch Thánh Ca Scraper

## Tổng Quan Nguồn Dữ Liệu

### 🔵 Nguồn 1: `https://thanhca.httlvn.org/` — CHUẨN (Ưu tiên)
- **Đây là nguồn CHÍNH xác** cho Thánh Ca Tin Lành Việt Nam (HTTLVN)
- URL bài hát: `https://thanhca.httlvn.org/thanh-ca-{số}/{slug-tên-bài}`
- URL danh sách: `https://thanhca.httlvn.org/thanh-ca` (hiển thị 20 bài/trang)
- **Phân loại chính thức** theo chủ đề (cat_top, cat_sub)
- Có thêm: Hợp Âm, Sheet Nhạc, MP3, Beat, Karaoke, PowerPoint!
- Số bài: ~553+ bài (có thể nhiều hơn với bổ sung)
- Lời nằm trong `<pre class="lyric">` hoặc `<div class="lyric-content">`

### 🟡 Nguồn 2: `http://www.thanhcatinlanh.com/` — Đa Tuyển Tập
Có **9 tuyển tập** khác nhau:
1. `thanh-ca-httl-viet-nam` — 553 bài (Thánh Ca VN cũ, chọn lọc)
2. `thanh-ca-baptist-bac-my` — Báp-tít Bắc Mỹ
3. `thanh-ca-tin-lanh-bac-my` — Tin Lành Bắc Mỹ
4. `ton-vinh-chua-hang-huu` — Tôn Vinh Chúa Hằng Hữu
5. `ca-khuc-chuc-ton` — Ca Khúc Chúc Tôn
6. `bai-ca-moi` — Bài Ca Mới
7. `nhac-si-david-dong-psalm-music` — NS David Dong
8. `nhac-si-le-anh-dong` — NS Lê Anh Đông
9. `nhung-ban-hoa-am-moi` — Những Bản Hòa Âm

URL bài hát: `http://www.thanhcatinlanh.com/index.php/{collection}/{số}-{slug}`

## Cấu Trúc URL HTTLVN

```
Danh sách: https://thanhca.httlvn.org/thanh-ca?page=1
Bài hát:   https://thanhca.httlvn.org/thanh-ca-{id}/{slug}
Phân loại: https://thanhca.httlvn.org/thanh-ca?cat_top=THỜ+PHƯỢNG
```

### Phân Loại Chính HTTLVN (từ site):
| Mã | Tên | Bài số |
|---|---|---|
| I | Thờ Phượng | 1-38, 456-460, 510-553 |
| II | Đức Chúa Trời | 39-52, 554 |
| III | Chúa Jêsus Christ | ... |
| IV | Đức Thánh Linh | ... |
| V | Hội Thánh | ... |
| VI | Kinh Thánh | ... |
| VII | Tin Lành | ... |
| VIII | Đời Tín Đồ | ... |
| ... | ... | ... |

## Kế Hoạch Scraper

### Chiến Lược:
1. **HTTLVN.org** → Thánh Ca chính thức (1-553+), có phân loại chính xác
2. **thanhcatinlanh.com** → 8 tuyển tập còn lại (Báp-tít, Bắc Mỹ, Tôn Vinh, ...)

### Database Schema (SQLite):
```sql
sources: httlvn | thanhcatinlanh
collections: httl-vn, baptist-bac-my, tin-lanh-bac-my, ton-vinh, ca-khuc, bai-ca-moi, david-dong, le-anh-dong, hoa-am
cat_top, cat_sub: phân loại HTTLVN chính thức
```

## Cần Làm
- [x] Khảo sát cấu trúc 2 nguồn
- [ ] Viết scraper mới cho HTTLVN.org (nguồn chính)
- [ ] Cập nhật scraper thanhcatinlanh.com cho 9 tuyển tập
- [ ] Cập nhật SQLite schema thêm cat_top, cat_sub, source
- [ ] Cập nhật thanh-ca.html UI: hiển thị phân loại chủ đề
