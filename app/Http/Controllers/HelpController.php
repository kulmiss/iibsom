<?php

namespace App\Http\Controllers;

use App\Support\Help\HelpCentre;
use Illuminate\View\View;

class HelpController extends Controller
{
    public function index(): View
    {
        return view('help.index', ['articles' => HelpCentre::articles(app()->getLocale())]);
    }

    public function show(string $article): View
    {
        $found = HelpCentre::article(app()->getLocale(), $article);
        abort_if($found === null, 404);

        return view('help.show', [
            'slug' => $article,
            'article' => $found,
            'others' => array_values(array_filter(HelpCentre::articles(app()->getLocale()), fn ($a) => $a['slug'] !== $article)),
        ]);
    }
}
