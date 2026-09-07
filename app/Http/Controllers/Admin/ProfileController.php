<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\RespondsJsonOrRedirect;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePasswordRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Models\Language;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Profile & preferences — the signed-in user's own account screen
 * (the "Profile & preferences" item in the topbar menu). Self-service:
 * no permission gate; a user always edits themselves.
 */
class ProfileController extends Controller
{
    use RespondsJsonOrRedirect;

    public function edit(): View
    {
        $user = request()->user();

        return view('admin.profile', [
            'user'      => $user,
            'languages' => Language::query()->active()->ordered()->get(),
            'stores'    => $user->accessibleStores(),
        ]);
    }

    public function update(UpdateProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $data = $request->profileData();

        // Avatar lifecycle — new upload / explicit remove / leave alone.
        if ($request->hasFile('avatar')) {
            // Only swap the path if the write actually succeeded — a failed
            // store() returns false and must never leave a broken reference.
            if ($path = $request->file('avatar')->store('avatars', 'public')) {
                $this->deleteAvatar($user);
                $data['avatar_path'] = $path;
            }
        } elseif ($request->boolean('avatar_remove')) {
            $this->deleteAvatar($user);
            $data['avatar_path'] = null;
        }

        do_action('user.before_update', $user, $data);
        $user->update($data);
        do_action('user.after_update', $user);

        return $this->jsonOrRedirect(
            $request,
            __('account.flash.updated'),
            route('admin.profile.edit'),
        );
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse|RedirectResponse
    {
        // The `password` attribute is hash-cast on the model.
        $request->user()->update(['password' => $request->input('password')]);

        return $this->jsonOrRedirect(
            $request,
            __('account.flash.password_updated'),
            route('admin.profile.edit'),
        );
    }

    private function deleteAvatar(User $user): void
    {
        if ($user->avatar_path && Storage::disk('public')->exists($user->avatar_path)) {
            Storage::disk('public')->delete($user->avatar_path);
        }
    }
}
