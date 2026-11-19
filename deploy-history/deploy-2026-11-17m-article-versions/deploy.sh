#!/usr/bin/env bash
# Al-Yusr API — article versions for the blog editor (17 Nov 2026, m).
#  - new table blog_post_revisions (own migration, run by path only)
#  - POST admin/blog-revisions (keep the version about to be replaced) and GET admin/blog-revisions?slug=
#  - only new files + one `require` line in routes/api.php: no file of the other developer is rewritten
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/article-versions-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
NEW=(app/Http/Controllers/Api/V1/Extras/BlogRevisionController.php routes/api-2026-11-17-article-versions.php database/migrations/2026_11_17_120000_create_blog_post_revisions.php)
for f in "${NEW[@]}"; do php -l "$HERE/files/$f" >/dev/null; done
[[ -f app/Support/StaffAccess.php && -d app/Http/Controllers/Api/V1/Extras ]] || { echo "ملفات أساسية ناقصة على السيرفر"; exit 1; }
for f in "${NEW[@]:0:2}"; do [[ ! -e "$f" ]] || { echo "الملف موجود بالفعل: $f (مش هكتب فوقه)"; exit 1; }; done
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; printf '#!/usr/bin/env bash\ncd %s\n' "$APP" > "$BK/rollback.sh"
cp routes/api.php "$BK/api.php"
echo "# remove only our require line (routes/api.php may have changed since)" >> "$BK/rollback.sh"
echo "sed -i \"/api-2026-11-17-article-versions.php/d\" routes/api.php" >> "$BK/rollback.sh"
mkdir -p "$(dirname "${NEW[2]}")"; cp "$HERE/files/${NEW[2]}" "${NEW[2]}"
php artisan migrate --path="${NEW[2]}" --force
echo "rm -f ${NEW[0]} ${NEW[1]}   # the versions table stays (harmless)" >> "$BK/rollback.sh"
echo "php artisan optimize:clear >/dev/null 2>&1 || true" >> "$BK/rollback.sh"
for f in "${NEW[0]}" "${NEW[1]}"; do mkdir -p "$(dirname "$f")"; cp "$HERE/files/$f" "$f"; echo "  ✓ $f (جديد)"; done
grep -q "api-2026-11-17-article-versions.php" routes/api.php || printf "\nrequire __DIR__.'/api-2026-11-17-article-versions.php';\n" >> routes/api.php
php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 $ok=Illuminate\Support\Facades\Schema::hasTable("blog_post_revisions");
 $r=collect(app("router")->getRoutes()->getRoutes())->map->uri()->filter(fn($u)=>str_contains($u,"admin/blog-revisions"))->count();
 echo "  table: ", ($ok?"✓":"✗"), "  routes: $r ", ($r>=2?"✓":"✗"), "\n";'
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
