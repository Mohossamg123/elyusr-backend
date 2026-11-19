#!/usr/bin/env bash
# Al-Yusr API — distribution candidates (17 Nov 2026, o): each candidate donation also returns where it went
# (target kind + name) and its package / share name. One file replaced, no migration.
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/distribution-targets-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
F=app/Http/Controllers/Api/V1/Impact/DistributionController.php
OLD_MD5=0ad65583642c9d62ebcafbdb2fd5b36d
php -l "$HERE/files/$F" >/dev/null
[[ -f "$F" ]] || { echo "الملف مش موجود (الأثر مش منشور؟)"; exit 1; }
[[ "$(md5sum "$F" | cut -d' ' -f1)" == "$OLD_MD5" ]] || { echo "الملف اتغيّر على السيرفر عن النسخة اللي اتبنت عليها 17o (مش هكتب فوقه)"; exit 1; }
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; cp "$F" "$BK/DistributionController.php"
printf '#!/usr/bin/env bash\ncd %s\ncp %s/DistributionController.php %s\nphp artisan optimize:clear >/dev/null 2>&1 || true\n' "$APP" "$BK" "$F" > "$BK/rollback.sh"
cp "$HERE/files/$F" "$F"; echo "  ✓ $F (اتحدّث)"
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $ok=method_exists(App\Http\Controllers\Api\V1\Impact\DistributionController::class,"candidates"); echo "  controller: ", ($ok?"✓":"✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
