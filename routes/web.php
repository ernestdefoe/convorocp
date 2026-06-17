<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Dashboard', [
    'server' => ['name' => 'web-01', 'status' => 'healthy', 'uptime' => '41d'],
    'metrics' => [
        ['label' => 'CPU', 'value' => '18', 'unit' => '%', 'delta' => '-4%'],
        ['label' => 'Memory', 'value' => '6.2', 'unit' => ' / 16 GB', 'delta' => '39%'],
        ['label' => 'Disk', 'value' => '142', 'unit' => ' / 500 GB', 'delta' => '28%'],
        ['label' => 'Bandwidth', 'value' => '1.4', 'unit' => ' TB', 'delta' => '+12%'],
    ],
    'sites' => [
        ['name' => 'convoro.co', 'runtime' => 'PHP 8.5', 'status' => 'healthy', 'visits' => '24.1k/day'],
        ['name' => 'shop.convoro.co', 'runtime' => 'Node 22', 'status' => 'healthy', 'visits' => '8.2k/day'],
        ['name' => 'staging.convoro.co', 'runtime' => 'PHP 8.5', 'status' => 'deploying', 'visits' => '—'],
    ],
]))->name('dashboard');
