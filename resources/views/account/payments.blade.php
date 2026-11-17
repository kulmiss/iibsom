@extends('layouts.app')

@section('title', __('ads.boost.payments'))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('ads.boost.payments') }}</h1>

        @if ($payments->isEmpty())
            <p class="mt-6 rounded-xl border border-dashed border-stone-300 bg-white p-8 text-center text-stone-600">{{ __('ads.boost.no_payments') }}</p>
        @else
            <ul class="mt-6 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
                @foreach ($payments as $p)
                    <li class="flex flex-wrap items-start justify-between gap-2 px-5 py-4">
                        <div class="min-w-0">
                            <p class="font-semibold">
                                {{ \App\Support\Money\Boosts::label($p->package) }} · {{ \App\Models\Payment::money($p->amount_cents) }}
                                @if ($p->is_test)<span class="ms-1 rounded bg-amber-100 px-1.5 py-0.5 text-xs font-semibold text-amber-900">{{ __('ads.boost.test') }}</span>@endif
                            </p>
                            <p class="truncate text-sm text-stone-600">{{ $p->ad?->title }}</p>
                            <p class="text-xs text-stone-500">{{ config('boosts.methods.'.$p->method) }} · <span class="font-mono">{{ $p->reference }}</span> · {{ $p->created_at?->translatedFormat('j M Y') }}</p>
                        </div>
                        <p class="text-sm font-semibold {{ $p->status === 'approved' ? 'text-green-700' : ($p->status === 'rejected' ? 'text-red-700' : 'text-stone-600') }}">
                            @if ($p->status === 'approved') {{ __('ads.boost.status_approved') }}
                            @elseif ($p->status === 'rejected') {{ __('ads.boost.status_rejected', ['reason' => $p->reason]) }}
                            @else {{ __('ads.boost.status_pending') }}
                            @endif
                        </p>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $payments->links() }}</div>
        @endif
    </section>
@endsection
