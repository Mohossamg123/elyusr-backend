#!/usr/bin/env bash
# Al-Yusr API — code reach (l): a verification code is refused with a clear message when the
# account's email domain can't receive mail (e.g. admin@alyusr.com); the reset message follows the
# code lifetime; ~/mail-setup.sh sends its test in the site's email design. bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/code-reach-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
declare -A EXPECT=([app/Support/EmailCode.php]=4621c9a19a832e2fe66d6a4b1f3d659b [app/Http/Controllers/Api/V1/Auth/EmailVerificationController.php]=51ff81458239257e31a56385b1cd1d80 [app/Http/Controllers/Api/V1/Auth/PasswordResetController.php]=5ffa9b226eb06509bc67fe463be8bb16)
for f in "${!EXPECT[@]}"; do now=$(md5sum "$f" | cut -d' ' -f1); [[ "$now" == "${EXPECT[$f]}" ]] || { echo "اتغير على السيرفر: $f ($now) — مفيش تعديل"; exit 1; }; php -l "$HERE/files/$f" >/dev/null; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
for f in "${!EXPECT[@]}"; do mkdir -p "$BK/$(dirname "$f")"; cp "$f" "$BK/$f"; cp "$HERE/files/$f" "$f"; echo "cp $BK/$f $f" >> "$BK/rollback.sh"; echo "  ✓ $f"; done
cp "$HOME/mail-setup.sh" "$BK/mail-setup.sh" 2>/dev/null && echo "cp $BK/mail-setup.sh $HOME/mail-setup.sh" >> "$BK/rollback.sh" || true
cp "$HERE/mail-setup.sh" "$HOME/mail-setup.sh"; echo "  ✓ ~/mail-setup.sh"
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); foreach (["x@alyusr.com"=>false,"x@gmail.com"=>true,"x@elyusr.org"=>true] as $m=>$want) echo "  ", $m, " → ", App\Support\EmailCode::reachable($m) ? "يستقبل" : "ما بيستقبلش", (App\Support\EmailCode::reachable($m)===$want ? " ✓" : " ✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
