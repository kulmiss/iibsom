{{-- $stars = 1..5, shown as filled/empty stars with a text for screen readers. --}}
<span class="inline-flex items-center" role="img" aria-label="{{ __('chat.review.star_label', ['count' => $stars]) }}">
    @for ($i = 1; $i <= 5; $i++)
        {{ icon('star', 'size-4 '.($i <= $stars ? 'fill-amber-400 text-amber-500' : 'text-stone-300')) }}
    @endfor
</span>
