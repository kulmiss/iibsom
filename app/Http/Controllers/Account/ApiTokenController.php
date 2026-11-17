<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Account → App access: make and remove keys for the write API (switch "api.write").
 * The full key is shown ONCE, right in the answer to "Make key" (never kept in the session).
 */
class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        return $this->page($request, null);
    }

    public function store(Request $request): Response
    {
        $user = $this->authUser($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:60'],
            'write' => ['sometimes', 'boolean'],
        ], [], ['name' => __('account.api.name')]);

        if ($user->apiTokens()->count() >= ApiToken::MAX_PER_USER) {
            throw ValidationException::withMessages(['name' => __('account.api.too_many', ['count' => ApiToken::MAX_PER_USER])]);
        }

        [$token, $plain] = ApiToken::issue($user, Str::squish(strip_tags($data['name'])), $request->boolean('write') ? ['read', 'write'] : ['read']);
        Log::channel('security')->info('api.token_created', ['user_id' => $user->id, 'token_id' => $token->id, 'abilities' => $token->abilities]);

        return $this->page($request, $plain);
    }

    public function destroy(Request $request, int $token): RedirectResponse
    {
        $user = $this->authUser($request);
        $deleted = $user->apiTokens()->whereKey($token)->delete();
        abort_if($deleted === 0, 404);
        Log::channel('security')->info('api.token_deleted', ['user_id' => $user->id, 'token_id' => $token]);

        return redirect(lroute('account.api'))->with('status', __('account.api.deleted'));
    }

    private function page(Request $request, ?string $newToken): Response
    {
        $tokens = $this->authUser($request)->apiTokens()->latest('id')->get();

        // no-store: the page can hold a new key; the browser must not keep a copy.
        return response()->view('account.api-tokens', ['tokens' => $tokens, 'newToken' => $newToken, 'max' => ApiToken::MAX_PER_USER])
            ->header('Cache-Control', 'no-store, private');
    }
}
