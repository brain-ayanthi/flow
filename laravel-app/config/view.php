<?php

return [
    'paths' => [
        resource_path('views'),
    ],

    // Use the path directly rather than realpath(); on a fresh Windows/XAMPP
    // extraction, realpath() can return false if this folder has not been created yet.
    'compiled' => env('VIEW_COMPILED_PATH', storage_path('framework/views')),
];
