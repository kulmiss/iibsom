<?php

namespace App\Search;

use App\Models\Ad;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Searching ads, behind an interface: the database now (works on shared
 * hosting), Meilisearch later on a VPS by changing SEARCH_DRIVER in .env.
 */
interface AdSearch
{
    /**
     * @param  array{q?: ?string, category_ids?: ?list<int>, city_id?: ?int, min?: ?int, max?: ?int, condition?: ?string, fields?: array<string, mixed>, sort?: ?string, published_after?: ?\DateTimeInterface}  $filters
     * @return LengthAwarePaginator<int, Ad>
     */
    public function search(array $filters, int $perPage = 24, int $page = 1): LengthAwarePaginator;
}
