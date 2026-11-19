<?php

namespace App\Http\Controllers;

use App\Support\Catalog;
use Illuminate\View\View;

/**
 * "All categories" (PR 84, switch "catalog.finder"): every category and sub category on one page, with
 * how many live ads each has and a search box (English and Somali names). Same for everyone: cached for guests.
 */
class CategoryFinderController extends Controller
{
    public function __construct(private readonly Catalog $catalog) {}

    public function index(): View
    {
        $mains = [];
        foreach ($this->catalog->categories() as $main) {
            $mains[] = $main + ['visible' => $this->catalog->visibleChildren($main)];
        }

        return view('browse.categories', ['mains' => $mains]);
    }
}
