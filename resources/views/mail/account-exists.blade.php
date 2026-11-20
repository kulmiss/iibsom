<x-mail::message>
# Akoon ayaad horey u lahayd · You already have an account

Qof (malaha adiga) ayaa isku dayay inuu ku diiwaangeliyo iimaylkan iibsom.<br>
Someone (maybe you) tried to sign up on iibsom with this email. It already has an account.

<x-mail::button :url="$loginUrl">
Gal · Sign in
</x-mail::button>

Ma xasuusatid furaha sirta ah? [Cusbooneysii]({{ $forgotUrl }}).<br>
Forgot your password? [Reset it]({{ $forgotUrl }}).

Haddii aysan adiga ahayn, iska indho tir: waxba isma beddelin.<br>
If it was not you, ignore this email: nothing has changed.
</x-mail::message>
