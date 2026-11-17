<?php

namespace App\Support\Social;

/**
 * One place new ads can be shared to (switch "social.autopost").
 * Add a network = add a class and list it in SocialPosts::NETWORKS.
 */
interface SocialNetwork
{
    /** Short name kept in the database ("telegram", "facebook"). */
    public function key(): string;

    /** Name shown to staff. */
    public function label(): string;

    /** True when its keys are in .env. */
    public function isConfigured(): bool;

    /**
     * Share one post: the text and a link to the ad (the network shows the ad's
     * photo from the page preview). Returns the id the network gave the post.
     *
     * @throws SocialPostFailed with a message that never contains a key
     */
    public function post(string $text, string $link): string;
}
