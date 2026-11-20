{{-- Save (favourite) button on the ad page. Guests are sent to sign in. resources/js/favourite.js makes it work without a page reload.
     $compact = true: the icon-only version for the phone top bar (PR 84). --}}
@php($compact = $compact ?? false)
@auth
    @if ($ad->isLive() || $saved)
        <form method="post" action="{{ lroute('favourite.toggle', ['ad' => $ad->id]) }}" data-favourite class="shrink-0">
            @csrf
            <button type="submit" aria-pressed="{{ $saved ? 'true' : 'false' }}"
                    aria-label="{{ $saved ? __('ads.saved.unsave_label') : __('ads.saved.save_label') }}"
                    data-label-on="{{ __('ads.saved.unsave_label') }}" data-label-off="{{ __('ads.saved.save_label') }}"
                    data-text-on="{{ __('ads.saved.saved') }}" data-text-off="{{ __('ads.saved.save') }}"
                    @class(['chip gap-1.5 ring-stone-200 aria-pressed:bg-brand-50 aria-pressed:text-brand-800 aria-pressed:ring-brand-200' => ! $compact, 'icon-btn aria-pressed:bg-white/20 aria-pressed:[&_svg]:fill-current' => $compact])>
                {{ icon('bookmark', $compact ? 'size-5' : 'size-4') }}<span data-favourite-text @class(['sr-only' => $compact])>{{ $saved ? __('ads.saved.saved') : __('ads.saved.save') }}</span>
            </button>
        </form>
    @endif
@else
    @if (sign_in_open())
        <a href="{{ lroute('login') }}" @class(['chip shrink-0 gap-1.5 ring-stone-200' => ! $compact, 'icon-btn shrink-0' => $compact]) title="{{ __('ads.saved.sign_in') }}">
            {{ icon('bookmark', $compact ? 'size-5' : 'size-4') }}<span @class(['sr-only' => $compact])>{{ $saved ?? false ? __('ads.saved.saved') : __('ads.saved.save') }}</span>
        </a>
    @endif
@endauth
