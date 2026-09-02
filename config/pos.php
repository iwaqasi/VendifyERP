<?php

return [

    /*
    |--------------------------------------------------------------------------
    | POS / License Enforcement
    |--------------------------------------------------------------------------
    |
    | License checks for the Flutter POS & CMS apps are FAIL-CLOSED:
    | any unexpected error inside the check denies access.
    |
    | `dev_license_bypass` lets local development skip the subscription check,
    | but it is ONLY honoured when APP_ENV is not "production". It must never
    | be set to true on a production server.
    |
    |-----------------------------------------------------------------------
    */

    'dev_license_bypass' => env('POS_LICENSE_DEV_BYPASS', false),

    /*
    |-----------------------------------------------------------------------
    | API Token Lifetime (Passport personal access tokens)
    |-----------------------------------------------------------------------
    |
    | Wave 3: tokens previously never expired (a lost device kept access
    | forever). Access tokens now expire and users can list/revoke devices
    | from GET/DELETE /api/v1/auth/devices.
    |
    |-----------------------------------------------------------------------
    */

    'token_access_days' => env('POS_TOKEN_ACCESS_DAYS', 30),
    'token_refresh_days' => env('POS_TOKEN_REFRESH_DAYS', 90),

];