@extends('layouts.app')

@section('title', __('sellers.my_following'))

@section('content')
    <section class="mx-auto max-w-2xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('sellers.my_following') }}</h1>

        @if ($sellers->isEmpty())
            <x-empty-state class="mt-6" icon="user-plus" :title="__('sellers.my_following_empty')">
                <a href="{{ lroute('home') }}" class="btn-primary">{{ __('ads.browse.all_ads') }}</a>
            </x-empty-state>
        @else
            <ul class="mt-4 divide-y divide-stone-100 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
                @foreach ($sellers as $seller)
                    <li class="flex flex-wrap items-center gap-3 px-5 py-4">
                        <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-700 font-bold text-white">{{ $seller->initial() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium break-words">{{ $seller->name }}</p>
                            @if ($seller->city)<p class="text-xs text-stone-500">{{ $seller->city->label }}</p>@endif
                        </div>
                        @if (! $seller->isBanned())
                            <a href="{{ lroute('seller.show', ['user' => $seller->id]) }}" class="chip ring-stone-200">{{ __('sellers.see_ads') }}</a>
                        @endif
                        <form method="post" action="{{ lroute('follow.toggle', ['seller' => $seller->id]) }}">
                            @csrf
                            <button type="submit" class="chip text-red-700 ring-red-200">{{ __('sellers.unfollow') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
            <div class="mt-6">{{ $sellers->links() }}</div>
        @endif
    </section>
@endsection
