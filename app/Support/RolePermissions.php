<?php

namespace App\Support;

use App\Models\Utilisateur;

/**
 * ============================================================
 * GAIN OFFICE — PERMISSIONS PAR RÔLE (source unique Laravel)
 * ============================================================
 *
 * Matrice alignée sur le frontend React :
 * - src/config/permissions.js (noms des permissions)
 * - src/config/roles.js       (matrice rôles -> permissions)
 *
 * Il n'existe pas de table "permissions" dans la base :
 * les droits sont dérivés du rôle de l'utilisateur,
 * comme côté React.
 */
class RolePermissions
{
    /**
     * Permissions déclarées de chaque rôle.
     */
    public static function parRole(string $role): array
    {
        $voirDashboard = ['voir_dashboard'];
        $notifications = ['voir_notifications', 'marquer_notification_lue'];

        return match ($role) {

            'Directeur' => array_merge($voirDashboard, [
                'voir_historique',
                'voir_employes',
                'voir_taches', 'creer_tache', 'modifier_tache',
                'supprimer_tache', 'assigner_tache', 'suivre_tache',
                'terminer_tache', 'valider_tache',
                'voir_demandes', 'modifier_demande',
                'traiter_demande', 'accepter_demande', 'refuser_demande',
                'voir_rapports', 'valider_rapport', 'controler_rapport',
                'voir_parametres',
            ], $notifications),

            'RH' => array_merge($voirDashboard, [
                'voir_employes', 'creer_employe', 'modifier_employe',
                'supprimer_employe',
                'voir_taches', 'suivre_tache',
                'voir_demandes', 'creer_demande', 'modifier_demande',
                'traiter_demande', 'accepter_demande', 'refuser_demande',
                'voir_rapports', 'creer_rapport', 'modifier_rapport',
                'voir_parametres',
            ], $notifications),

            'Responsable' => array_merge($voirDashboard, [
                'voir_taches', 'creer_tache', 'modifier_tache',
                'assigner_tache', 'suivre_tache', 'terminer_tache',
                'voir_demandes', 'creer_demande', 'modifier_demande',
                'traiter_demande', 'accepter_demande', 'refuser_demande',
                'voir_rapports', 'creer_rapport', 'modifier_rapport',
                'valider_rapport',
                'voir_parametres',
            ], $notifications),

            'Sous-directeur' => array_merge($voirDashboard, [
                'voir_employes',
                'voir_taches', 'creer_tache', 'modifier_tache',
                'assigner_tache', 'suivre_tache', 'terminer_tache',
                'voir_demandes', 'creer_demande', 'modifier_demande',
                'traiter_demande', 'accepter_demande', 'refuser_demande',
                'voir_rapports', 'creer_rapport', 'modifier_rapport',
                'valider_rapport',
                'voir_parametres',
            ], $notifications),

            'Employe' => array_merge($voirDashboard, [
                'voir_taches', 'suivre_tache', 'terminer_tache',
                'voir_demandes', 'creer_demande', 'modifier_demande',
                'voir_rapports', 'creer_rapport', 'modifier_rapport',
                'voir_parametres',
            ], $notifications),

            /* L'administrateur dispose de toutes les permissions */
            'Administrateur' => [
                'voir_dashboard',
                'voir_historique',
                'voir_employes', 'creer_employe', 'modifier_employe',
                'supprimer_employe',
                'voir_taches', 'creer_tache', 'modifier_tache',
                'supprimer_tache', 'assigner_tache', 'suivre_tache',
                'terminer_tache', 'valider_tache',
                'voir_demandes', 'creer_demande', 'modifier_demande',
                'supprimer_demande', 'traiter_demande',
                'accepter_demande', 'refuser_demande',
                'voir_rapports', 'creer_rapport', 'modifier_rapport',
                'supprimer_rapport', 'valider_rapport',
                'controler_rapport',
                'voir_notifications', 'marquer_notification_lue',
                'supprimer_notification',
                'voir_parametres', 'modifier_parametres',
                'gerer_roles', 'gerer_services', 'gerer_postes',
            ],

            default => [],
        };
    }

    /**
     * L'utilisateur possède-t-il la permission ?
     */
    public static function a(
        Utilisateur $utilisateur,
        string $permission
    ): bool {
        $role = $utilisateur->role?->nom ?? '';

        return in_array(
            $permission,
            self::parRole($role),
            true
        );
    }

    /**
     * Refuser (403) si la permission est absente.
     */
    public static function exiger(
        Utilisateur $utilisateur,
        string $permission
    ): void {
        if (self::a($utilisateur, $permission)) {
            return;
        }

        abort(
            403,
            "Vous n'avez pas la permission ({$permission}) d'effectuer cette action."
        );
    }
}
