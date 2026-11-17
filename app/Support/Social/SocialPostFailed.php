<?php

namespace App\Support\Social;

use RuntimeException;

/** A network said no (or did not answer). The message is safe to store and show. */
class SocialPostFailed extends RuntimeException
{
    /** @param  list<string>  $secrets  removed from the message */
    public static function because(string $message, array $secrets = []): self
    {
        foreach (array_filter($secrets) as $secret) {
            $message = str_replace($secret, '***', $message);
        }

        return new self(mb_substr($message, 0, 300));
    }
}
