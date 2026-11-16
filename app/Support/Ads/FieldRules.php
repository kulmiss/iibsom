<?php

namespace App\Support\Ads;

use App\Models\Category;
use App\Models\CategoryField;
use Illuminate\Validation\Rule;

/** Validation rules and clean values for a category's custom fields. */
class FieldRules
{
    /** @return array<string, list<mixed>> rules keyed "fields.<key>" */
    public static function rules(Category $category): array
    {
        $rules = [];

        foreach ($category->allFields() as $field) {
            $base = [$field->required ? 'required' : 'nullable'];

            $rules["fields.{$field->key}"] = array_merge($base, match ($field->type) {
                'number' => ['integer', 'min:0', 'max:'.($field->key === 'year' ? (int) date('Y') + 1 : 99_999_999)],
                'select' => ['string', Rule::in(array_keys($field->optionList()))],
                'boolean' => ['boolean'],
                default => ['string', 'max:100'],
            });

            if ($field->key === 'year') {
                $rules["fields.{$field->key}"][] = 'min:1950';
            }
        }

        return $rules;
    }

    /**
     * Keep only known fields, cast to the right type, drop empty ones.
     *
     * @param  array<mixed>  $input
     * @return array<string, int|bool|string>
     */
    public static function clean(Category $category, array $input): array
    {
        $fields = $category->allFields()->keyBy('key');
        $clean = [];

        foreach ($fields as $key => $field) {
            $value = $input[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            $clean[$key] = match ($field->type) {
                'number' => (int) $value,
                'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                default => strip_tags(trim((string) $value)),
            };
        }

        return $clean;
    }

    /**
     * Attribute names for nice error messages ("Brand is required").
     *
     * @return array<string, string>
     */
    public static function names(Category $category): array
    {
        return $category->allFields()->mapWithKeys(fn (CategoryField $f) => ["fields.{$f->key}" => $f->label_text])->all();
    }
}
