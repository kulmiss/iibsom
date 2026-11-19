{{-- Post an ad: where am I? (PR 78, switch "post.steps"). $current = 1, 2 or 3. --}}
@if (feature('post.steps'))
    <ol class="mb-4 flex items-center gap-2 text-xs font-medium sm:text-sm" aria-label="{{ __('ads.post.steps_label') }}" data-post-steps>
        @foreach ([1 => __('ads.post.step_category'), 2 => __('ads.post.step_details'), 3 => __('ads.post.step_done')] as $n => $label)
            <li @class(['flex min-w-0 items-center gap-2', 'flex-1' => ! $loop->last]) @if ($n === $current) aria-current="step" @endif>
                <span @class([
                    'flex size-7 shrink-0 items-center justify-center rounded-full font-bold',
                    'bg-brand-700 text-white' => $n === $current,
                    'bg-emerald-600 text-white' => $n < $current,
                    'bg-stone-200 text-stone-600' => $n > $current,
                ])>@if ($n < $current){{ icon('check', 'size-4') }}<span class="sr-only">✓</span>@else{{ $n }}@endif</span>
                {{-- Phones: only the current step's name (the others would be cut); computers: all names. --}}
                <span @class(['truncate', 'text-stone-900' => $n === $current, 'sr-only sm:not-sr-only text-stone-500' => $n !== $current])>{{ $label }}</span>
                @if (! $loop->last)<span class="h-0.5 min-w-3 flex-1 rounded {{ $n < $current ? 'bg-emerald-600' : 'bg-stone-200' }}" aria-hidden="true"></span>@endif
            </li>
        @endforeach
    </ol>
@endif
