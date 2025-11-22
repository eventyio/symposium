<?php

use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\LaravelFlare\Facades\Flare;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \Laravel\Scout\ScoutServiceProvider::class,
        \Laravel\Passport\PassportServiceProvider::class,
        \Creativeorange\Gravatar\GravatarServiceProvider::class,
        \Intervention\Image\ImageServiceProvider::class,
        \Laravel\Socialite\SocialiteServiceProvider::class,
        \App\Providers\Filament\AdminPanelProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        // channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectUsersTo(AppServiceProvider::HOME);

        $middleware->throttleApi();
        $middleware->api('web');

        $middleware->alias([
            'check-authorization-params' => \LucaDegasperi\OAuth2Server\Middleware\CheckAuthCodeRequestMiddleware::class,
            'oauth' => \LucaDegasperi\OAuth2Server\Middleware\OAuthMiddleware::class,
            'oauth-client' => \LucaDegasperi\OAuth2Server\Middleware\OAuthClientOwnerMiddleware::class,
            'oauth-user' => \LucaDegasperi\OAuth2Server\Middleware\OAuthUserOwnerMiddleware::class,
            'social' => \App\Http\Middleware\Social::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        Flare::handles($exceptions);
    })->create();
