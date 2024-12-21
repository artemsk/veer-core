<?php

namespace Veer\Providers;

use Illuminate\Contracts\Events\Dispatcher as DispatcherContract;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array
     */
    protected $listen = [
        'lock.for.edit' => [
            \Veer\Events\adminLock::class
        ],
    ];

    /**
     * Register any other events for your application.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

		\Event::listen('veer.message.center', function($message) {
            
            app('veer')->loadedComponents['veer_message_center'][] = $message;
            \Session::put('veer_message_center', app('veer')->loadedComponents['veer_message_center']);
        });
    }
}
