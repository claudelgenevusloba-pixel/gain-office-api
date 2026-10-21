<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceUtf8
{
    /**
     * Force l'encodage UTF-8 et désactive le cache HTTP
     * dans toutes les réponses pour éviter :
     * - les problèmes d'affichage des caractères accentués (é, ê, â, etc.)
     * - le cache navigateur qui empêche de voir les modifications en base de données
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;

        // 1. Forcer le charset UTF-8 dans le header Content-Type
        $contentType = $headers->get('Content-Type');
        if ($contentType && strpos($contentType, 'charset=') === false) {
            $headers->set('Content-Type', $contentType . '; charset=utf-8');
        }

        // 2. Désactiver le cache HTTP pour toutes les réponses API et web
        // Cela garantit que les modifications en base de données sont visibles
        // immédiatement après rechargement, sans qu'il soit nécessaire de vider
        // le cache navigateur manuellement.
        $headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        $headers->set('Pragma', 'no-cache');
        $headers->set('Expires', '0');

        // S'assurer que mbstring utilise UTF-8
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        return $response;
    }
}