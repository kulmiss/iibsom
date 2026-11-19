<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Support\Admin\Activity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Messages from the contact form (switch "site.contact_form"). Staff answer by email and mark them done. */
class ContactMessageController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $done = $request->query('show') === 'done';
        $page = ContactMessage::query()->with('handler:id,name')
            ->when($done, fn ($q) => $q->whereNotNull('handled_at'), fn ($q) => $q->whereNull('handled_at'))
            ->latest('id')->paginate(20);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ContactMessage $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'email' => $m->email,
                'topic' => __('site.contact.topics.'.$m->topic, [], 'en'),
                'message' => $m->message,
                'language' => $m->locale === 'so' ? 'Somali' : 'English',
                'created_at' => $m->created_at?->toIso8601String(),
                'handled_at' => $m->handled_at?->toIso8601String(),
                'handled_by' => $m->handler?->name,
            ])->values(),
            'total' => $page->total(),
            'last_page' => $page->lastPage(),
            'open' => ContactMessage::whereNull('handled_at')->count(),
        ]);
    }

    public function done(Request $request, ContactMessage $message): JsonResponse
    {
        $message->forceFill(['handled_at' => now(), 'handled_by' => $this->authUser($request)->id])->save();
        Activity::log('contact.done', "Marked a contact message from {$message->name} as done");

        return response()->json(['done' => true]);
    }

    public function reopen(ContactMessage $message): JsonResponse
    {
        $message->forceFill(['handled_at' => null, 'handled_by' => null])->save();
        Activity::log('contact.reopened', "Opened again a contact message from {$message->name}");

        return response()->json(['done' => false]);
    }
}
