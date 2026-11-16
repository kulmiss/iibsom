<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PageAdminController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Page::orderBy('id')->get()->map(fn (Page $p) => $this->row($p)));
    }

    public function update(Request $request, Page $page): JsonResponse
    {
        $data = $request->validate([
            'title_en' => ['required', 'string', 'max:120'],
            'title_so' => ['nullable', 'string', 'max:120'],
            'body_en' => ['required', 'string', 'max:30000'],
            'body_so' => ['nullable', 'string', 'max:30000'],
            'enabled' => ['boolean'],
        ]);

        $page->title = array_filter(['en' => $data['title_en'], 'so' => $data['title_so'] ?? null]);
        $page->body = array_filter(['en' => $data['body_en'], 'so' => $data['body_so'] ?? null]);
        $page->enabled = (bool) ($data['enabled'] ?? true);
        $page->forceFill(['updated_by' => $this->authUser($request)->id])->save();
        Activity::log('page.updated', "Edited page “{$page->titleText()}”", $page);

        return response()->json($this->row($page));
    }

    /** Preview Markdown exactly as the site will show it (escaped HTML). */
    public function preview(Request $request): JsonResponse
    {
        $body = $request->validate(['body' => ['nullable', 'string', 'max:30000']])['body'] ?? '';

        return response()->json(['html' => (string) (new Page(['body' => ['en' => $body]]))->bodyHtml()]);
    }

    /** @return array<string, mixed> */
    private function row(Page $p): array
    {
        return [
            'id' => $p->id, 'slug' => $p->slug, 'url' => url('/'.$p->slug), 'enabled' => $p->enabled,
            'title_en' => $p->title['en'] ?? '', 'title_so' => $p->title['so'] ?? null,
            'body_en' => $p->body['en'] ?? '', 'body_so' => $p->body['so'] ?? null,
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }
}
