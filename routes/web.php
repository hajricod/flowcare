<?php

use Illuminate\Support\Facades\Route;

// Route::get('/', function () {
//     return view('welcome');
// });

Route::get('/', function () {
    return response()->json(['status' => 'online', 'version' => '1.0.0']);
});

Route::get('/queue-sse-demo', function () {
    return view('queue-sse-demo');
});