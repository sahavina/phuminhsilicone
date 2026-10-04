#!/bin/sh
# Làm nóng cache Cloudflare: gọi mọi URL trong sitemap để khách vào không phải chờ
# máy chủ dựng trang (MISS/EXPIRED ~0,7 s → HIT ~0,2 s).
#
# Dùng: sh warm-cache.sh [https://siliconephuminh.com] [số luồng]
# Mặc định 1 luồng: máy chủ chậm hẳn (4–11 s) khi nhiều trang chưa cache bị gọi cùng lúc.
# Cron (DirectAdmin → Cron Jobs, mỗi 60 phút — nhỏ hơn Edge TTL của Cache Rule):
#   7 * * * * sh /home/keodansaha/saha-deploy/warm-cache.sh >/dev/null 2>&1
#
# Chỉ cần sh + curl + xargs (chạy được trên CentOS 7). Không gửi cookie → nhận đúng
# bản cache của khách. Request đi từ máy chủ nên làm nóng điểm Cloudflare gần máy
# chủ — cũng là điểm khách ở Việt Nam thường đi qua.

SITE="${1:-https://siliconephuminh.com}"
JOBS="${2:-1}"
UA="Mozilla/5.0 (compatible; SAHA-cache-warmer/1.0)"

fetch() {
	curl -s -L --max-time 30 -A "$UA" "$1"
}

urls=$(
	echo "$SITE/"
	for map in $(fetch "$SITE/wp-sitemap.xml" | grep -oE '<loc>[^<]+</loc>' | sed -e 's/<loc>//' -e 's#</loc>##'); do
		fetch "$map" | grep -oE '<loc>[^<]+</loc>' | sed -e 's/<loc>//' -e 's#</loc>##'
	done
)

total=$(printf '%s\n' "$urls" | grep -c .)
start=$(date +%s)

# Mỗi URL in đúng một dòng (chạy song song không lẫn): mã HTTP, cache Cloudflare, TTFB, URL.
# curl của CentOS 7 chưa có %header{} → đọc header bằng -D rồi tách.
printf '%s\n' "$urls" | sort -u | xargs -P "$JOBS" -I{} sh -c '
	h=$(curl -s -o /dev/null --max-time 30 -A "$1" -H "Accept: text/html" \
		-H "Accept-Encoding: gzip, deflate, br" -D - -w "~~%{http_code} %{time_starttransfer}" "$2")
	s=$(printf "%s\n" "$h" | tr -d "\r" | awk -F": " "tolower(\$1) == \"cf-cache-status\" { print \$2 }")
	r=${h##*~~}
	printf "%s %s %ss %s\n" "${r% *}" "${s:--}" "${r#* }" "$2"
' _ "$UA" {} > /tmp/saha-warm.log

echo "$(date '+%F %T') $SITE: $total URL, $(($(date +%s) - start)) s" \
	"| HIT: $(grep -c ' HIT ' /tmp/saha-warm.log)" \
	"| MISS/EXPIRED: $(grep -cE ' (MISS|EXPIRED) ' /tmp/saha-warm.log)" \
	"| lỗi: $(grep -vcE '^(200|301|302) ' /tmp/saha-warm.log)"
