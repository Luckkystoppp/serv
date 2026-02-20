# Hướng dẫn đăng ký Telegram Webhook

Sau khi upload code lên server, chạy lệnh này 1 lần duy nhất để đăng ký webhook:

```
https://api.telegram.org/bot8336938728:AAH9QDiLrpb-OLtTj9zWB9ouWKnRV4-UHx4/setWebhook?url=https://YOURDOMAIN.COM/admin/webhook.php
```

Thay `YOURDOMAIN.COM` bằng domain thực của bạn.

## Luồng hoạt động sau fix:

1. Admin vào `/admin/admin.php`
2. Lần đầu → gửi 1 tin nhắn Telegram duy nhất (không spam nữa)
3. Refresh trang → KHÔNG gửi lại tin (kiểm tra session + DB)
4. Ấn **[OK] Verify** trong Telegram → bot callback về webhook.php → mark token trong DB
5. Trang web tự polling 3 giây phát hiện → **vào admin panel ngay**, không cần mở web
6. Ấn **[X] Block** → xóa token, admin bị từ chối

## Các file đã sửa:
- `admin/admin.php` — fix spam, fix polling check từ DB
- `admin/webhook.php` — **MỚI** — xử lý Telegram callback
- `config.php` — đổi button Verify từ URL link → callback_data, bỏ emoji
