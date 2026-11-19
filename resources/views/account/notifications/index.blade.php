@extends('layouts.app')

@section('title', __('notify.title'))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h1 class="text-2xl font-bold">{{ __('notify.title') }}</h1>
            <a href="{{ lroute('notifications.settings') }}" class="chip gap-1.5 bg-white ring-stone-200">{{ icon('settings', 'size-4') }} {{ __('notify.settings_link') }}</a>
        </div>

        @if ($items->isEmpty())
            <div class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">
                {{ icon('bell', 'mx-auto size-8 text-stone-400') }}
                <p class="mt-2">{{ __('notify.empty') }}</p>
            </div>
        @else
            <ul class="mt-4 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200" data-inbox>
                @foreach ($items as $item)
                    @php($url = \App\Support\Notify\Inbox::url($item))
                    @php($new = $unreadIds->has($item->id))
                    <li class="{{ $new ? 'bg-brand-50/60' : '' }}">
                        @if ($url)<a href="{{ $url }}" class="flex items-start gap-3 px-4 py-3.5 hover:bg-stone-50">@else<div class="flex items-start gap-3 px-4 py-3.5">@endif
                            <span class="mt-0.5 flex size-9 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700">{{ icon(\App\Support\Notify\Inbox::icon($item->kind), 'size-5') }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm leading-snug [overflow-wrap:anywhere]">{{ \App\Support\Notify\Inbox::text($item) }}</span>
                                <span class="mt-0.5 block text-xs text-stone-500">{{ $item->created_at?->diffForHumans() }}@if ($new) · <strong class="text-brand-700">{{ __('notify.new') }}</strong>@endif</span>
                            </span>
                        @if ($url)</a>@else</div>@endif
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $items->links() }}</div>
        @endif
    </section>
@endsection
