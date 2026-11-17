@extends('layouts.app')

@section('title', __('account.api.title'))

@section('content')
    <section class="mx-auto max-w-xl px-4 py-8">
        <h1 class="text-2xl font-bold">{{ __('account.api.title') }}</h1>
        <p class="mt-2 text-stone-600">{{ __('account.api.intro') }}</p>

        @if (session('status'))
            <p class="mt-4 rounded-xl bg-green-50 p-4 text-sm text-green-900 ring-1 ring-green-200" role="status">{{ session('status') }}</p>
        @endif

        @if ($newToken)
            <div class="mt-6 rounded-2xl bg-amber-50 p-5 ring-1 ring-amber-300" role="status">
                <p class="font-semibold text-amber-900">{{ __('account.api.new_title') }}</p>
                <p class="mt-1 text-sm text-amber-900">{{ __('account.api.new_help') }}</p>
                <label for="new-token" class="sr-only">{{ __('account.api.key') }}</label>
                <input id="new-token" type="text" readonly value="{{ $newToken }}" class="form-input mt-3 font-mono text-xs" autocomplete="off" spellcheck="false">
                <button type="button" class="btn-primary mt-3 w-full py-2" data-copy-from="#new-token" data-done="{{ __('account.api.copied') }}">{{ __('account.api.copy') }}</button>
            </div>
        @endif

        @if ($tokens->count() < $max)
            <form method="post" action="{{ lroute('account.api.store') }}" class="mt-6 space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-stone-200" novalidate>
                @csrf
                <div>
                    <label for="name" class="form-label">{{ __('account.api.name') }}</label>
                    <input id="name" name="name" type="text" required maxlength="60" value="{{ old('name') }}" class="form-input" placeholder="{{ __('account.api.name_example') }}">
                    @error('name') <p class="form-error">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="write" value="1" class="mt-1" @checked(old('write'))>
                    <span>{{ __('account.api.write') }}</span>
                </label>
                <button type="submit" class="btn-sell w-full py-3">{{ __('account.api.make') }}</button>
            </form>
        @else
            <p class="mt-6 rounded-xl bg-stone-100 p-4 text-sm text-stone-700">{{ __('account.api.too_many', ['count' => $max]) }}</p>
        @endif

        <h2 class="mt-8 text-lg font-semibold">{{ __('account.api.yours') }}</h2>
        @if ($tokens->isEmpty())
            <p class="mt-2 text-sm text-stone-600">{{ __('account.api.none') }}</p>
        @else
            <ul class="mt-3 divide-y divide-stone-200 rounded-2xl bg-white shadow-sm ring-1 ring-stone-200">
                @foreach ($tokens as $token)
                    <li class="flex items-center gap-3 px-5 py-4">
                        {{ icon('key', 'size-5 shrink-0 text-brand-700') }}
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-medium">{{ $token->name }}</p>
                            <p class="text-xs text-stone-500">
                                {{ in_array('write', $token->abilities, true) ? __('account.api.can_write') : __('account.api.can_read') }}
                                · {{ $token->last_used_at ? __('account.api.last_used', ['date' => mdate($token->last_used_at)]) : __('account.api.never_used') }}
                                · {{ __('account.api.expires', ['date' => mdate($token->expires_at)]) }}
                            </p>
                        </div>
                        <form method="post" action="{{ lroute('account.api.destroy', ['token' => $token->id]) }}" data-confirm="{{ __('account.api.delete_confirm') }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="rounded-lg p-2 text-red-700 hover:bg-red-50" aria-label="{{ __('account.api.delete', ['name' => $token->name]) }}">{{ icon('trash-2', 'size-5') }}</button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif

        <p class="mt-6 text-xs text-stone-500">{{ __('account.api.safety') }}</p>
    </section>
@endsection
