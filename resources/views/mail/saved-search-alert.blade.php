<x-mail::message>
# {{ $search->label }}

**{{ $total }}** xayeysiis cusub · {{ $total }} new {{ $total === 1 ? 'ad' : 'ads' }}

@foreach ($ads as $ad)
- [{{ $ad->title }}]({{ $ad->url() }}) — {{ $ad->priceLabel() ?: '—' }} · {{ $ad->city->label }}
@endforeach

<x-mail::button :url="$resultsUrl">
Arag dhammaan · See them all
</x-mail::button>

Ma rabtid iimayladan? Ka saar [Raadintayada la kaydiyey]({{ $manageUrl }}).<br>
Don't want these emails? Remove the search in [Saved searches]({{ $manageUrl }}).
</x-mail::message>
