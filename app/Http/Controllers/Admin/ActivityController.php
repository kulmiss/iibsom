<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminActivity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'action' => ['nullable', 'string', 'max:60'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
        ]);

        $page = AdminActivity::query()
            ->with('user:id,name,role')
            ->when(! feature('site.demo_data'), fn ($q) => $q->where('is_demo', false))
            ->when($request->search, fn ($q, $s) => $q->where('summary', 'like', '%'.addcslashes($s, '%_').'%'))
            ->when($request->action, fn ($q, $a) => $q->where('action', 'like', addcslashes($a, '%_').'%'))
            ->latest('id')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($page->items())->map(fn (AdminActivity $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'summary' => $a->summary,
                'subject' => $a->subject_type ? "{$a->subject_type} #{$a->subject_id}" : null,
                'by' => $a->user->name ?? 'System',
                'at' => $a->created_at->toIso8601String(),
            ]),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'actions' => AdminActivity::query()->distinct()->orderBy('action')->limit(100)->pluck('action'),
        ]);
    }
}
