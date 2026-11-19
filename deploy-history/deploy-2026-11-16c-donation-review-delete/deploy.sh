#!/usr/bin/env bash
# Al-Yusr API — donations: «تم الاطلاع» + safe delete (16 Nov 2026, c).
#  - donations get reviewed_at/reviewed_by and soft deletes (own migration, run by path only)
#  - DELETE donations/{id}: only pending/failed/cancelled, the caller must send confirm_reference = the donation's
#    reference, soft delete + activity log (paid/refunded donations are never deletable)
#  - PATCH donations/{id}/reviewed (permission donations.update)
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/donation-review-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
declare -A EXPECT=([app/Models/Donation.php]=07225a3720b0b8a5fa1fba5afee22698 [app/Http/Resources/DonationResource.php]=bf3e4907bcd78344c7c2639be4f20b09 [app/Http/Controllers/Api/V1/Donation/DonationController.php]=99acf102ed39293969e48cc1a55a79b6)
NEW=(app/Http/Controllers/Api/V1/Donation/DonationReviewController.php routes/api-2026-11-16-donations.php database/migrations/2026_11_16_110000_donations_review_and_soft_delete.php)
for f in "${!EXPECT[@]}"; do now=$(md5sum "$f" | cut -d' ' -f1); [[ "$now" == "${EXPECT[$f]}" ]] || { echo "اتغير على السيرفر: $f ($now) — مفيش تعديل"; exit 1; }; done
for f in "${!EXPECT[@]}" "${NEW[@]}"; do php -l "$HERE/files/$f" >/dev/null; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
cp routes/api.php "$BK/api.php"; echo "cp $BK/api.php routes/api.php" >> "$BK/rollback.sh"
for f in "${!EXPECT[@]}"; do mkdir -p "$BK/$(dirname "$f")"; cp "$f" "$BK/$f"; echo "cp $BK/$f $f" >> "$BK/rollback.sh"; done
cp "$HERE/files/${NEW[2]}" "${NEW[2]}"
php artisan migrate --path="${NEW[2]}" --force
echo "rm -f ${NEW[0]} ${NEW[1]}   # the new columns stay (harmless)" >> "$BK/rollback.sh"
for f in "${!EXPECT[@]}"; do cp "$HERE/files/$f" "$f"; echo "  ✓ $f"; done
for f in "${NEW[0]}" "${NEW[1]}"; do mkdir -p "$(dirname "$f")"; cp "$HERE/files/$f" "$f"; echo "  ✓ $f (جديد)"; done
grep -q "api-2026-11-16-donations.php" routes/api.php || printf "\nrequire __DIR__.'/api-2026-11-16-donations.php';\n" >> routes/api.php
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $S=Illuminate\Support\Facades\Schema::class; $ok=$S::hasColumn("donations","reviewed_at") && $S::hasColumn("donations","deleted_at");
 $n=App\Models\Donation::count(); $r=collect(app("router")->getRoutes()->getRoutes())->map->uri()->filter(fn($u)=>str_contains($u,"donations/{donation}/reviewed"))->count();
 echo "  columns: ", ($ok?"✓":"✗"), "  donations visible: $n  reviewed route: $r ", ($r>=1?"✓":"✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
