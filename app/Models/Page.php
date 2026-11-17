<?php

namespace App\Models;

use App\Support\Cache\PageCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * @property array<string, string> $title
 * @property array<string, string> $body
 */
class Page extends Model
{
    public const CORE = ['about', 'terms', 'privacy', 'safety', 'cookies', 'copyright'];

    protected $fillable = ['slug', 'title', 'body', 'enabled'];

    protected $casts = ['title' => 'array', 'body' => 'array', 'enabled' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => PageCache::bump());
        static::deleted(fn () => PageCache::bump());
    }

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
