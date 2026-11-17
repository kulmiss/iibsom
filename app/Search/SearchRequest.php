<?php

namespace App\Search;

use Illuminate\Http\Request;

/** Typed search filters from an already validated request (website and API use the same rules). */
final class SearchRequest
{
    /** @return array{q: ?string, min: ?int, max: ?int, condition: ?string, sort: string} */
    public static function basic(Request $request): array
    {
        return [
            'q' => $request->filled('q') ? $request->string('q')->trim()->toString() : null,
            'min' => $request->filled('min') ? $request->integer('min') : null,
            'max' => $request->filled('max') ? $request->integer('max') : null,
            'condition' => $request->filled('condition') ? $request->string('condition')->toString() : null,
            'sort' => $request->filled('sort') ? $request->string('sort')->toString() : 'newest',
        ];
    }

    /**
     * @param  iterable<mixed>  $ids
     * @return list<int>
     */
    public static function ids(iterable $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $out[] = (int) $id;
            }
        }

        return $out;
    }
}
