<?php

namespace App\Session;

use Illuminate\Session\DatabaseSessionHandler;

/**
 * Database sessions without IP address or browser name for visitors who are
 * not signed in (privacy rule). Signed-in users keep them so staff can see
 * suspicious sign-ins.
 */
class PrivateDatabaseSessionHandler extends DatabaseSessionHandler
{
    protected function addRequestInformation(&$payload)
    {
        if ($this->userId() !== null) {
            parent::addRequestInformation($payload);
        }

        return $this;
    }
}
