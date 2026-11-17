<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * The signed-in user, for routes behind the `auth` or `admin` middleware.
     * Answers 401 instead of crashing if a route is ever left unprotected.
     */
    protected function authUser(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
