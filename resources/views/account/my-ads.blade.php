@extends('layouts.app')

@section('title', __('ads.mine.title'))

@section('content')
    <section class="mx-auto max-w-3xl px-4 py-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="min-w-0 text-2xl font-bold">{{ __('ads.mine.title') }}</h1>
            @feature('ads.posting')
                <a href="{{ lroute('post') }}" class="btn-sell shrink-0">{{ icon('plus', 'size-4') }} {{ __('ads.mine.post_first') }}</a>
            @endfeature
        </div>

        {{-- Tabs --}}
        <nav class="-mx-4 mt-4 flex gap-1 overflow-x-auto px-4" aria-label="{{ __('ads.mine.title') }}">
            @foreach (\App\Http\Controllers\Account\MyAdsController::TABS as $t)
                <a href="{{ lroute('account.ads', ['tab' => $t]) }}" @if ($t === $tab) aria-current="page" @endif
                   class="shrink-0 rounded-full px-4 py-2 text-sm font-semibold {{ $t === $tab ? 'bg-brand-700 text-white' : 'bg-white text-stone-700 ring-1 ring-stone-200' }}">
                    {{ __('ads.mine.tabs.'.$t) }} <span class="ml-0.5 opacity-75">{{ $counts[$t] }}</span>
                </a>
            @endforeach
        </nav>

        <ul class="mt-4 space-y-3">
            @forelse ($ads as $ad)
                @php($cover = $ad->cover)
                <li class="flex gap-3 rounded-2xl bg-white p-3 shadow-sm ring-1 ring-stone-200" data-my-ad="{{ $ad->id }}">
                    <div class="size-20 shrink-0 overflow-hidden rounded-xl bg-stone-100 sm:size-24">
                        @if ($cover)
                            <img src="{{ $cover->url('thumb') }}" alt="" loading="lazy" class="size-full object-cover" width="96" height="96">
                        @else
                            <span class="flex size-full items-center justify-center bg-gradient-to-br from-brand-50 to-stone-100 text-brand-300">{{ icon($ad->category->icon ?? 'tag', 'size-8') }}</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate font-semibold">{{ $ad->title }}</p>
                        <p class="text-sm font-bold text-brand-800">{{ $ad->priceLabel() }}</p>
                        <p class="text-xs text-stone-500">
                            {{ $ad->status->label() }}
                            @if ($ad->closed_reason && in_array($ad->closed_reason, \App\Http\Controllers\Account\MyAdsController::CLOSE_REASONS, true)) · {{ __('ads.mine.closed_as', ['reason' => __('ads.close.'.$ad->closed_reason)]) }}@endif
                            @if ($ad->status === \App\Enums\AdStatus::Sold && $ad->sold_at) · {{ __('ads.mine.sold_on', ['date' => mdate($ad->sold_at)]) }}
                            @elseif ($ad->expires_at && $ad->expires_at->isPast()) · {{ __('ads.mine.ended_on', ['date' => mdate($ad->expires_at)]) }}
                            @elseif ($ad->expires_at) · {{ __('ads.mine.ends', ['date' => mdate($ad->expires_at)]) }}
                            @endif
                        </p>
                        @feature('ad_stats')
                            @php($st = $stats[$ad->id] ?? ['views' => 0, 'phone' => 0, 'whatsapp' => 0, 'call' => 0])
                            <p class="mt-1 flex flex-wrap gap-x-3 text-xs text-stone-600" aria-label="{{ __('ads.stats.last_30') }}">
                                <span>{{ icon('eye', 'inline size-3.5') }} {{ trans_choice('ads.stats.views_n', $st['views'], ['count' => number_format($st['views'])]) }}</span>
                                <span>{{ icon('phone', 'inline size-3.5') }} {{ trans_choice('ads.stats.contacts_n', $st['phone'] + $st['whatsapp'] + $st['call'], ['count' => number_format($st['phone'] + $st['whatsapp'] + $st['call'])]) }}</span>
                            </p>
                        @endfeature
                        @if ($ad->status === \App\Enums\AdStatus::Rejected)
                            <p class="mt-1 rounded-lg bg-red-50 px-2 py-1 text-xs text-red-800">
                                @if ($ad->rejection_reason){{ __('ads.mine.rejected_reason', ['reason' => $ad->rejection_reason]) }}<br>@endif
                                {{ __('ads.mine.rejected_help') }}
                                @if (feature('help.posting_rules') && feature('help.centre'))
                                    <a href="{{ lroute('help.show', ['article' => 'posting-rules']) }}" class="font-semibold underline">{{ __('ads.mine.posting_rules') }}</a>
                                @endif
                            </p>
                        @endif

                        <div class="mt-2 flex flex-wrap gap-2 text-sm">
                            <a href="{{ $ad->url() }}" class="btn-chip">{{ __('ads.mine.view') }}</a>
                            @if (feature('boosts') && $ad->isLive())
                                <a href="{{ lroute('account.boost', ['ad' => $ad->id]) }}" class="btn-chip">{{ icon('rocket', 'size-3.5') }} {{ $ad->activeBoost() ? \App\Support\Money\Boosts::label($ad->activeBoost()) : __('ads.boost.button') }}</a>
                            @endif
                            @feature('ad_stats')
                                <a href="{{ lroute('account.ads.stats', ['ad' => $ad->id]) }}" class="btn-chip">{{ icon('chart-column', 'size-3.5') }} {{ __('ads.stats.open') }}</a>
                            @endfeature
                            @if ($ad->status !== \App\Enums\AdStatus::Sold)
                                <a href="{{ lroute('account.ads.edit', ['ad' => $ad->id]) }}" class="btn-chip">{{ icon('pencil', 'size-3.5') }} {{ __('ads.mine.edit') }}</a>
                            @endif
                            @if ($ad->isLive() && feature('ads.free_bump'))
                                @if (\App\Http\Controllers\Account\MyAdsController::canBump($ad))
                                    <form method="post" action="{{ lroute('account.ads.bump', ['ad' => $ad->id]) }}">
                                        @csrf
                                        <button class="btn-chip bg-brand-700 text-white ring-brand-700">{{ icon('arrow-up-circle', 'size-3.5') }} {{ __('ads.mine.bump') }}</button>
                                    </form>
                                @else
                                    <p class="order-last flex basis-full items-center gap-1 text-xs text-stone-500" data-bump-next>{{ icon('arrow-up-circle', 'size-3.5') }} {{ __('ads.mine.bump_next', ['date' => mdate(\App\Http\Controllers\Account\MyAdsController::nextBump($ad))]) }}</p>
                                @endif
                            @endif
                            @if ($ad->isLive() && feature('ads.close_reason'))
                                <details class="open:basis-full" data-close-ad>
                                    <summary class="btn-chip inline-flex cursor-pointer list-none [&::-webkit-details-marker]:hidden">{{ icon('check', 'size-3.5') }} {{ __('ads.mine.close_button') }}</summary>
                                    <form method="post" action="{{ lroute('account.ads.close', ['ad' => $ad->id]) }}" class="mt-2 rounded-xl bg-stone-50 p-3 ring-1 ring-stone-200">
                                        @csrf
                                        <fieldset>
                                            <legend class="text-sm font-semibold">{{ __('ads.mine.close_reason') }}</legend>
                                            @foreach (\App\Http\Controllers\Account\MyAdsController::CLOSE_REASONS as $reason)
                                                <label class="mt-1.5 flex items-center gap-2 text-sm">
                                                    <input type="radio" name="reason" value="{{ $reason }}" required class="size-4 text-brand-700"> {{ __('ads.close.'.$reason) }}
                                                </label>
                                            @endforeach
                                        </fieldset>
                                        <button type="submit" class="btn-primary mt-3">{{ __('ads.mine.close_confirm') }}</button>
                                    </form>
                                </details>
                            @elseif ($ad->isLive())
                                <form method="post" action="{{ lroute('account.ads.sold', ['ad' => $ad->id]) }}" data-confirm="{{ __('ads.mine.mark_sold_confirm') }}">
                                    @csrf
                                    <button class="btn-chip">{{ icon('check', 'size-3.5') }} {{ __('ads.mine.mark_sold') }}</button>
                                </form>
                            @endif
                            @if (feature('ads.expiry') && \App\Http\Controllers\Account\MyAdsController::canRenew($ad))
                                <form method="post" action="{{ lroute('account.ads.renew', ['ad' => $ad->id]) }}">
                                    @csrf
                                    <button class="btn-chip bg-brand-700 text-white ring-brand-700">{{ icon('refresh-cw', 'size-3.5') }} {{ __('ads.mine.renew') }}</button>
                                </form>
                            @endif
                            <form method="post" action="{{ lroute('account.ads.destroy', ['ad' => $ad->id]) }}" data-confirm="{{ __('ads.mine.delete_confirm') }}">
                                @csrf @method('delete')
                                <button class="btn-chip text-red-700">{{ icon('trash-2', 'size-3.5') }} {{ __('ads.mine.delete') }}</button>
                            </form>
                        </div>
                    </div>
                </li>
            @empty
                <x-empty-state tag="li" icon="megaphone" :title="__('ads.mine.empty')">
                    @if (feature('ads.posting'))
                        <a href="{{ lroute('post') }}" class="btn-sell">{{ __('site.nav.post_ad') }}</a>
                    @endif
                </x-empty-state>
            @endforelse
        </ul>

        <div class="mt-4">{{ $ads->links() }}</div>
    </section>
@endsection

@push('scripts')
    @vite('resources/js/confirm.js')
@endpush
