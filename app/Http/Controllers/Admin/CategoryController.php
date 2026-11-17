<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\Category;
use App\Models\CategoryField;
use App\Support\Admin\Activity;
use App\Support\Catalog;
use App\Support\Routing\ReservedSlugs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Categories + their custom fields, edited without code. */
class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $all = Category::query()->withCount('fields')->orderBy('sort')->get();

        return response()->json([
            'categories' => $all->whereNull('parent_id')->values()->map(fn (Category $c) => $this->row($c) + [
                'children' => $all->where('parent_id', $c->id)->values()->map(fn ($child) => $this->row($child)),
            ]),
            'icons' => $this->icons(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $category = new Category;
        $this->fill($category, $data);
        $category->sort = max(0, (int) Category::where('parent_id', $data['parent_id'] ?? null)->max('sort') + 1);
        $category->save();
        Activity::log('category.created', "Created category “{$category->label}”", $category);

        return response()->json($this->row($category), 201);
    }

    public function update(Request $request, Category $category): JsonResponse
    {
        $data = $this->validated($request, $category);
        $before = $category->only(['name', 'enabled', 'popular', 'icon', 'parent_id']);
        $this->fill($category, $data);
        $category->save();
        Activity::log('category.updated', "Changed category “{$category->label}”", $category, ['before' => $before]);

        return response()->json($this->row($category));
    }

    public function destroy(Category $category): JsonResponse
    {
        $hasAds = Ad::withTrashed()->whereIn('category_id', $category->children()->pluck('id')->push($category->id))->exists();
        if ($hasAds) {
            return response()->json(['message' => 'This category has ads. Turn it OFF instead (the ads are kept).'], 422);
        }

        $category->delete();
        Activity::log('category.deleted', "Deleted category “{$category->label}”", $category);

        return response()->json(['deleted' => true]);
    }

    /** New order: list of ids in the order they should show. */
    public function reorder(Request $request): JsonResponse
    {
        $ids = array_map('intval', $request->validate(['ids' => ['required', 'array', 'max:200'], 'ids.*' => ['integer', 'distinct']])['ids']);

        // One list at a time, and the whole list: all main categories, or all sub categories of one main.
        $parents = Category::whereIn('id', $ids)->pluck('parent_id')->unique();
        $siblings = $parents->count() === 1 ? Category::where('parent_id', $parents->first())->pluck('id')->map(fn ($id) => (int) $id)->sort()->values()->all() : [];
        $sorted = $ids;
        sort($sorted);
        if ($siblings === [] || $siblings !== $sorted) {
            return response()->json(['message' => 'The order could not be saved. Reload the page and try again.'], 422);
        }

        DB::transaction(function () use ($ids) {
            foreach (array_values($ids) as $sort => $id) {
                Category::whereKey($id)->update(['sort' => $sort]);
            }
        });
        app(Catalog::class)->flush();
        Activity::log('category.reordered', 'Changed the order of categories');

        return response()->json(['ok' => true]);
    }

    // --- Fields -------------------------------------------------------

    public function fields(Category $category): JsonResponse
    {
        return response()->json([
            'own' => $category->fields()->get()->map(fn ($f) => $this->fieldRow($f)),
            'inherited' => $category->parent ? $category->parent->fields()->get()->map(fn ($f) => $this->fieldRow($f)) : [],
            'types' => CategoryField::TYPES,
        ]);
    }

    public function storeField(Request $request, Category $category): JsonResponse
    {
        $field = new CategoryField(['category_id' => $category->id]);
        $this->fillField($field, $this->validatedField($request, $category));
        $field->category_id = $category->id;
        $field->sort = max(0, (int) $category->fields()->max('sort') + 1);
        $field->save();
        Activity::log('field.created', "Added field “{$field->label_text}” to {$category->label}", $category);

        return response()->json($this->fieldRow($field), 201);
    }

    public function updateField(Request $request, Category $category, CategoryField $field): JsonResponse
    {
        abort_unless($field->category_id === $category->id, 404);
        $this->fillField($field, $this->validatedField($request, $category, $field));
        $field->save();
        Activity::log('field.updated', "Changed field “{$field->label_text}” on {$category->label}", $category);

        return response()->json($this->fieldRow($field));
    }

    public function destroyField(Category $category, CategoryField $field): JsonResponse
    {
        abort_unless($field->category_id === $category->id, 404);
        $field->delete(); // old ads keep their value; it is just not shown or asked any more
        Activity::log('field.deleted', "Removed field “{$field->label_text}” from {$category->label}", $category);

        return response()->json(['deleted' => true]);
    }

    // --- helpers ------------------------------------------------------

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        $data = $request->validate([
            'name_en' => ['required', 'string', 'min:2', 'max:60'],
            'name_so' => ['nullable', 'string', 'max:60'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/', Rule::unique('categories', 'slug')->ignore($category?->id)],
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id'), Rule::notIn([$category?->id])],
            'icon' => ['required', Rule::in($this->icons())],
            'enabled' => ['boolean'],
            'popular' => ['boolean'],
            'has_condition' => ['boolean'],
        ]);

        // The web address: typed, kept from before, or made from the English name.
        // Checked here so a clash is a clear message, not a crash or a page nobody can open.
        $typed = filled($data['slug'] ?? null);
        $data['slug'] = $typed ? $data['slug'] : ($category->slug ?? Str::slug($data['name_en']));
        $fix = $typed ? 'Type another web address.' : 'Change the English name a little (for example add a word).';
        Validator::make($data, [
            'slug' => [
                'required',
                Rule::unique('categories', 'slug')->ignore($category?->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($fix): void {
                    if (ReservedSlugs::has((string) $value)) {
                        $fail("The site already uses the web address /{$value}. {$fix}");
                    }
                },
            ],
        ], [
            'slug.required' => 'The English name needs at least one letter or number (a-z, 0-9).',
            'slug.unique' => "Another category already uses the web address /{$data['slug']}. {$fix}",
        ])->validate();

        return $data;
    }

    /** @param  array<string, mixed>  $data */
    private function fill(Category $category, array $data): void
    {
        $category->name = array_filter(['en' => strip_tags($data['name_en']), 'so' => isset($data['name_so']) ? strip_tags($data['name_so']) : null]);
        $category->slug = $data['slug'];
        // A main category with sub categories can't become a sub category (only 2 levels).
        if (! ($category->exists && $category->children()->exists())) {
            $category->parent_id = $data['parent_id'] ?? null;
        }
        $category->icon = $data['icon'];
        $category->enabled = (bool) ($data['enabled'] ?? true);
        $category->popular = (bool) ($data['popular'] ?? false);
        $category->has_condition = (bool) ($data['has_condition'] ?? true);
    }

    /** @return array<string, mixed> */
    private function validatedField(Request $request, Category $category, ?CategoryField $field = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:40', 'regex:/^[a-z][a-z0-9_]*$/', Rule::unique('category_fields', 'key')->where('category_id', $category->id)->ignore($field?->id)],
            'type' => ['required', Rule::in(CategoryField::TYPES)],
            'label_en' => ['required', 'string', 'max:60'],
            'label_so' => ['nullable', 'string', 'max:60'],
            'options' => ['required_if:type,select', 'nullable', 'array', 'max:100'],
            'options.*' => ['string', 'max:60'],
            'unit' => ['nullable', 'string', 'max:20'],
            'required' => ['boolean'],
            'filterable' => ['boolean'],
            'enabled' => ['boolean'],
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function fillField(CategoryField $field, array $data): void
    {
        $field->key = $data['key'];
        $field->type = $data['type'];
        $field->label = array_filter(['en' => strip_tags($data['label_en']), 'so' => isset($data['label_so']) ? strip_tags($data['label_so']) : null]);
        $options = null;
        if ($data['type'] === 'select') {
            $options = [];
            $seen = [];
            foreach (is_array($data['options'] ?? null) ? $data['options'] : [] as $label) {
                $label = is_string($label) ? trim(strip_tags($label)) : '';
                if ($label === '' || isset($seen[$label])) {
                    continue;
                }
                $seen[$label] = true;
                $options[] = ['value' => Str::slug($label) ?: Str::random(6), 'label' => ['en' => $label]];
            }
        }
        $field->options = $options;
        $field->unit = isset($data['unit']) ? strip_tags($data['unit']) : null;
        $field->required = (bool) ($data['required'] ?? false);
        $field->filterable = (bool) ($data['filterable'] ?? true);
        $field->enabled = (bool) ($data['enabled'] ?? true);
    }

    /** @return array<string, mixed> */
    private function row(Category $c): array
    {
        return [
            'id' => $c->id,
            'parent_id' => $c->parent_id,
            'name_en' => $c->name['en'] ?? '',
            'name_so' => $c->name['so'] ?? null,
            'slug' => $c->slug,
            'icon' => $c->icon,
            'enabled' => $c->enabled,
            'popular' => $c->popular,
            'has_condition' => $c->has_condition,
            'ads_count' => $c->ads_count,
            'fields_count' => $c->fields_count ?? $c->fields()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function fieldRow(CategoryField $f): array
    {
        return [
            'id' => $f->id,
            'key' => $f->key,
            'type' => $f->type,
            'label_en' => $f->label['en'] ?? '',
            'label_so' => $f->label['so'] ?? null,
            'options' => collect($f->options ?? [])->map(fn ($o) => $o['label']['en'] ?? $o['value'])->values(),
            'unit' => $f->unit,
            'required' => $f->required,
            'filterable' => $f->filterable,
            'enabled' => $f->enabled,
        ];
    }

    /** @return list<string> icon names available in resources/svg/icons */
    private function icons(): array
    {
        return array_values(collect(glob(resource_path('svg/icons/*.svg')) ?: [])->map(fn ($p) => basename($p, '.svg'))
            ->reject(fn ($n) => in_array($n, ['chevron-down', 'chevron-right', 'search', 'plus', 'square-plus', 'bell', 'bookmark', 'message-circle', 'list', 'globe', 'circle-help', 'pencil', 'check', 'refresh-cw', 'trash-2', 'eye', 'x', 'clock', 'history'], true))
            ->all());
    }
}
