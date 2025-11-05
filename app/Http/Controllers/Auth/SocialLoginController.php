<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Socialite;

class SocialLoginController extends Controller
{
    public function redirect($service, Request $request)
    {
        return Socialite::driver($service)->redirect();
    }

    public function callback($service, Request $request)
    {
        $serviceUser = Socialite::driver($service)->user();

        $user = $this->getExistingUser($serviceUser, $service);

        if (!$user) {
            return view('auth.no-signups');
        }

        if (!$user->hasSocialLinked($service)) {
            $user->social()->create([
                'social_id' => $serviceUser->getId(),
                'service' => $service,
            ]);
        }

        Auth::login($user, false);

        return redirect()->route('dashboard');
    }

    protected function getExistingUser($serviceUser, $service)
    {
        return User::where('email', $serviceUser->getEmail())->orWhereHas('social', function ($q) use ($serviceUser, $service) {
            $q->where('social_id', $serviceUser->getId())->where('service', $service);
        })->first();
    }
}
