<?php

return [
    // Akun admin awal yang dibuat DatabaseSeeder (dilewati bila email/password kosong).
    'admin' => [
        'name' => env('ADMIN_NAME', 'Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],
];
