<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'stack' => [
        'Laravel' => Application::VERSION,
        'PHP' => PHP_VERSION,
        'Inertia' => 'v3',
        'Vue' => 'v3',
        'Tailwind CSS' => 'v4',
    ],
]))->name('home');
