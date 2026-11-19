#!/usr/bin/env bash
# Al-Yusr API — hardening of release 17i after the independent review (17 Nov 2026, j).
#  - ChangeRequestEditController: row lock + status re-check inside the transaction, 409 when the request changed,
#    only fields already in the submitted payload, same sensitive-key list as the submission redaction (nested too)
#  - MetalPriceController: one fetch at a time (lock), 60 s back-off after a failure, shorter timeouts
# Both files are ours (17i); the other developer's files are not touched. bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/review-hardening-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
F=(app/Http/Controllers/Api/V1/Extras/ChangeRequestEditController.php app/Http/Controllers/Api/V1/Extras/MetalPriceController.php)
OLD=(111c02ee5ba1dc5395ba45bca0527952 3367bd2dfa5f1aa808dcfaf230158cfd)
for f in "${F[@]}"; do php -l "$HERE/files/$f" >/dev/null; done
for i in 0 1; do [[ "$(md5sum "${F[$i]}" | cut -d' ' -f1)" == "${OLD[$i]}" ]] || { echo "الملف اتغيّر عن نسخة 17i: ${F[$i]} (مش هكتب فوقه)"; exit 1; }; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
for f in "${F[@]}"; do cp "$f" "$BK/$(basename "$f")"; echo "cp $BK/$(basename "$f") $f" >> "$BK/rollback.sh"; cp "$HERE/files/$f" "$f"; echo "  ✓ $f"; done
echo "php artisan optimize:clear >/dev/null 2>&1 || true" >> "$BK/rollback.sh"
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $res=(new App\Http\Controllers\Api\V1\Extras\MetalPriceController)(); echo "  metal prices: HTTP ", $res->getStatusCode(), "\n";
 echo "  edit controller: ", class_exists(App\Http\Controllers\Api\V1\Extras\ChangeRequestEditController::class) ? "✓" : "✗", "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
