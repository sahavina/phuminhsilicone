"""
Sinh docs/QA.md từ mục "## 8. Testing" của docs/PHASE-1.md … PHASE-7.md
và checklist SAHA Commerce Core ở docs/scc/PHASE-1.7.md.

Chạy lại mỗi khi checklist của một phase thay đổi:

    python tests/build-qa-checklist.py

Không sửa tay docs/QA.md — sửa ở PHASE-*.md rồi chạy lại script.
"""

import pathlib
import re
import sys

# Console Windows mặc định cp1252 không in được tiếng Việt.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8")

ROOT = pathlib.Path(__file__).resolve().parent.parent
DOCS = ROOT / "docs"

# Mục đã được công cụ tự động kiểm tra (một phần hoặc toàn bộ).
#   qa   = wp saha qa  /  SAHA → Kiểm tra hệ thống
#   http = php tests/http-smoke.php <url> [--write]
AUTO = {
    (1, 1): "qa", (1, 2): "qa", (1, 10): "qa", (1, 12): "qa",
    (3, 1): "http + qa", (3, 2): "http + qa", (3, 3): "http + qa", (3, 4): "qa (collation)",
    (3, 5): "http", (3, 6): "http + qa", (3, 7): "http + qa", (3, 10): "http",
    (3, 11): "http", (3, 12): "http", (3, 13): "qa",
    (4, 5): "http --write", (4, 6): "http --write", (4, 9): "http --write", (4, 10): "http --write",
    (4, 12): "http --write", (4, 13): "http --write", (4, 14): "http --write",
    (6, 7): "http", (6, 8): "http + qa",
    (7, 7): "http", (7, 14): "qa", (7, 17): "http",
}

# Checklist SCC (docs/scc/PHASE-1.7.md, mục 8 — bảng "Checklist SCC").
SCC_DOC = DOCS / "scc" / "PHASE-1.7.md"
#   smoke = php tests/smoke.php · js = npm run test:js · qa / http như trên
SCC_AUTO = {
    1: "qa", 2: "smoke", 5: "js", 6: "smoke", 8: "smoke", 9: "http", 10: "http",
    13: "qa", 14: "smoke", 16: "smoke + qa + http", 17: "smoke", 18: "smoke", 19: "http",
    20: "qa", 21: "smoke", 25: "qa", 26: "qa + http",
}

# Checklist SCC Phase 2 (docs/scc/PHASE-2.8.md — bảng "Checklist SCC Phase 2").
SCC2_DOC = DOCS / "scc" / "PHASE-2.8.md"
SCC2_AUTO = {
    2: "smoke", 3: "smoke", 5: "smoke", 7: "http", 9: "http", 11: "smoke + http --write",
    14: "smoke", 16: "js + qa", 17: "js", 18: "smoke", 20: "smoke",
    23: "http", 24: "smoke", 26: "http", 27: "http", 28: "smoke + http + qa", 29: "qa",
}

TITLES = {
    1: "Foundation",
    2: "Catalogue",
    3: "Search & Filter",
    4: "Quote & Lead",
    5: "Homepage",
    6: "SEO",
    7: "Performance",
}

ROW = re.compile(r"^\|\s*(\d+)\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*$")


def rows_for(phase: int):
    text = (DOCS / f"PHASE-{phase}.md").read_text(encoding="utf-8")
    match = re.search(r"## 8\. Testing(.*?)\n## 9\.", text, re.S)

    if not match:
        return []

    out = []

    for line in match.group(1).splitlines():
        m = ROW.match(line)

        if m:
            out.append((int(m.group(1)), m.group(2), m.group(3)))

    return out


def scc_rows(doc=None, heading="### Checklist SCC", end=r"\n## 9\."):
    text = (doc or SCC_DOC).read_text(encoding="utf-8")
    match = re.search(re.escape(heading) + r"\n(.*?)" + end, text, re.S)

    if not match:
        return []

    return [
        (int(m.group(1)), m.group(2), m.group(3))
        for m in (ROW.match(line) for line in match.group(1).splitlines())
        if m
    ]


def main() -> None:
    sections = []
    total = 0
    auto_total = 0

    for phase, title in TITLES.items():
        rows = rows_for(phase)
        total += len(rows)

        lines = [
            f"### P{phase} — {title} ([PHASE-{phase}.md](PHASE-{phase}.md))",
            "",
            "| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |",
            "|---|---|---|---|---|---|",
        ]

        for num, test, expect in rows:
            auto = AUTO.get((phase, num), "")
            auto_total += 1 if auto else 0
            lines.append(f"| P{phase}-{num:02d} | {test} | {expect} | {auto} | ☐ | |")

        sections.append("\n".join(lines))

    rows = scc_rows()
    total += len(rows)
    lines = [
        "### SCC — SAHA Commerce Core: saha-theme + SAHA Builder ([scc/PHASE-1.7.md](scc/PHASE-1.7.md))",
        "",
        "Trên `saha-theme`, các mục của P5 nói về UX Builder/Flatsome thay bằng mục SCC tương ứng.",
        "",
        "| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |",
        "|---|---|---|---|---|---|",
    ]

    for num, test, expect in rows:
        auto = SCC_AUTO.get(num, "")
        auto_total += 1 if auto else 0
        lines.append(f"| SCC-{num:02d} | {test} | {expect} | {auto} | ☐ | |")

    sections.append("\n".join(lines))

    rows = scc_rows(SCC2_DOC, "### Checklist SCC Phase 2", r"\n## ")
    total += len(rows)
    lines = [
        "### SCC Phase 2 — mega menu, template, cửa hàng, báo giá, import/export, element ([scc/PHASE-2.8.md](scc/PHASE-2.8.md))",
        "",
        "| ID | Test | Kỳ vọng | Tự động | Kết quả | Ghi chú |",
        "|---|---|---|---|---|---|",
    ]

    for num, test, expect in rows:
        auto = SCC2_AUTO.get(num, "")
        auto_total += 1 if auto else 0
        lines.append(f"| SCC2-{num:02d} | {test} | {expect} | {auto} | ☐ | |")

    sections.append("\n".join(lines))

    header = f"""# QA — Tổng Kho Keo Dán SAHA

> File này được sinh bởi `tests/build-qa-checklist.py` từ mục 8 của các `docs/PHASE-*.md`.
> Không sửa tay — sửa ở PHASE-*.md rồi chạy lại script.

Tổng: **{total} test thủ công**, trong đó **{auto_total}** đã có công cụ tự động kiểm tra (một phần hoặc toàn bộ).

## 1. Công cụ tự động — chạy trước

| Công cụ | Chạy ở đâu | Phủ | Ghi dữ liệu? |
|---|---|---|---|
| `php tests/smoke.php` | máy dev, không cần WordPress | logic thuần: validate, sanitize, tokenizer, cache, robots, SEO; builder (schema, sanitize mọi control, render, CSS), Theme Options, header/footer, trang chủ mẫu, Mua ngay | Không |
| `wp saha qa` hoặc **SAHA → Kiểm tra hệ thống** | trên site | môi trường, bảng + index, quyền, cấu hình, taxonomy, REST route + permission_callback, builder (quyền, file CSS, header/footer, trang chủ, shortcode Flatsome còn sót), WooCommerce (catalogue thật sự chặn mua, template override lỗi thời), relevance tìm kiếm, robots, bảo mật upload/debug, cron, object cache | Không |
| `php tests/http-smoke.php <url>` | máy bất kỳ | REST từ ngoài vào, mã HTTP, robots.txt, noindex, no-cache, lỗi PHP lộ ra trang, số H1, shortcode thô, route builder từ chối khách | Không |
| `php tests/http-smoke.php <url> --write` | máy bất kỳ → **chỉ local/staging** | spec §57: thiếu nonce, email sai, product giả, chống trùng, rate limit, honeypot | Có — 2 báo giá + 1 lead "[Mẫu] QA…" |
| `wp saha seed --with-crm` | trên site **local/staging** | tạo dữ liệu mẫu để các test trên có dữ liệu | Có — gỡ bằng `wp saha unseed` |

Thứ tự khuyến nghị trên staging:

```bash
# chạy tại thư mục gốc WordPress (cũng là gốc repo)
wp saha seed --with-crm
wp saha homepage --front        # trang chủ mẫu dựng bằng SAHA Builder (saha-theme)
wp saha qa --strict
php tests/http-smoke.php https://staging.example.com --write
```

Cột **Tự động** bên dưới ghi công cụ đã phủ mục đó. Mục có công cụ vẫn nên xem lại bằng mắt ở lần QA đầu.

## 2. Ma trận thiết bị & trình duyệt (spec §54, §55)

Chạy các trang: Trang chủ · Danh mục · Thương hiệu · Sản phẩm · Tìm kiếm · Báo giá · Liên hệ · Bài viết · 404.

| Chiều rộng | Thiết bị gợi ý | Chrome | Edge | Firefox | Safari |
|---|---|---|---|---|---|
| 320px | iPhone SE (1st) | ☐ | — | — | ☐ |
| 375px | iPhone 12 mini | ☐ | — | — | ☐ |
| 390px | iPhone 14 | ☐ | — | — | ☐ |
| 768px | iPad | ☐ | ☐ | ☐ | ☐ |
| 1024px | iPad ngang / laptop nhỏ | ☐ | ☐ | ☐ | ☐ |
| 1366px | laptop | ☐ | ☐ | ☐ | ☐ |
| 1440px | desktop | ☐ | ☐ | ☐ | ☐ |
| 1920px | màn lớn | ☐ | ☐ | ☐ | — |

Mỗi ô: không tràn ngang, sticky CTA không che nội dung, chữ đọc được, nút bấm được bằng ngón tay (≥ 40px), bàn phím Tab đi hết các nút/ô nhập (spec §39).

## 3. Checklist theo phase

Kết quả: ☐ chưa chạy · ✅ đạt · ❌ lỗi (ghi chú + link issue) · ➖ không áp dụng.
"""

    footer = """

## 4. Nghiệm thu (spec §98)

Một module chỉ được coi hoàn thành khi:

| Tiêu chí | Kết quả | Người xác nhận | Ngày |
|---|---|---|---|
| Hoạt động đúng — mọi test ❌ đã sửa và chạy lại | ☐ | | |
| Không PHP fatal (debug.log sạch sau khi chạy hết checklist) | ☐ | | |
| Không JS error (Console sạch ở mọi trang trong ma trận mục 2) | ☐ | | |
| Responsive — ma trận mục 2 đạt | ☐ | | |
| Security validation — P1, P4, P6 phần bảo mật đạt | ☐ | | |
| Tắt plugin SAHA Builder → mọi trang đã dựng hiển thị y hệt (SCC-03) | ☐ | | |
| Không còn trang nào dùng shortcode UX Builder/Flatsome (`wp saha qa`) | ☐ | | |
| Không sửa core (`git status` của WordPress/WooCommerce sạch) | ☐ | | |
| `wp saha qa --strict` đạt | ☐ | | |
| `php tests/http-smoke.php <staging> --write` đạt | ☐ | | |
| PageSpeed mobile: LCP < 2.5s, CLS < 0.1 (trang chủ, sản phẩm, thương hiệu) | ☐ | | |
"""

    (DOCS / "QA.md").write_text(header + "\n" + "\n\n".join(sections) + footer, encoding="utf-8", newline="\n")
    print(f"docs/QA.md: {total} test, {auto_total} có công cụ tự động")


if __name__ == "__main__":
    main()
