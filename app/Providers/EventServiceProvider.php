<?php

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Statamic\Events\EntrySaved;
use Statamic\Events\EntryDeleted;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        EntrySaved::class => [
            \App\Listeners\QueueAiIndex::class,
        ],
        EntryDeleted::class => [
            \App\Listeners\QueueAiIndex::class,
        ],
    ];
}
