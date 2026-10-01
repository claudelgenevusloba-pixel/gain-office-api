<?php

use Illuminate\Support\Facades\Route;

// Contrôleurs API
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DemandeController;
use App\Http\Controllers\Api\EmployeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\RapportTacheController;
use App\Http\Controllers\Api\TacheController;

// Contrôleur des données de référence
use App\Http\Controllers\ReferenceController;


/*
|--------------------------------------------------------------------------
| API publique
|--------------------------------------------------------------------------
*/

Route::post('/auth/login', [AuthController::class, 'login']);


/*
|--------------------------------------------------------------------------
| API protégée par Sanctum
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Authentification
    |--------------------------------------------------------------------------
    */

    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::post('/auth/logout', [AuthController::class, 'logout']);


    /*
    |--------------------------------------------------------------------------
    | Données de référence
    |--------------------------------------------------------------------------
    */
 Route::middleware('auth:sanctum')->get('/roles-disponibles', [ReferenceController::class, 'rolesDisponibles']);
    Route::get('/roles', [ReferenceController::class, 'roles']);

    Route::get('/services', [ReferenceController::class, 'services']);

    Route::get('/postes', [ReferenceController::class, 'postes']);


    /*
    |--------------------------------------------------------------------------
    | Employés
    |--------------------------------------------------------------------------
    */

    Route::get('/employes', [EmployeController::class, 'index']);

    Route::get('/employes/{id}', [EmployeController::class, 'show']);

    Route::post('/employes', [EmployeController::class, 'store']);

    Route::put('/employes/{id}', [EmployeController::class, 'update']);

    Route::delete('/employes/{id}', [EmployeController::class, 'destroy']);


    /*
    |--------------------------------------------------------------------------
    | Demandes
    |--------------------------------------------------------------------------
    */

    Route::get('/demandes', [DemandeController::class, 'index']);

    Route::get('/demandes/{id}', [DemandeController::class, 'show']);

    Route::post('/demandes', [DemandeController::class, 'store']);

    Route::put('/demandes/{id}', [DemandeController::class, 'update']);

    Route::delete('/demandes/{id}', [DemandeController::class, 'destroy']);


    /*
    |--------------------------------------------------------------------------
    | Tâches
    |--------------------------------------------------------------------------
    */

    Route::get('/taches', [TacheController::class, 'index']);

    Route::get('/taches/{id}', [TacheController::class, 'show']);

    Route::post('/taches', [TacheController::class, 'store']);

    Route::put('/taches/{id}', [TacheController::class, 'update']);

    Route::delete('/taches/{id}', [TacheController::class, 'destroy']);


    /*
    |--------------------------------------------------------------------------
    | Rapports de tâches
    |--------------------------------------------------------------------------
    */

    Route::get('/rapports-taches', [RapportTacheController::class, 'index']);

    Route::get('/rapports-taches/{id}', [RapportTacheController::class, 'show']);

    Route::post('/rapports-taches', [RapportTacheController::class, 'store']);

    Route::put('/rapports-taches/{id}', [RapportTacheController::class, 'update']);

    Route::delete('/rapports-taches/{id}', [RapportTacheController::class, 'destroy']);


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get('/notifications', [NotificationController::class, 'index']);

    Route::get('/notifications/{id}', [NotificationController::class, 'show']);

    Route::put('/notifications/{id}', [NotificationController::class, 'update']);

    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

});
