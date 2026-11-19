#!/usr/bin/env bash
# Al-Yusr API — code cooldown (16 Nov 2026, a): 30 s between two codes to one address (429 +
# retry_after), and the code leads the email subject (no two subjects alike; Gmail was filing the
# identical «رمز تأكيد البريد» subjects as spam). bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/code-cooldown-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
declare -A EXPECT=([app/Support/EmailCode.php]=f6bcb0d47d4f66b114d0504402a5d6f6 [app/Http/Controllers/Api/V1/Auth/EmailVerificationController.php]=3ec1340999ee3cda192a7e74948715d4 [app/Http/Controllers/Api/V1/Auth/PasswordResetController.php]=8e531ac355d6bd4c9474b40e7367918c)
for f in "${!EXPECT[@]}"; do now=$(md5sum "$f" | cut -d' ' -f1); [[ "$now" == "${EXPECT[$f]}" ]] || { echo "اتغير على السيرفر: $f ($now) — مفيش تعديل"; exit 1; }; php -l "$HERE/files/$f" >/dev/null; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
for f in "${!EXPECT[@]}"; do mkdir -p "$BK/$(dirname "$f")"; cp "$f" "$BK/$f"; cp "$HERE/files/$f" "$f"; echo "cp $BK/$f $f" >> "$BK/rollback.sh"; echo "  ✓ $f"; done
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap(); $e="selftest-noreply@elyusr.org"; $a1=App\Support\EmailCode::cooldown("selftest",$e); $a2=App\Support\EmailCode::cooldown("selftest",$e); echo "  cooldown: first=$a1 second=$a2 ", ($a1===0 && $a2>0 ? "✓" : "✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
