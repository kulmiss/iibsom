<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class Page extends Model
{
    public const CORE = ['about', 'terms', 'privacy', 'safety'];

    protected $fillable = ['slug', 'title', 'body', 'enabled'];

    protected $casts = ['title' => 'array', 'body' => 'array', 'enabled' => 'boolean'];

    public function titleText(): string
    {
        return translated($this->title);
    }

    /** Markdown → HTML, with any HTML typed by staff escaped (XSS-safe). */
    public function bodyHtml(): HtmlString
    {
        return new HtmlString(Str::markdown(translated($this->body), [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 10,
        ]));
    }
}
