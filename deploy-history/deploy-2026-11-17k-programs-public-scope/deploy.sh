#!/usr/bin/env bash
# Al-Yusr API — public program list/detail show published programs only (17 Nov 2026, k).
# Staff with programs.update (checked from the Sanctum token) still see drafts in the dashboard.
# Touches ONE file of the other developer's (ProgramController.php): refuses when it differs from the version this was written against.
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/programs-scope-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
F=app/Http/Controllers/Api/V1/Program/ProgramController.php
OLD=54b6aa384087daa9fb4590effa77c87b
php -l "$HERE/files/$F" >/dev/null
[[ -f app/Support/StaffAccess.php ]] || { echo "StaffAccess ناقص"; exit 1; }
[[ "$(md5sum "$F" | cut -d' ' -f1)" == "$OLD" ]] || { echo "الملف اتغيّر على السيرفر عن النسخة اللي اتكتبت عليها: $F (مش هكتب فوقه)"; exit 1; }
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; cp "$F" "$BK/ProgramController.php"
printf '#!/usr/bin/env bash\ncd %s\ncp %s/ProgramController.php %s\nphp artisan optimize:clear >/dev/null 2>&1 || true\n' "$APP" "$BK" "$F" > "$BK/rollback.sh"
cp "$HERE/files/$F" "$F"; echo "  ✓ $F"
php artisan optimize:clear >/dev/null 2>&1 || true
n=$(curl -s -m 30 https://api.elyusr.org/api/v1/programs | grep -o '"status":"draft"' | wc -l || true)
t=$(curl -s -m 30 https://api.elyusr.org/api/v1/programs | grep -o '"slug"' | wc -l || true)
echo "  البرامج المنشورة في القائمة العامة: $t | مسودات ظاهرة: $n (لازم 0)"
[[ "$n" == "0" ]] || { echo "✗ في مسودات لسه ظاهرة، برجّع"; bash "$BK/rollback.sh"; exit 1; }
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
