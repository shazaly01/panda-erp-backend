<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | System Branding Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may define the application name and the paths for the branding
    | assets. These values are read directly from the environment file (.env)
    | and will be served to the frontend application dynamically.
    |
    */

    'app_name' => env('APP_NAME', 'نظام باندا للادارة'),

    'logo_full' => env('APP_LOGO_FULL', 'branding/default/logo_full.png'),

    'logo_mini' => env('APP_LOGO_MINI', 'branding/default/logo_mini.png'),

    'favicon' => env('APP_FAVICON', 'branding/default/favicon.ico'),

];
