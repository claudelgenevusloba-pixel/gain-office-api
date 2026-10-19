<?php

use Illuminate\Support\Facades\Route;

// Contrôleurs API
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DemandeController;
use App\Http\Controllers\Api\EmployeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ParametreEntrepriseController;
use App\Http\Controllers\Api\PosteController;
use App\Http\Controllers\Api\RapportTacheController;
use App\Http\Controllers\Api\TacheController;
use App\Http\Controllers\Api\InvitationController;
// Contrôleur des données de référence
use App\Http\Controllers\ReferenceController;


/*
|--------------------------------------------------------------------------
| API publique
|--------------------------------------------------------------------------
*/

Route::post(
    '/auth/login',
    [AuthController::class, 'login']
);

/*
|--------------------------------------------------------------------------
| Récupération de mot de passe (public, avec limitation de débit)
|--------------------------------------------------------------------------
*/

Route::post(
    '/mot-de-passe-oublie',
    [AuthController::class, 'motDePasseOublie']
);

Route::post(
    '/mot-de-passe-reinitialise',
    [AuthController::class, 'reinitialiserMotDePasse']
);


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
    /*
|--------------------------------------------------------------------------
| Invitations des employés
|--------------------------------------------------------------------------
*/

Route::post(
    '/invitations',
    [InvitationController::class, 'store']
);

Route::post(
    '/invitations/{id}/renvoyer',
    [InvitationController::class, 'renvoyer']
);

    Route::get(
        '/auth/me',
        [AuthController::class, 'me']
    );

    Route::post(
        '/auth/logout',
        [AuthController::class, 'logout']
    );

    Route::put(
        '/auth/password',
        [AuthController::class, 'changerMotDePasse']
    );

    Route::put(
        '/auth/profil',
        [AuthController::class, 'mettreAJourProfil']
    );


    /*
    |--------------------------------------------------------------------------
    | Dashboard
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/dashboard',
        [DashboardController::class, 'index']
    );


    /*
    |--------------------------------------------------------------------------
    | Données de référence
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/roles-disponibles',
        [ReferenceController::class, 'rolesDisponibles']
    );

    Route::get(
        '/roles',
        [ReferenceController::class, 'roles']
    );

    Route::get(
        '/services',
        [ReferenceController::class, 'services']
    );


    /*
    |--------------------------------------------------------------------------
    | Postes
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/postes',
        [PosteController::class, 'index']
    );

    Route::post(
        '/postes',
        [PosteController::class, 'store']
    );

    Route::get(
        '/postes/{id}',
        [PosteController::class, 'show']
    );

    Route::put(
        '/postes/{id}',
        [PosteController::class, 'update']
    );

    Route::delete(
        '/postes/{id}',
        [PosteController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Paramètres entreprise
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/parametres',
        [ParametreEntrepriseController::class, 'index']
    );

    Route::put(
        '/parametres',
        [ParametreEntrepriseController::class, 'store']
    );


    /*
    |--------------------------------------------------------------------------
    | Employés
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/employes',
        [EmployeController::class, 'index']
    );

    Route::get(
        '/employes/{id}',
        [EmployeController::class, 'show']
    );

    Route::post(
        '/employes',
        [EmployeController::class, 'store']
    );

    Route::put(
        '/employes/{id}',
        [EmployeController::class, 'update']
    );

    Route::delete(
        '/employes/{id}',
        [EmployeController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Demandes
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/demandes',
        [DemandeController::class, 'index']
    );

    Route::get(
        '/demandes/{id}',
        [DemandeController::class, 'show']
    );

    Route::post(
        '/demandes',
        [DemandeController::class, 'store']
    );

    Route::put(
        '/demandes/{id}',
        [DemandeController::class, 'update']
    );

    Route::delete(
        '/demandes/{id}',
        [DemandeController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Tâches
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/taches',
        [TacheController::class, 'index']
    );

    Route::get(
        '/taches/{id}',
        [TacheController::class, 'show']
    );

    Route::post(
        '/taches',
        [TacheController::class, 'store']
    );

    Route::put(
        '/taches/{id}',
        [TacheController::class, 'update']
    );

    Route::delete(
        '/taches/{id}',
        [TacheController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Rapports de tâches
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/rapports-taches',
        [RapportTacheController::class, 'index']
    );

    Route::get(
        '/rapports-taches/{id}',
        [RapportTacheController::class, 'show']
    );

    Route::post(
        '/rapports-taches',
        [RapportTacheController::class, 'store']
    );

    Route::put(
        '/rapports-taches/{id}',
        [RapportTacheController::class, 'update']
    );

    Route::delete(
        '/rapports-taches/{id}',
        [RapportTacheController::class, 'destroy']
    );


    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    */

    Route::get(
        '/notifications',
        [NotificationController::class, 'index']
    );

    Route::get(
        '/notifications/{id}',
        [NotificationController::class, 'show']
    );

    Route::put(
        '/notifications/{id}',
        [NotificationController::class, 'read']
    );

    Route::post(
        '/notifications/read-all',
        [NotificationController::class, 'readAll']
    );

    Route::delete(
        '/notifications/{id}',
        [NotificationController::class, 'destroy']
    );

});
/*
|--------------------------------------------------------------------------
| Activation des comptes
|--------------------------------------------------------------------------
*/

Route::get(
    '/invitations/{token}',
    [InvitationController::class, 'verify']
);

Route::post(
    '/invitations/activate',
    [InvitationController::class, 'activate']
);
