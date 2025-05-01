<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Factory;

class FirebaseServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('firebase.messaging', function ($app) {
            $factory = (new Factory)->withServiceAccount(storage_path('tomah-e3dc4-firebase-adminsdk-fbsvc-53d02e686b.json'));
            return $factory->createMessaging();
        });
    }

    public function boot()
    {
        //
    }
}