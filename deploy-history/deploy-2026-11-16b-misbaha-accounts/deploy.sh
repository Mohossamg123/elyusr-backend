#!/usr/bin/env bash
# Al-Yusr API — misbaha and accounts (16 Nov 2026, b): a signed-in account keeps its own misbaha tally
# (general + each shared room), rooms remember who created them, visitors only add to an anonymous
# daily total. New tables (own migration, run by path only), new routes file, 2 files replaced.
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/misbaha-accounts-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
declare -A EXPECT=([app/Models/MisbahaRoom.php]=c1aace9ad1cd2c4ff0e7b39d909e7fe7 [app/Http/Controllers/Api/V1/Misbaha/MisbahaRoomController.php]=8e72f20e559cf9d58d5bae02f7719ab3)
NEW=(app/Support/MisbahaTally.php app/Http/Controllers/Api/V1/Misbaha/MisbahaMeController.php routes/api-2026-11-16-misbaha.php database/migrations/2026_11_16_100000_create_misbaha_user_counts.php)
for f in "${!EXPECT[@]}"; do now=$(md5sum "$f" | cut -d' ' -f1); [[ "$now" == "${EXPECT[$f]}" ]] || { echo "اتغير على السيرفر: $f ($now) — مفيش تعديل"; exit 1; }; done
for f in "${!EXPECT[@]}" "${NEW[@]}"; do php -l "$HERE/files/$f" >/dev/null; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
cp routes/api.php "$BK/api.php"; echo "cp $BK/api.php routes/api.php" >> "$BK/rollback.sh"
for f in "${!EXPECT[@]}"; do mkdir -p "$BK/$(dirname "$f")"; cp "$f" "$BK/$f"; echo "cp $BK/$f $f" >> "$BK/rollback.sh"; done
# 1) tables first, so the new code never runs without them (only this migration, by path)
mkdir -p database/migrations; cp "$HERE/files/${NEW[3]}" "${NEW[3]}"
php artisan migrate --path="${NEW[3]}" --force
echo "rm -f ${NEW[0]} ${NEW[1]} ${NEW[2]}   # the tables stay: they hold people's counts" >> "$BK/rollback.sh"
# 2) code
for f in "${!EXPECT[@]}"; do cp "$HERE/files/$f" "$f"; echo "  ✓ $f"; done
for f in "${NEW[0]}" "${NEW[1]}" "${NEW[2]}"; do mkdir -p "$(dirname "$f")"; cp "$HERE/files/$f" "$f"; echo "  ✓ $f (جديد)"; done
grep -q "api-2026-11-16-misbaha.php" routes/api.php || printf "\nrequire __DIR__.'/api-2026-11-16-misbaha.php';\n" >> routes/api.php
php artisan optimize:clear >/dev/null 2>&1 || true
# 3) self-test: tables exist, the tally helper runs, the new routes are registered
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $ok=Illuminate\Support\Facades\Schema::hasTable("misbaha_user_counts") && Illuminate\Support\Facades\Schema::hasTable("misbaha_guest_days") && Illuminate\Support\Facades\Schema::hasColumn("misbaha_rooms","user_id");
 App\Support\MisbahaTally::add(null,0,0);
 $r=collect(app("router")->getRoutes()->getRoutes())->map->uri()->filter(fn($u)=>str_contains($u,"me/misbaha")||$u==="api/v1/misbaha/count")->count();
 echo "  tables: ", ($ok?"✓":"✗"), "  routes: $r ", ($r>=2?"✓":"✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
