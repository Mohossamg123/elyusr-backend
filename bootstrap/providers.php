<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    App\Providers\TeamNotificationsServiceProvider::class,
    App\Providers\MailSystemServiceProvider::class,
    App\Providers\ActivityTrailServiceProvider::class,
];
