<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Laravel's own auto-registered signed-URL serving route for
            // this disk would otherwise claim the exact same "/storage/
            // {path}" URI as this app's custom fallback route below (see
            // StorageController) — 'serve' => true (the framework's default
            // stub value) makes it intercept those requests first and 403
            // anything without a valid signature, since nothing here ever
            // generates one. Nothing in the app uses the 'local' disk's
            // serving feature, so it's turned off to leave that URI to the
            // app's own controller.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => 'auto',
            'bucket' => env('R2_BUCKET'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => true,
            // Root-relative on purpose (not built from APP_URL): this app is tested
            // from multiple devices on the same network (dev machine, tester phones/
            // laptops), each hitting the server through a different host/IP. A URL
            // baked from APP_URL (e.g. http://127.0.0.1:8000) only resolves on the
            // machine running the server — every other device gets a broken image.
            // A path starting with "/" always resolves against whatever host the
            // browser is actually using, so it works the same for everyone.
            //
            // This also means every file request still flows through this app's own
            // StorageController (see routes/web.php) rather than hitting R2
            // directly — which is exactly what we want now that the bucket itself
            // is private, not public: nothing can read a founder's uploaded
            // documents without going through this app's own access checks first.
            'url' => env('ASSET_URL') ? rtrim(env('ASSET_URL'), '/').'/storage' : '/storage',
            'visibility' => 'private',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
