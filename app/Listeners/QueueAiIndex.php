<?php

namespace App\Listeners;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class QueueAiIndex
{
    public function handle($event): void
    {
        // Valgfritt: bare index visse collections
        // if (method_exists($event, 'entry') && $event->entry?->collectionHandle() !== 'articles') {
        //     return;
        // }

        // Debounce: kjør maks 1 gang per 30 sek uansett hvor mange saves/deletes
        $key = 'ai:index:debounce';

        if (Cache::add($key, '1', now()->addSeconds(30))) {
            // Kjør i queue (ikke blokkér UI/requests)
            Artisan::queue('ai:index');
        }
    }
}
