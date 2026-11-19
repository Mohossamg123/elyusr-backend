#!/usr/bin/env bash
# Al-Yusr API — activity trail (16 Nov 2026, d): every staff write and refused attempt is logged, plus sign-ins.
# Adds 3 new files and one line to bootstrap/providers.php. bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/activity-trail-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
NEW=(app/Http/Middleware/ActivityTrail.php app/Providers/ActivityTrailServiceProvider.php app/Support/ActivityResources.php)
now=$(md5sum bootstrap/providers.php | cut -d' ' -f1)
if ! grep -q ActivityTrailServiceProvider bootstrap/providers.php && [[ "$now" != "0f7bf03d9819af225549722a92e8ef14" ]]; then echo "bootstrap/providers.php اتغير على السيرفر ($now) — مفيش تعديل"; exit 1; fi
for f in "${NEW[@]}"; do php -l "$HERE/files/$f" >/dev/null; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; cp bootstrap/providers.php "$BK/providers.php"
printf '#!/usr/bin/env bash\ncd %s\ncp %s/providers.php bootstrap/providers.php\nrm -f %s\nphp artisan optimize:clear >/dev/null 2>&1 || true\n' "$APP" "$BK" "${NEW[*]}" > "$BK/rollback.sh"
for f in "${NEW[@]}"; do mkdir -p "$(dirname "$f")"; cp "$HERE/files/$f" "$f"; echo "  ✓ $f"; done
if ! grep -q ActivityTrailServiceProvider bootstrap/providers.php; then
  sed -i 's#^\];#    App\\Providers\\ActivityTrailServiceProvider::class,\n];#' bootstrap/providers.php
fi
php -l bootstrap/providers.php >/dev/null || { cp "$BK/providers.php" bootstrap/providers.php; echo "providers.php اتكسر — رجّعته"; exit 1; }
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $g=app("router")->getMiddlewareGroups()["api"] ?? []; echo "  middleware in api group: ", in_array(App\Http\Middleware\ActivityTrail::class, $g, true) ? "✓" : "✗", "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
