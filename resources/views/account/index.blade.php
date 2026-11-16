@extends('layouts.app')

@section('title', __('account.account.title'))

@php($user = auth()->user())

@section('content')
    <section class="mx-auto max-w-md px-4 py-8">
        <div class="flex items-center gap-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200">
            <span class="flex size-14 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xl font-bold text-white">{{ $user->initial() }}</span>
            <div class="min-w-0">
                <h1 class="truncate text-lg font-bold">{{ $user->name }}</h1>
                <p class="text-sm text-stone-600">{{ $user->city?->label }} · {{ \App\Support\Phone\SomaliPhone::format($user->phone) }}</p>
                <p class="text-xs text-stone-500">{{ __('account.account.member_since', ['date' => $user->created_at->translatedFormat('M Y')]) }}</p>
            </div>
        </div>

        <ul class="mt-4 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
            <li>
                <a href="{{ lroute('account.profile') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                    {{ icon('user', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.edit_profile') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
                </a>
            </li>
            @feature('ads.my_ads')
                <li>
                    <a href="{{ lroute('account.ads') }}" class="flex items-center gap-3 px-5 py-4 hover:bg-stone-50">
                        {{ icon('list', 'size-5 text-brand-700') }}<span class="flex-1">{{ __('account.account.my_ads') }}</span>{{ icon('chevron-right', 'size-4 text-stone-400') }}
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
