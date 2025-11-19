<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Facades\Image;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class AccountController extends Controller
{
    public const THUMB_SIZE = 250;

    public const HIRES_SIZE = 1250;

    public function show(): View
    {
        return view('account.show', [
            'user' => auth()->user(),
        ]);
    }

    public function edit(): View
    {
        return view('account.edit', [
            'user' => auth()->user(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        request()->validate([
            'name' => 'required',
            'email' => 'email|required|unique:users,email,' . auth()->user()->id,
            'wants_notifications' => '',
            'enable_profile' => '',
            'allow_profile_contact' => '',
            'profile_intro' => '',
            'profile_slug' => 'alpha_dash|required_if:enable_profile,1|unique:users,profile_slug,' . auth()->user()->id,
            'profile_picture' => 'image|max:5000',
        ], [
            'profile_picture.max' => 'Profile picture cannot be larger than 5mb',
            'profile_slug.required_if' => 'You must set a Profile URL Slug to enable your Public Speaker Profile',
        ]);

        // Save
        $user = auth()->user();
        $user->name = $request->get('name');
        $user->email = $request->get('email');
        if ($request->get('password')) {
            $user->password = Hash::make($request->get('password'));
        }
        $user->wants_notifications = $request->get('wants_notifications');
        $user->enable_profile = $request->get('enable_profile');
        $user->allow_profile_contact = $request->get('allow_profile_contact');
        $user->profile_intro = $request->get('profile_intro');
        $user->profile_slug = $request->get('profile_slug');
        $user->location = $request->get('location');
        $user->neighborhood = $request->get('neighborhood');
        $user->sublocality = $request->get('sublocality');
        $user->city = $request->get('city');
        $user->state = $request->get('state');
        $user->country = $request->get('country');

        $user->save();

        if ($request->hasFile('profile_picture')) {
            $this->updateProfilePicture($user, $request->file('profile_picture'));
        }

        Session::flash('success-message', 'Successfully edited account.');

        return redirect('account');
    }

    public function destroy(): RedirectResponse
    {
        $user = auth()->user();

        auth()->logout();
        $user->delete();

        Session::flash('success-message', 'Successfully deleted account.');

        return redirect('/');
    }

    public function delete(): View
    {
        return view('account.confirm-delete');
    }

    public function export(): BinaryFileResponse
    {
        $user = auth()->user();
        $user->load([
            'talks' => fn ($query) => $query->withCurrentRevision(),
            'talks.revisions',
        ]);

        $headers = ['Content-type' => 'application/json'];

        $tempName = sprintf('%d_export.json', $user->id);
        $exportName = sprintf('export_%s.json', now()->format('Y_m_d'));

        $export = ['talks' => $user->talks->sortByTitle()->toArray()];

        Storage::disk('local')->put($tempName, json_encode($export));

        $path = Storage::disk('local')->path('/');

        return response()
            ->download($path . $tempName, $exportName, $headers)
            ->deleteFileAfterSend(true);
    }

    public function oAuthSettings(): View
    {
        return view('account.oauth-settings');
    }

    private function updateProfilePicture($user, $picture)
    {
        // Make regular image
        $thumb = Image::make($picture->getRealPath())
            ->fit(self::THUMB_SIZE, self::THUMB_SIZE);

        // Make hires image
        $hires = Image::make($picture->getRealPath())
            ->fit(self::HIRES_SIZE, self::HIRES_SIZE, function ($constraint) {
                $constraint->upsize();
            });

        // Delete the previous profile pictures
        if ($user->profile_picture != null) {
            Storage::delete([
                User::PROFILE_PICTURE_THUMB_PATH . $user->profile_picture,
                User::PROFILE_PICTURE_HIRES_PATH . $user->profile_picture,
            ]);
        }

        // Store the new profile pictures
        Storage::disk('public')->put(User::PROFILE_PICTURE_THUMB_PATH . $picture->hashName(), $thumb->stream());
        Storage::disk('public')->put(User::PROFILE_PICTURE_HIRES_PATH . $picture->hashName(), $hires->stream());

        // Save the updated filename to the user
        $user->updateProfilePicture($picture->hashName());
    }
}
