<?php

namespace App\Http\Controllers\MobileApi;

use App\Models\MobileApiToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Login/logout for the Expo mobile app. Independent of the main app's
 * session `web` guard — see MobileApiToken and AuthenticateMobileApiToken.
 */
class AuthController
{
    public function login(Request $request)
    {
        $data = Validator::make($request->all(), [
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
            'store_id' => ['nullable', 'integer'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ])->validate();

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Account is disabled.'], 403);
        }

        $storeIds = $user->accessibleStoreIds();

        if (empty($storeIds)) {
            return response()->json(['message' => 'No store access on this account.'], 403);
        }

        if (! empty($data['store_id'])) {
            if (! $user->canAccessStore((int) $data['store_id'])) {
                return response()->json(['message' => 'No access to that store.'], 403);
            }
            $storeId = (int) $data['store_id'];
        } elseif (count($storeIds) === 1) {
            $storeId = $storeIds[0];
        } else {
            return response()->json([
                'message' => 'Select a store.',
                'stores'  => $user->accessibleStores()->map(fn ($s) => [
                    'id' => $s->id, 'name' => $s->name, 'code' => $s->code,
                ])->values(),
            ], 409);
        }

        $plaintext = MobileApiToken::issue($user, $storeId, $data['device_name'] ?? null);

        return response()->json([
            'token'   => $plaintext,
            'store'   => ['id' => $storeId],
            'user'    => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email],
        ]);
    }

    public function logout(Request $request)
    {
        $header = $request->header('Authorization', '');
        $plaintext = str_starts_with($header, 'Bearer ') ? substr($header, 7) : null;

        if ($plaintext) {
            MobileApiToken::resolve($plaintext)?->delete();
        }

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * Every store this account can sign into — powers a Settings-screen
     * store picker (same list `login()` returns in its 409 when a
     * multi-store account doesn't pass `store_id`), so the app can show
     * "you're viewing: Branch X ▾" without re-running the login flow.
     */
    public function stores(Request $request)
    {
        $user = $request->attributes->get('mobile_api_user');

        return response()->json([
            'current_store_id' => $request->attributes->get('mobile_api_store_id'),
            'stores' => $user->accessibleStores()->map(fn ($s) => [
                'id' => $s->id, 'name' => $s->name, 'code' => $s->code,
            ])->values(),
        ]);
    }

    /**
     * Change which store the CURRENT bearer token is pinned to, in
     * place — no re-login, no new token issued, so the app doesn't have
     * to store a new plaintext value. This is the "select branch" a
     * Settings screen needs: prices/stock everywhere else in the API
     * are scoped to `mobile_api_store_id`, which comes straight from
     * this token row (see AuthenticateMobileApiToken).
     */
    public function switchStore(Request $request)
    {
        $data = Validator::make($request->all(), [
            'store_id' => ['required', 'integer'],
        ])->validate();

        /** @var User $user */
        $user = $request->attributes->get('mobile_api_user');

        if (! $user->canAccessStore((int) $data['store_id'])) {
            return response()->json(['message' => 'No access to that store.'], 403);
        }

        /** @var MobileApiToken $token */
        $token = $request->attributes->get('mobile_api_token');
        $token->forceFill(['store_id' => (int) $data['store_id']])->save();

        $store = $user->accessibleStores()->find($data['store_id']);

        return response()->json([
            'store' => ['id' => $store->id, 'name' => $store->name, 'code' => $store->code],
        ]);
    }
}
