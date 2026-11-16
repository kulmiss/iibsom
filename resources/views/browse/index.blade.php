@extends('layouts.app')

@php
    $title = $category ? $category->label.($city ? ' '.__('ads.browse.in_city', ['city' => $city->label]) : '') : (!empty($filters['q']) ? __('ads.browse.search_for', ['q' => $filters['q']]) : __('ads.browse.all_ads'));
    $action = $category ? lroute('browse.category', array_filter(['category' => $category->slug, 'city' => $city?->slug])) : lroute('search');
@endphp

@section('title', $title)
@section('description', trans_choice('ads.browse.results', $results->total(), ['count' => number_format($results->total())]).' · '.$title.' · iibsom')

@push('head')
    @if ($results->currentPage() > 1 || !empty($filters['q']) || !empty($filters['f']) || !empty($filters['min']) || !empty($filters['max']))
        <meta name="robots" content="noindex, follow">
    @endif
@endpush

@section('content')
    <div class="mx-auto max-w-6xl px-4 py-5" @if (feature('recommendations') && $category) data-track-cat="{{ $category->id }}" @endif>
        <nav class="text-sm text-stone-500" aria-label="Breadcrumb">
            <a href="{{ lroute('home') }}" class="hover:underline">{{ __('site.nav.home') }}</a>
            @if ($category?->parent)
                › <a href="{{ lroute('browse.category', ['category' => $category->parent->slug]) }}" class="hover:underline">{{ $category->parent->label }}</a>
            @endif
            @if ($category)
                › <span>{{ $category->label }}</span>
            @endif
        </nav>
        <h1 class="mt-1 text-2xl font-bold">{{ $title }}</h1>
        <p class="text-sm text-stone-600">{{ trans_choice('ads.browse.results', $results->total(), ['count' => number_format($results->total())]) }}</p>

        <div class="mt-4 grid gap-5 lg:grid-cols-[16rem_1fr]">
            {{-- Filters: a fold-out box on phones, a sidebar on desktop --}}
            <details class="group rounded-xl bg-white shadow-sm ring-1 ring-stone-200 lg:open:block" @if (request()->hasAny(['min','max','condition','f'])) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between px-4 py-3 font-semibold lg:hidden">
                    {{ __('ads.browse.filters') }} {{ icon('chevron-down', 'size-5 transition group-open:rotate-180') }}
                </summary>
                <form method="get" action="{{ $action }}" class="space-y-4 border-t border-stone-100 p-4 lg:border-0">
                    @if (!$category)
                        <input type="hidden" name="q" value="{{ $filters['q'] ?? '' }}">
                    @else
                        <div>
                            <label for="fq" class="form-label">{{ __('site.home.search_button') }}</label>
                            <input id="fq" name="q" type="search" value="{{ $filters['q'] ?? '' }}" class="form-input">
                        </div>
                    @endif

                    @if (count($cities) > 1 && $category)
                        <div>
                            <p class="form-label">{{ __('ads.browse.city') }}</p>
                            <ul class="space-y-1 text-sm">
                                <li><a href="{{ lroute('browse.category', ['category' => $category->slug]) }}" @class(['font-semibold text-brand-700' => !$city])>{{ __('ads.browse.all_cities') }}</a></li>
                                @foreach ($cities as $c)
                                    <li><a href="{{ lroute('browse.category', ['category' => $category->slug, 'city' => $c['slug']]) }}" @class(['font-semibold text-brand-700' => $city?->id === $c['id']])>{{ translated($c['name']) }}</a></li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div>
                        <p class="form-label">{{ __('ads.browse.price') }}</p>
                        <div class="flex gap-2">
                            <input name="min" type="number" min="0" inputmode="numeric" placeholder="{{ __('ads.browse.min') }}" value="{{ $filters['min'] ?? '' }}" class="form-input" aria-label="{{ __('ads.browse.min') }}">
                            <input name="max" type="number" min="0" inputmode="numeric" placeholder="{{ __('ads.browse.max') }}" value="{{ $filters['max'] ?? '' }}" class="form-input" aria-label="{{ __('ads.browse.max') }}">
                        </div>
                    </div>

                    @if (!$category || $category->has_condition)
                        <div>
                            <label for="fcond" class="form-label">{{ __('ads.browse.condition') }}</label>
                            <select id="fcond" name="condition" class="form-input">
                                <option value="">{{ __('ads.browse.any') }}</option>
                                <option value="new" @selected(($filters['condition'] ?? '') === 'new')>{{ __('ads.form.new') }}</option>
                                <option value="used" @selected(($filters['condition'] ?? '') === 'used')>{{ __('ads.form.used') }}</option>
                            </select>
                        </div>
                    @endif

                    @foreach ($fields as $field)
                        @php($v = $filters['f'][$field->key] ?? null)
                        <div>
                            @if ($field->type === 'number')
                                <p class="form-label">{{ $field->label_text }}@if ($field->unit) ({{ $field->unit }})@endif</p>
                                <div class="flex gap-2">
                                    <input name="f[{{ $field->key }}][min]" type="number" min="0" placeholder="{{ __('ads.browse.from') }}" value="{{ is_array($v) ? ($v['min'] ?? '') : '' }}" class="form-input" aria-label="{{ $field->label_text }} {{ __('ads.browse.from') }}">
                                    <input name="f[{{ $field->key }}][max]" type="number" min="0" placeholder="{{ __('ads.browse.to') }}" value="{{ is_array($v) ? ($v['max'] ?? '') : '' }}" class="form-input" aria-label="{{ $field->label_text }} {{ __('ads.browse.to') }}">
                                </div>
                            @elseif ($field->type === 'select')
                                <label for="ff-{{ $field->key }}" class="form-label">{{ $field->label_text }}</label>
                                <select id="ff-{{ $field->key }}" name="f[{{ $field->key }}]" class="form-input">
                                    <option value="">{{ __('ads.browse.any') }}</option>
                                    @foreach ($field->optionList() as $ov => $ol)
                                        <option value="{{ $ov }}" @selected($v === $ov)>{{ $ol }}</option>
                                    @endforeach
                                </select>
                            @elseif ($field->type === 'boolean')
                                <label class="flex items-center gap-2 text-sm font-medium">
                                    <input type="checkbox" name="f[{{ $field->key }}]" value="1" @checked($v === '1') class="size-4 rounded border-stone-300"> {{ $field->label_text }}
                                </label>
                            @endif
                        </div>
                    @endforeach

                    <div>
                        <label for="fsort" class="form-label">{{ __('ads.browse.sort') }}</label>
                        <select id="fsort" name="sort" class="form-input">
                            @foreach (['newest', 'cheapest', 'expensive'] as $s)
                                <option value="{{ $s }}" @selected(($filters['sort'] ?? 'newest') === $s)>{{ __('ads.browse.'.$s) }}</option>
                            @endforeach
                        </select>
                    </div>

                    <button type="submit" class="btn-primary w-full justify-center py-2.5">{{ __('ads.browse.apply') }}</button>
                    <a href="{{ $action }}" class="block text-center text-sm text-stone-600 underline">{{ __('ads.browse.clear') }}</a>
                </form>
            </details>

            <div class="min-w-0">
                @if (!$category)
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($categories as $main)
                            <a href="{{ lroute('browse.category', ['category' => $main['slug']]) }}" class="chip shrink-0 bg-white ring-stone-200">{{ icon($main['icon'], 'size-4') }} {{ translated($main['name']) }}</a>
                        @endforeach
                    </div>
                @elseif ($category->parent_id === null)
                    <div class="mb-3 flex gap-2 overflow-x-auto pb-1">
                        @foreach ($category->children()->where('enabled', true)->orderBy('sort')->get() as $sub)
                            <a href="{{ lroute('browse.category', array_filter(['category' => $sub->slug, 'city' => $city?->slug])) }}" class="chip shrink-0 bg-white ring-stone-200">{{ icon($sub->icon, 'size-4') }} {{ $sub->label }}</a>
                        @endforeach
                    </div>
                @endif

                @if ($results->isEmpty())
                    <div class="rounded-xl border border-dashed border-stone-300 bg-white p-10 text-center">
                        <p class="font-semibold">{{ __('ads.browse.nothing_title') }}</p>
                        <p class="mt-1 text-sm text-stone-600">{{ __('ads.browse.nothing_text') }}</p>
                    </div>
                @else
                    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-4">
                        @foreach ($results as $ad)
                            @include('ads._card', ['ad' => $ad])
                        @endforeach
                    </div>
                    <div class="mt-6">{{ $results->onEachSide(1)->links() }}</div>
                @endif
            </div>
        </div>
    </div>
@endsection
