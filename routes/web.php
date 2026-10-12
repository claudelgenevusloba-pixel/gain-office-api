<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;



Route::get('/', function () {
    $index = public_path('build/index.html');

    if (file_exists($index)) {
        return response()->file($index);
    }

    return view('welcome');
});


/*
| Routage SPA (fallback) : permet d'actualiser directement
| une route React profonde (/rapports, /employes, ...).
| Les requêtes /api/* inconnues restent des 404 JSON.
| Inactif tant que public/build/index.html n'existe pas.
*/
Route::fallback(function (Request $request) {
    if ($request->is('api/*')) {
        abort(404);
    }

    $index = public_path('build/index.html');

    if (file_exists($index)) {
        return response()->file($index);
    }

    abort(404);
});

