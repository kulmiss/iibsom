@extends('layouts.app')

@section('title', __('account.account.title'))

@php($user = auth()->user())

@section('content')
    @php($dash = feature('account.dashboard'))
    <section class="mx-auto {{ $dash ? 'max-w-3xl' : 'max-w-md' }} px-4 py-8">
        <div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xl font-bold text-white">{{ $user->initial() }}</span>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-bold">{{ $user->name }}</h1>
                <p class="text-sm text-stone-600">{{ $user->city?->label }} · {{ \App\Support\Phone\SomaliPhone::format($user->phone) }}</p>
                <p class="text-xs text-stone-500">{{ __('account.account.member_since', ['date' => mdate($user->created_at, 'M Y')]) }}</p>
            </div>
        </div>

        @if ($dash)
            {{-- Dashboard (PR 79): own numbers only, each a link to the page with the details. Views and contacts link to
                 My ads, so they show only while My ads is ON (found when Ad statistics became ON by default, PR 106). --}}
            @php($sum = \App\Support\Users\AccountSummary::for($user))
            @php($tiles = array_values(array_filter([
                feature('ads.my_ads') ? ['list', __('account.dash.live'), $sum['live'], lroute('account.ads')] : null,
                feature('ads.my_ads') && $sum['waiting'] > 0 ? ['clock', __('account.dash.waiting'), $sum['waiting'], lroute('account.ads', ['tab' => 'pending'])] : null,
                feature('ads.my_ads') && $sum['views'] !== null ? ['eye', __('account.dash.views'), $sum['views'], lroute('account.ads')] : null,
                feature('ads.my_ads') && $sum['contacts'] !== null ? ['phone', __('account.dash.contacts'), $sum['contacts'], lroute('account.ads')] : null,
                $sum['unread'] !== null ? ['message-circle', __('account.dash.unread'), $sum['unread'], lroute('chat')] : null,
                $sum['saved'] !== null ? ['bookmark', __('account.dash.saved'), $sum['saved'], lroute('saved')] : null,
                $sum['followers'] !== null ? ['users', __('account.dash.followers'), $sum['followers'], lroute('seller.show', ['user' => $user->id])] : null,
            ])))
            <h2 class="sr-only">{{ __('account.dash.title') }}</h2>
            <ul class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-3" data-dashboard>
                @foreach ($tiles as [$tileIcon, $tileLabel, $tileValue, $tileUrl])
                    <li>
                        <a href="{{ $tileUrl }}" class="flex h-full flex-col gap-1 rounded-2xl bg-white p-4 shadow-sm ring-1 ring-stone-200 hover:ring-brand-300">
                            <span class="flex items-center gap-1.5 text-xs font-medium text-stone-600">{{ icon($tileIcon, 'size-4 text-brand-700') }} {{ $tileLabel }}</span>
                            <span class="text-2xl font-bold">{{ number_format($tileValue) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
            @if (feature('ads.posting'))
                <div class="mt-4 flex flex-wrap gap-2" data-dashboard-actions>
                    <a href="{{ lroute('post') }}" class="btn-sell">{{ icon('plus', 'size-4') }} {{ __('site.nav.post_ad') }}</a>
                    @feature('ads.my_ads')
                        <a href="{{ lroute('account.ads') }}" class="btn-chip">{{ icon('list', 'size-4') }} {{ __('account.account.my_ads') }}</a>
                    @endfeature
                    @feature('chat')
                        <a href="{{ lroute('chat') }}" class="btn-chip">{{ icon('message-circle', 'size-4') }} {{ __('site.nav.messages') }}</a>
                    @endfeature
                </div>
            @endif
        @endif

        <ul @class(['mt-4 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200', 'sm:grid sm:grid-cols-2 sm:divide-y-0 sm:[&>li]:border-b sm:[&>li]:border-stone-100 sm:[&>li:nth-child(odd)]:border-e' => $dash])>
            <li>
                <a href="{{ lroute('account.profile') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                    {{ icon('user', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.edit_profile') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                </a>
            </li>
            @if (feature('sellers.phone_confirmed') && filled($user->phone))
                <li>
                    <a href="{{ lroute('account.phone') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50" data-phone-menu>
                        {{ icon('phone', 'size-5 text-brand-700') }}<span class="flex-1">{{ $user->phone_confirmed_at ? __('account.phone.menu_done') : __('account.phone.menu') }}</span>
                        @if ($user->phone_confirmed_at){{ icon('check', 'size-4 text-emerald-700') }}@else{{ icon('chevron-right', 'size-4 text-stone-400') }}@endif
                    </a>
                </li>
            @endif
            @feature('ads.my_ads')
                <li>
                    <a href="{{ lroute('account.ads') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('list', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.my_ads') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('boosts')
                <li>
                    <a href="{{ lroute('account.payments') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('rocket', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('ads.boost.payments') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('shops')
                <li>
                    <a href="{{ lroute('account.shop') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('store', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.shop.title') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @if (feature('seller_ratings') && auth()->user()?->rating_count > 0)
                <li>
                    <a href="{{ lroute('seller.reviews', ['user' => auth()->id()]) }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('star', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('ads.ratings.mine') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endif
            @feature('api.write')
                <li>
                    <a href="{{ lroute('account.api') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('key', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.api.menu') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('verified_badge')
                <li>
                    <a href="{{ lroute('verify') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('badge-check', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.verify.title') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('favourites')
                <li>
                    <a href="{{ lroute('saved') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('bookmark', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('ads.saved.title') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('notifications')
                <li>
                    <a href="{{ lroute('notifications') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('bell', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('notify.title') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @if (feature('sellers.page') && feature('sellers.follow'))
                <li>
                    <a href="{{ lroute('following') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('user-check', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('sellers.my_following') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endif
            @feature('saved_searches')
                <li>
                    <a href="{{ lroute('searches') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon(feature('notifications') ? 'search' : 'bell', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.searches') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('site.welcome_tour')
                <li>
                    <a href="{{ lroute('account', ['tour' => 1]) }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('map-pin', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.tour') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
            @feature('help.centre')
                <li>
                    <a href="{{ lroute('help') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('circle-help', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.help') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                    </a>
                </li>
            @endfeature
        </ul>

        <form method="post" action="{{ lroute('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="w-full rounded-lg px-4 py-3 font-semibold text-red-700 ring-1 ring-red-200 hover:bg-red-50">{{ __('account.account.logout') }}</button>
        </form>
    </section>
@endsection
