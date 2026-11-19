#!/usr/bin/env bash
# Al-Yusr API — "staff" for public endpoints becomes an allow-list (17 Nov 2026, l).
# Before: every role except donor/volunteer was staff (a new role would have seen drafts and full case data).
# After: super-admin, admin, editor, or any other role that carries permissions (donor and volunteer stay public). One file of ours (app/Support/Viewer.php), drift-checked.
# bash deploy.sh [--check]
set -euo pipefail
APP="${APP:-$HOME/domains/api.elyusr.org/public_html}"; HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BK="$HOME/backups/staff-allowlist-$(date +%Y%m%d-%H%M%S)"; cd "$APP"
F=app/Support/Viewer.php; OLD=91e2fbcb5244438774736ba14a8fabac
php -l "$HERE/files/$F" >/dev/null
[[ "$(md5sum "$F" | cut -d' ' -f1)" == "$OLD" ]] || { echo "الملف اتغيّر على السيرفر: $F (مش هكتب فوقه)"; exit 1; }
echo "الفحص تمام."; [[ "${1:-}" == "--check" ]] && exit 0
mkdir -p "$BK"; cp "$F" "$BK/Viewer.php"
printf '#!/usr/bin/env bash\ncd %s\ncp %s/Viewer.php %s\nphp artisan optimize:clear >/dev/null 2>&1 || true\n' "$APP" "$BK" "$F" > "$BK/rollback.sh"
cp "$HERE/files/$F" "$F"; echo "  ✓ $F"; php artisan optimize:clear >/dev/null 2>&1 || true
php -r 'require "vendor/autoload.php"; $a=require "bootstrap/app.php"; $a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
 foreach (App\Models\User::with("roles")->get() as $u) { $r=Illuminate\Http\Request::create("/"); $r->setUserResolver(fn()=>$u); $st=$u->roles()->whereNotIn("slug",["donor","volunteer"])->where(fn($q)=>$q->whereIn("slug",["super-admin","admin","editor"])->orWhereHas("permissions"))->exists(); echo "  ", $u->id, " ", implode(",", $u->roles->pluck("slug")->all()) ?: "-", " → ", $st ? "staff" : "public", "\n"; }' 2>&1 | head -40
echo "✅ تمام. الرجوع: bash $BK/rollback.sh"
