<?php

namespace App\Http\Controllers;

use App\Http\Requests\SpeakerSearchRequest;
use App\Mail\ContactRequest;
use App\Models\User;
use Captcha\Captcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\View\View;

class PublicProfileController extends Controller
{
    public function index(SpeakerSearchRequest $request): View
    {
        $users = User::searchPublicSpeakers($request->query('query'))
            ->orderBy('name', 'asc')
            ->get();

        return view('account.public-profile.index', [
            'speakers' => $users,
            'query' => $request->original,
        ]);
    }

    public function show($profile_slug): View
    {
        $user = $this->getPublicUserByProfileSlug($profile_slug);
        $talks = $user->talks()->withCurrentRevision()->public()->get()->sortByTitle();
        $bios = $user->bios()->public()->get();

        return view('account.public-profile.show', [
            'user' => $user,
            'talks' => $talks,
            'bios' => $bios,
        ]);
    }

    public function showTalk($profile_slug, $talk_id): View
    {
        $user = $this->getPublicUserByProfileSlug($profile_slug);

        $talk = $user->talks()->withCurrentRevision()->public()->findOrFail($talk_id);

        return view('talks.show-public', [
            'user' => $user,
            'talk' => $talk,
        ]);
    }

    public function showBio($profile_slug, $bio_id): View
    {
        $user = $this->getPublicUserByProfileSlug($profile_slug);

        $bio = $user->bios()->public()->findOrFail($bio_id);

        return view('bios.show-public', [
            'user' => $user,
            'bio' => $bio,
        ]);
    }

    public function getEmail($profile_slug): View
    {
        $user = $this->getPublicUserByProfileSlug($profile_slug);

        if (! $user->allow_profile_contact) {
            abort(404);
        }

        return view('account.public-profile.email', [
            'user' => $user,
        ]);
    }

    public function postEmail($profile_slug, Captcha $captcha, Request $request): RedirectResponse
    {
        $user = $this->getPublicUserByProfileSlug($profile_slug);

        if (! $user->allow_profile_contact) {
            abort(404);
        }

        request()->validate([
            'email' => 'required|email',
            'name' => '',
            'message' => 'required',
        ]);

        $captchaResponse = $captcha->check();
        if (! $captchaResponse->isValid()) {
            Log::info('Captcha error on public speaker profile page '.$request->url().'; reason: '.$captchaResponse->getError());
            Session::flash('error-message', 'You have not passed the captcha. Please try again.');

            return redirect()->back();
        }

        Mail::to($user->email)->send(new ContactRequest($request->get('email'), $request->get('name'), $request->get('message')));

        Session::flash('success-message', 'Message sent!');

        return redirect()->route('speakers-public.show', $user->profile_slug);
    }

    private function getPublicUserByProfileSlug($profile_slug)
    {
        return User::where('profile_slug', $profile_slug)
            ->where('enable_profile', true)
            ->firstOrFail();
    }
}
