{{-- 3-slide welcome tour: after the first sign-in, or from Account → Quick tour. Opened by resources/js/tour.js. --}}
@php($slides = [
    ['search', __('site.tour.find_title'), __('site.tour.find_text')],
    ['square-plus', __('site.tour.sell_title'), __('site.tour.sell_text')],
    ['shield-check', __('site.tour.safe_title'), __('site.tour.safe_text')],
])
<dialog data-tour aria-label="{{ __('site.tour.label') }}" class="m-auto w-[calc(100%-2rem)] max-w-sm rounded-2xl bg-white p-0 shadow-xl backdrop:bg-stone-900/60">
    <form method="dialog" class="p-6 text-center">
        @foreach ($slides as $i => [$icon, $title, $text])
            <section data-tour-slide @if ($i > 0) hidden @endif>
                <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-brand-50">{{ icon($icon, 'size-8 text-brand-700') }}</span>
                <h2 class="mt-4 text-xl font-bold">{{ $title }}</h2>
                <p class="mt-2 text-stone-600">{{ $text }}</p>
                <p class="mt-4 text-xs text-stone-500">{{ __('site.tour.step', ['n' => $i + 1, 'total' => count($slides)]) }}</p>
            </section>
        @endforeach
        <div class="mt-5 flex items-center justify-between gap-2">
            <button type="submit" value="skip" data-tour-skip class="rounded-lg px-3 py-2 text-sm font-semibold text-stone-600 hover:bg-stone-100">{{ __('site.tour.skip') }}</button>
            <button type="button" data-tour-back hidden class="rounded-lg px-3 py-2 text-sm font-semibold text-brand-700 hover:bg-brand-50">{{ __('site.tour.back') }}</button>
            <button type="button" data-tour-next class="btn-sell px-5">{{ __('site.tour.next') }}</button>
            <button type="submit" value="done" data-tour-done hidden class="btn-sell px-5">{{ __('site.tour.done') }}</button>
        </div>
    </form>
</dialog>
