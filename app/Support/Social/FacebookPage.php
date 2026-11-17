<?php

namespace App\Support\Social;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/** A Facebook Page through the Graph API (FACEBOOK_PAGE_ID, FACEBOOK_PAGE_TOKEN: a long-lived Page token). */
class FacebookPage implements SocialNetwork
{
    public function key(): string
    {
        return 'facebook';
    }

    public function label(): string
    {
        return 'Facebook Page';
    }

    public function isConfigured(): bool
    {
        return filled(config('services.facebook.page_id')) && filled(config('services.facebook.page_token'));
    }

    public function post(string $text, string $link): string
    {
        $token = (string) config('services.facebook.page_token');
        $page = rawurlencode((string) config('services.facebook.page_id'));
        $url = 'https://graph.facebook.com/'.config('services.facebook.graph_version', 'v21.0')."/{$page}/feed";

        try {
            // A link post: Facebook shows the ad's photo and title from the page preview.
            // The token goes in the form body, never in the address (addresses end up in logs).
            $res = Http::timeout(15)->asForm()->post($url, ['message' => mb_substr($text, 0, 3000), 'link' => $link, 'access_token' => $token]);
        } catch (ConnectionException $e) {
            throw SocialPostFailed::because('Facebook did not answer: '.$e->getMessage(), [$token]);
        }

        if (! $res->successful()) {
            throw SocialPostFailed::because('Facebook said no ('.$res->status().'): '.(string) $res->json('error.message', 'no reason given'), [$token]);
        }

        return (string) $res->json('id');
    }
}
