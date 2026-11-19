#!/usr/bin/env bash
# Al-Yusr API — misbaha admin overview v2 (16 Nov 2026, e): grand total, general vs rooms, 30-day series, top rooms/accounts.
# Replaces ONE file (our own, from 16b). Legacy response keys are kept. bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
F=app/Http/Controllers/Api/V1/Misbaha/MisbahaMeController.php; OLD=4ce167246893aa48212c5e278020fb88
BK="$HOME/backups/misbaha-overview-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
now=$(md5sum "$F" | cut -d' ' -f1)
[[ "$now" == "$OLD" || "$now" == "$(md5sum "$HERE/files/$F" | cut -d' ' -f1)" ]] || { echo "اتغير على السيرفر: $F ($now) — مفيش تعديل"; exit 1; }
php -l "$HERE/files/$F" >/dev/null; echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; cp "$F" "$BK/Controller.php"
printf '#!/usr/bin/env bash\ncd %s\ncp %s/Controller.php %s\nphp artisan optimize:clear >/dev/null 2>&1 || true\n' "$APP" "$BK" "$F" > "$BK/rollback.sh"
cp "$HERE/files/$F" "$F"; php -l "$F" >/dev/null || { cp "$BK/Controller.php" "$F"; echo "اتكسر — رجّعته"; exit 1; }
php artisan optimize:clear >/dev/null 2>&1 || true
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
