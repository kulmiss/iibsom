<x-mail::message>
# {{ $buyerName }}: {{ $phone }}

**{{ $buyerName }}** wuxuu rabaa inaad u soo wacdo ku saabsan · would like you to call about:
[{{ $ad->title }}]({{ $ad->url() }})

<x-mail::button :url="$tel">
U wac {{ $phone }} · Call {{ $phone }}
</x-mail::button>

Ammaan ahaw: weligaa lacag ha qaadan ama ha dirin intaadan la kulmin.<br>
Stay safe: never send or take money before you meet.

Ma rabtid iimayladan? Beddel [Ogeysiisyada]({{ $settingsUrl }}).<br>
Don't want these emails? Change [Notifications]({{ $settingsUrl }}).
</x-mail::message>
