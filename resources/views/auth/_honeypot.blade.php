@feature('safety.honeypot')
    {{-- Bot trap: hidden from people and screen readers; robots fill it. --}}
    <div class="hidden" aria-hidden="true">
        <label for="website-{{ $id ?? 'f' }}">Website</label>
        <input id="website-{{ $id ?? 'f' }}" name="website" type="text" tabindex="-1" autocomplete="off">
    </div>
@endfeature
