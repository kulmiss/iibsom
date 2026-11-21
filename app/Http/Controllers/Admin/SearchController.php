<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\Page;
use App\Models\User;
use App\Support\Features\FeatureManager;
use App\Support\Phone\SomaliPhone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The admin's global search (PR 102): one box finds ads, people, categories, feature switches and pages.
 * Each group only for staff who may open it, at most 5 results each, so it stays fast while typing.
 * Pages of the admin menu and settings are found in the browser (no request needed).
 */
class SearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->validate(['q' => ['required', 'string', 'min:2', 'max:100']])['q']);
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $like = '%'.addcslashes($q, '%_\\').'%';
        $number = ltrim($q, '#');
        $groups = [];
        // Names and page titles are JSON (English + Somali); json_unquote gives a case-sensitive text, so both sides are lowered.

        if ($user->hasPermission('ads.view')) {
            $ads = Ad::withTrashed()->with('category:id,name')
                ->when(! feature('site.demo_data'), fn ($b) => $b->where('is_demo', false))
                ->where(fn ($b) => ctype_digit($number) ? $b->whereKey((int) $number)->orWhere('title', 'like', $like) : $b->where('title', 'like', $like))
                ->orderByDesc('id')->limit(self::PER_GROUP)->get(['id', 'title', 'status', 'price', 'category_id', 'deleted_at']);
            $groups['ads'] = $ads->map(fn (Ad $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'hint' => '#'.$a->id.' · '.($a->category->label ?? '').' · '.($a->deleted_at ? 'deleted' : $a->status->value).($a->price !== null ? ' · $'.number_format((float) $a->price) : ''),
            ])->all();
        }

        if ($user->hasPermission('users.view')) {
            $phone = SomaliPhone::normalize($q);
            // A "confirm my number" code from a WhatsApp message (PR 108), with or without the "IIB-".
            $code = preg_match('/^(IIB-?)?([A-Z0-9]{5})$/i', trim($q), $m) ? 'IIB-'.strtoupper($m[2]) : null;
            $people = User::query()
                ->when(! feature('site.demo_data'), fn ($b) => $b->where('is_demo', false))
                ->where(fn ($b) => $b->where('name', 'like', $like)->orWhere('email', 'like', $like)
                    ->when($phone, fn ($b) => $b->orWhere('phone', $phone))
                    ->when($code, fn ($b) => $b->orWhere('phone_confirm_code', $code))
                    ->when(ctype_digit($number), fn ($b) => $b->orWhere('id', (int) $number)))
                ->orderByDesc('id')->limit(self::PER_GROUP)->get(['id', 'name', 'email', 'role', 'banned_at']);
            $groups['users'] = $people->map(fn (User $u) => [
                'id' => $u->id,
                'title' => $u->name ?: $u->email,
                'hint' => '#'.$u->id.' · '.$u->email.($u->isStaff() ? ' · staff' : '').($u->banned_at ? ' · banned' : ''),
                'staff' => $u->isStaff(),
            ])->all();
        }

        if ($user->hasPermission('categories.manage')) {
            $groups['categories'] = Category::with('parent:id,name')->where(fn ($b) => $b->whereRaw("lower(json_unquote(json_extract(name, '$.en'))) like ?", [mb_strtolower($like)])->orWhereRaw("lower(json_unquote(json_extract(name, '$.so'))) like ?", [mb_strtolower($like)]))->orderBy('parent_id')->orderBy('sort')
                ->limit(self::PER_GROUP)->get(['id', 'name', 'parent_id', 'slug'])
                ->map(fn (Category $c) => ['id' => $c->id, 'title' => $c->label, 'hint' => $c->parent ? 'in '.$c->parent->label : 'main category'])->all();
        }

        if ($user->hasPermission('features.manage')) {
            $needle = mb_strtolower($q);
            $groups['switches'] = collect(app(FeatureManager::class)->grouped())->flatten(1)
                ->filter(fn (array $f) => str_contains(mb_strtolower($f['label'].' '.$f['key'].' '.$f['description']), $needle))
                ->sortBy(fn (array $f) => str_contains(mb_strtolower($f['label']), $needle) ? 0 : 1)
                ->take(self::PER_GROUP)->values()
                ->map(fn (array $f) => ['id' => $f['key'], 'title' => $f['label'], 'hint' => $f['enabled'] ? 'ON' : 'OFF'])->all();
        }

        if ($user->hasPermission('pages.manage')) {
            $groups['pages'] = Page::where(fn ($b) => $b->whereRaw("lower(json_unquote(json_extract(title, '$.en'))) like ?", [mb_strtolower($like)])->orWhereRaw("lower(json_unquote(json_extract(title, '$.so'))) like ?", [mb_strtolower($like)])->orWhere('slug', 'like', $like))->limit(self::PER_GROUP)->get(['id', 'slug', 'title'])
                ->map(fn (Page $p) => ['id' => $p->id, 'title' => $p->titleText(), 'hint' => '/'.$p->slug])->all();
        }

        return response()->json(['q' => $q, 'groups' => array_filter($groups)]);
    }
}
