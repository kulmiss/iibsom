<footer class="mt-12 border-t border-stone-200 bg-white">
    <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-stone-600">
        {{-- Links become real pages in the "pages" PR. --}}
        <ul class="flex flex-wrap gap-x-5 gap-y-2">
            <li>{{ __('site.footer.about') }}</li>
            <li>{{ __('site.footer.safety') }}</li>
            <li>{{ __('site.footer.terms') }}</li>
            <li>{{ __('site.footer.privacy') }}</li>
            <li>{{ __('site.footer.contact') }}</li>
        </ul>
        <p class="mt-4">© {{ now()->year }} {{ config('app.name') }}. {{ __('site.footer.rights') }}</p>
    </div>
</footer>
