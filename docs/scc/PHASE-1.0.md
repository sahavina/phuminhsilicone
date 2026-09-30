# SCC Phase 1 — Mốc 1.0: Nâng môi trường

> Thuộc [TECHNICAL-DESIGN.md](TECHNICAL-DESIGN.md) §15 (lộ trình Phase 1). Mốc này không đổi giao diện, chỉ chuẩn bị môi trường để viết code SCC.

## 1. Goal

- Chạy dự án trên **PHP 8.2** (yêu cầu tối thiểu mới của SCC, quyết định D10).
- Có hệ thống build JS/CSS bằng **`@wordpress/scripts` 36.0.0** (webpack, quyết định D8) cho `saha-builder` và `saha-theme`.
- CI tự chạy `php -l`, smoke test, lint và build trên mỗi push/PR.

Nghiệm thu: `npm run build` và `php tests/smoke.php` chạy được trên PHP 8.2.

## 2. Architecture

```
package.json            1 package npm cho cả repo (không workspace)
webpack.config.js       1 config, nhiều "package" build:
                          saha-builder  src/<entry>/index.js → plugins/saha-builder/build/
                          saha-theme    src/js/frontend.js   → themes/saha-theme/assets/build/
eslint.config.cjs       config mặc định wp-scripts + khai báo @wordpress/* là external
.github/workflows/ci.yml
```

- `@wordpress/*` **không** cài vào `node_modules` để chạy — webpack (DependencyExtractionWebpackPlugin) biến chúng thành `wp.*` do WordPress nạp sẵn và ghi danh sách phụ thuộc vào `*.asset.php`.
- `build/` **được commit** (D9): server production deploy bằng rsync, không cần Node. CI báo lỗi nếu `src/` đổi mà quên build lại.
- `webpack.config.js` tạo config mới cho mỗi package (`freshDefaults()`), tránh hai build dùng chung instance plugin.

## 3. Files

| File | Vai trò |
|---|---|
| `package.json`, `package-lock.json` | script `build`, `start`, `lint:js`, `lint:css`, `test:js`; khoá `@wordpress/scripts` 36.0.0 |
| `webpack.config.js` | entry của từng package, thư mục output |
| `eslint.config.cjs` | `import/core-modules` cho các gói `@wordpress/*` |
| `.github/workflows/ci.yml` | job PHP 8.2 + 8.3 (lint, smoke) và job Node 22 (lint, build, so `build/`) |
| `saha-core.php` | `Requires PHP: 8.2`, `Requires at least: 6.4`, `SAHA_CORE_MIN_PHP = '8.2'`, `SAHA_BUILDER_API_VERSION = 1` |

`wp saha qa` kiểm tra `PHP ≥ SAHA_CORE_MIN_PHP` nên tự báo lỗi nếu hosting còn PHP 8.0/8.1.

## 4. Database

Không đổi.

## 5. Code

Không có code nghiệp vụ mới.

## 6. Hooks

Không có.

## 7. Security

- `npm install` chặn 4 install script của gói phụ thuộc (cơ chế allow-scripts của npm) — giữ nguyên, build vẫn chạy.
- `node_modules/` không commit.

## 8. Testing

- [ ] `php -v` trên máy dev báo 8.2.x; trang **SAHA → Kiểm tra hệ thống** báo "PHP ≥ 8.2" đạt.
- [ ] `npm ci && npm run build` chạy xong không lỗi, sinh `saha-builder/build/theme-options.{js,css,asset.php}` và `saha-theme/assets/build/frontend.{js,css,asset.php}`.
- [ ] `npm run build` lần hai không làm đổi file trong `build/` (`git status` sạch).
- [ ] `php tests/smoke.php` trên PHP 8.2 → 0 failed.
- [ ] CI xanh trên branch `scc/*`.

## 9. Installation

### Máy dev Windows + XAMPP (XAMPP đóng gói PHP 8.0)

Đã làm trên máy dev hiện tại; thành viên mới làm tương tự:

1. Tải PHP 8.2 **Thread Safe** x64 VS16 từ windows.php.net, giải nén vào `C:\php82`. Chép `php.ini-development` → `php.ini`, bật: `curl`, `fileinfo`, `gd`, `mbstring`, `exif`, `mysqli`, `openssl`, `sodium`, `zip`; đặt `extension_dir = "C:/php82/ext"`.
2. Backup `C:\xampp\apache\conf\extra\httpd-xampp.conf` (trên máy dev: `httpd-xampp.conf.php80.bak`), rồi sửa:
   - `LoadFile`/`LoadModule` trỏ vào `C:/php82/php8ts.dll` và `C:/php82/php8apache2_4.dll`;
   - `SetEnv PHPRC "C:/php82"` và `PHPINIDir "C:/php82"` (cả hai chỗ — XAMPP có 2 dòng, sót một dòng là Apache vẫn đọc php.ini của 8.0);
   - Thêm `LoadFile` nạp trước các DLL phụ thuộc, nếu không Apache lấy nhầm bản cũ trong `apache\bin` và `curl`/`sodium` không nạp được:
     ```apache
     LoadFile "C:/php82/libssh2.dll"
     LoadFile "C:/php82/nghttp2.dll"
     LoadFile "C:/php82/libsodium.dll"
     LoadFile "C:/php82/brotlicommon.dll"
     LoadFile "C:/php82/brotlidec.dll"
     ```
3. Khởi động lại Apache, mở `phpinfo()` hoặc **SAHA → Kiểm tra hệ thống** để xác nhận.
4. WP-CLI: `C:/php82/php.exe C:/xampp/php/wp-cli.phar …`
5. Node.js ≥ 20 (máy dev: 24.18, CI: 22), rồi `npm ci`.

Hoàn tác: chép lại file `.php80.bak`, khởi động lại Apache.

### Production

**Chưa xác minh hosting có PHP 8.2** (rủi ro R4). Kiểm tra trước khi deploy bất kỳ code SCC nào: plugin khai báo `Requires PHP: 8.2`, WordPress sẽ không cho kích hoạt trên PHP thấp hơn.

## 10. Acceptance criteria

- [x] PHP 8.2.34 chạy trên Apache XAMPP; 10 extension nạp đủ, `debug.log` sạch.
- [x] `npm run build` thành công.
- [x] Hồi quy toàn bộ trên PHP 8.2: `php -l` 0 lỗi, smoke test 0 failed, `wp saha qa` 0 lỗi, `http-smoke --write` 0 failed.

## 11. Ghi chú & giới hạn

- CI chưa chạy test PHP có WordPress (cần MySQL + WordPress trong runner) — để mốc 1.7.
- `npm run test:js` hiện chưa có test (`--passWithNoTests`); unit test JS đi cùng builder ở mốc 1.3.
