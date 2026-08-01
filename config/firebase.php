<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Firebase Credentials
    |--------------------------------------------------------------------------
    */

    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/firebase-service-account.json')),
    ],

];