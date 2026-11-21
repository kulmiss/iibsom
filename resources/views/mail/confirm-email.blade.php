<x-mail::message>
# Soo dhawoow · Welcome, {{ $name }}

Riix badhanka si aad u xaqiijiso iimaylkaaga, kadibna ku gal furahaaga sirta ah.<br>
Tap the button to confirm your email, then sign in with your password.

<x-mail::button :url="$url">
Xaqiiji iimaylka · Confirm email
</x-mail::button>

Linkigu wuxuu shaqeynayaa {{ $hours }} saacadood.<br>
The link works for {{ $hours }} hours.

Haddii aadan is-diiwaangelin, iska indho tir iimaylkan.<br>
If you did not sign up, ignore this email: no account is made without this link.
</x-mail::message>
