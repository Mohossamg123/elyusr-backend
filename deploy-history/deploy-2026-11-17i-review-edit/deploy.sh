#!/usr/bin/env bash
# Al-Yusr API — reviewers edit a pending change before approving it, with history (17 Nov 2026, i).
#  - new table change_request_revisions (own migration, run by path only)
#  - PATCH admin/change-requests/{id}/payload  and  GET admin/change-requests/{id}/revisions
#  - GET public/metal-prices (gold/silver per gram in EGP for the zakat calculator, cached 10 min)
#  - only new files + one `require` line in routes/api.php: no file of the other developer is rewritten
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/review-edit-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
NEW=(app/Http/Controllers/Api/V1/Extras/ChangeRequestEditController.php routes/api-2026-11-17-review-edit.php app/Http/Controllers/Api/V1/Extras/MetalPriceController.php database/migrations/2026_11_17_100000_create_change_request_revisions.php)
for f in "${NEW[@]}"; do php -l "$HERE/files/$f" >/dev/null; done
[[ -f app/Support/StaffAccess.php && -f app/Services/ActivityLogService.php && -f app/Http/Controllers/Api/V1/Extras/ChangeRequestDetailsController.php ]] || { echo "ملفات أساسية ناقصة على السيرفر"; exit 1; }
for f in "${NEW[@]:0:3}"; do [[ ! -e "$f" ]] || { echo "الملف موجود بالفعل: $f (مش هكتب فوقه)"; exit 1; }; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
cp routes/api.php "$BK/api.php"; echo "cp $BK/api.php routes/api.php" >> "$BK/rollback.sh"
mkdir -p "$(dirname "${NEW[3]}")"; cp "$HERE/files/${NEW[3]}" "${NEW[3]}"
php artisan migrate --path="${NEW[3]}" --force
echo "rm -f ${NEW[0]} ${NEW[1]} ${NEW[2]}   # the revisions table stays (harmless)" >> "$BK/rollback.sh"
for f in "${NEW[0]}" "${NEW[1]}" "${NEW[2]}"; do mkdir -p "$(dirname "$f")"; cp "$HERE/files/$f" "$f"; echo "  ✓ $f (جديد)"; done
grep -q "api-2026-11-17-review-edit.php" routes/api.php || printf "\nrequire __DIR__.'/api-2026-11-17-review-edit.php';\n" >> routes/api.php
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $ok=Illuminate\Support\Facades\Schema::hasTable("change_request_revisions");
 $r=collect(app("router")->getRoutes()->getRoutes())->map->uri()->filter(fn($u)=>str_contains($u,"change-requests/{id}/"))->count();
 echo "  table: ", ($ok?"✓":"✗"), "  routes: $r ", ($r>=2?"✓":"✗"), "\n";
 $res=(new App\Http\Controllers\Api\V1\Extras\MetalPriceController)(); echo "  metal prices: HTTP ", $res->getStatusCode(), " ", substr(json_encode($res->getData(true), JSON_UNESCAPED_UNICODE),0,160), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
