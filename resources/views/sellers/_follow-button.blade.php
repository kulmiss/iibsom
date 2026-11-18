{{-- Follow a seller (switch "sellers.follow"). A plain form: works without JavaScript. Guests are sent to sign in. --}}
@if (feature('sellers.follow'))
    @auth
        @if (auth()->id() !== $seller->id)
            <form method="post" action="{{ lroute('follow.toggle', ['seller' => $seller->id]) }}" data-follow>
                @csrf
                <button type="submit" aria-pressed="{{ $following ? 'true' : 'false' }}" aria-label="{{ $following ? __('sellers.unfollow_label', ['name' => $seller->name]) : __('sellers.follow_label', ['name' => $seller->name]) }}"
                        class="chip gap-1.5 ring-brand-200 {{ $following ? 'bg-brand-700 text-white ring-brand-700' : 'bg-white text-brand-800' }}">
                    {{ icon($following ? 'user-check' : 'user-plus', 'size-4') }}<span>{{ $following ? __('sellers.following') : __('sellers.follow') }}</span>
                </button>
            </form>
        @endif
    @else
        @if (feature('login.google') || \App\Http\Controllers\Auth\TestSignInController::available())
            <a href="{{ lroute('login') }}" class="chip gap-1.5 bg-white text-brand-800 ring-brand-200" title="{{ __('sellers.sign_in') }}">{{ icon('user-plus', 'size-4') }}<span>{{ __('sellers.follow') }}</span></a>
        @endif
    @endauth
@endif
