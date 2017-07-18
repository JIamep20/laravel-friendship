<?php

namespace Lamer1\LaravelFriendships\Providers;

use Illuminate\Support\ServiceProvider;

class FriendshipsServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        //$from = __DIR__ . '/../';

        //$this->publishes([
        //    $from . 'database/migrations/create_friendships_table.php' => database_path('migrations') . '/' . date('Y_m_d_His', time()) . '_create_friendships_table.php'
        //]);
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
}
