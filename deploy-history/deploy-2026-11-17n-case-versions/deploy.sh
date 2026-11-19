#!/usr/bin/env bash
# Al-Yusr API — case versions (17 Nov 2026, n): the article-versions controller (my own file from 17m) also keeps
# versions of cases, under slug "case-<id>" with the case's own field list. One file replaced, no migration.
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/case-versions-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
F=app/Http/Controllers/Api/V1/Extras/BlogRevisionController.php
OLD_MD5=4d7b9106b0d8e563645a05dbf87409e3
php -l "$HERE/files/$F" >/dev/null
[[ -f "$F" ]] || { echo "الملف مش موجود (17m مش منشورة؟)"; exit 1; }
[[ "$(md5sum "$F" | cut -d' ' -f1)" == "$OLD_MD5" ]] || { echo "الملف اتغيّر على السيرفر عن نسخة 17m (مش هكتب فوقه)"; exit 1; }
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; cp "$F" "$BK/BlogRevisionController.php"
printf '#!/usr/bin/env bash\ncd %s\ncp %s/BlogRevisionController.php %s\nphp artisan optimize:clear >/dev/null 2>&1 || true\n' "$APP" "$BK" "$F" > "$BK/rollback.sh"
cp "$HERE/files/$F" "$F"; echo "  ✓ $F (اتحدّث)"
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $ok=method_exists(App\Http\Controllers\Api\V1\Extras\BlogRevisionController::class,"store"); echo "  controller: ", ($ok?"✓":"✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
