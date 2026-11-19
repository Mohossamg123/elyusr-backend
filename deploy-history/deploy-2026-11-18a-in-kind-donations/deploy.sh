#!/usr/bin/env bash
# Al-Yusr API — in-kind donations + pickup-agent rules (18 Nov 2026, a). bash deploy.sh [--check] [--force]
#  - new columns on donations: is_in_kind, in_kind_category, in_kind_description (own migration, run by path only, safe to re-run)
#  - POST donations/submit: in-kind => amount 0 allowed, category required, pickup agent method only,
#    and pickup_governorate / pickup_city / pickup_address required whenever the method is cash_pickup
#  - DonationResource returns the 3 new fields (pickup block was already there)
# Replaces 3 files listed in manifest.txt (md5 = the version before this deploy). If one was edited by hand on the server,
# nothing is changed unless you pass --force (a backup + rollback.sh are written before the first copy either way).
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/in-kind-18a-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
MIG=database/migrations/2026_11_18_100000_add_in_kind_fields_to_donations_table.php
CHECK=0; FORCE=0
for a in "$@"; do [[ "$a" == "--check" ]] && CHECK=1; [[ "$a" == "--force" ]] && FORCE=1; done
bad=0
while read -r f old; do
  now=$(md5sum "$f" | cut -d' ' -f1); new=$(md5sum "$HERE/files/$f" | cut -d' ' -f1)
  if [[ "$now" != "$old" && "$now" != "$new" ]]; then echo "اتغيّر على السيرفر: $f ($now)"; bad=1; fi
  php -l "$HERE/files/$f" >/dev/null
done < "$HERE/manifest.txt"
php -l "$HERE/files/$MIG" >/dev/null
[[ -f app/Models/PaymentMethod.php ]] || { echo "app/Models/PaymentMethod.php ناقص على السيرفر"; exit 1; }
if [[ $bad == 1 && $FORCE == 0 ]]; then echo "فيه ملفات اتعدّلت على السيرفر. شغّل --check للتفاصيل، أو --force (بياخد نسخة احتياطية الأول)."; exit 1; fi
echo "الفحص تمام ($(wc -l < "$HERE/manifest.txt") ملفات + migration)."; [[ $CHECK == 1 ]] && exit 0
mkdir -p "$BK"; { echo '#!/usr/bin/env bash'; echo "cd $APP"; } > "$BK/rollback.sh"
while read -r f old; do
  mkdir -p "$BK/$(dirname "$f")"; cp "$f" "$BK/$f"; echo "cp $BK/$f $f" >> "$BK/rollback.sh"
done < "$HERE/manifest.txt"
if [[ ! -e "$MIG" ]]; then
  mkdir -p "$(dirname "$MIG")"; cp "$HERE/files/$MIG" "$MIG"; echo "rm -f $MIG   # the 3 columns stay on donations (harmless)" >> "$BK/rollback.sh"
fi
php artisan migrate --path="$MIG" --force
echo 'php artisan optimize:clear >/dev/null 2>&1 || true' >> "$BK/rollback.sh"
while read -r f old; do cp "$HERE/files/$f" "$f"; php -l "$f" >/dev/null || { bash "$BK/rollback.sh"; echo "اتكسر $f — رجّعت كل حاجة"; exit 1; }; echo "  ✓ $f"; done < "$HERE/manifest.txt"
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $s=Illuminate\Support\Facades\Schema::class;
 foreach(["is_in_kind","in_kind_category","in_kind_description"] as $c){ echo "  donations.", $c, ": ", ($s::hasColumn("donations",$c)?"✓":"✗"), "\n"; }'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
