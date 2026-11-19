#!/usr/bin/env bash
# Al-Yusr API — security hardening (16 Nov 2026, f). bash deploy.sh [--check]
# Source: independent security-audit run-1 (see docs/security/). Replaces 9 files; each must still be the version listed in manifest.txt
# (or already the new one), otherwise NOTHING is changed. Backup + rollback.sh are written before the first copy.
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/security-16f-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
bad=0
while read -r f old; do
  now=$(md5sum "$f" | cut -d' ' -f1); new=$(md5sum "$HERE/files/$f" | cut -d' ' -f1)
  if [[ "$now" != "$old" && "$now" != "$new" ]]; then echo "اتغيّر على السيرفر (محمد؟): $f ($now) — مفيش تعديل"; bad=1; fi
  php -l "$HERE/files/$f" >/dev/null
done < "$HERE/manifest.txt"
[[ $bad == 0 ]] || exit 1
echo "الفحص تمام ($(wc -l < "$HERE/manifest.txt") ملفات)."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; { echo '#!/usr/bin/env bash'; echo "cd $APP"; } > "$BK/rollback.sh"
while read -r f old; do
  mkdir -p "$BK/$(dirname "$f")"; cp "$f" "$BK/$f"; echo "cp $BK/$f $f" >> "$BK/rollback.sh"
done < "$HERE/manifest.txt"
echo 'php artisan optimize:clear >/dev/null 2>&1 || true' >> "$BK/rollback.sh"
while read -r f old; do cp "$HERE/files/$f" "$f"; php -l "$f" >/dev/null || { bash "$BK/rollback.sh"; echo "اتكسر $f — رجّعت كل حاجة"; exit 1; }; done < "$HERE/manifest.txt"
php artisan optimize:clear >/dev/null 2>&1 || true
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
