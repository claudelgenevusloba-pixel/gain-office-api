<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ForceUtf8
{
    /**
     * Force l'encodage UTF-8 dans toutes les réponses HTTP
     * pour éviter les problèmes d'affichage des caractères accentués (é, ê, â, etc.)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Forcer le charset UTF-8 dans le header Content-Type
        $headers = $response->headers;
        $contentType = $headers->get('Content-Type');

        if ($contentType && strpos($contentType, 'charset=') === false) {
            $headers->set('Content-Type', $contentType . '; charset=utf-8');
        }

        // S'assurer que mbstring utilise UTF-8
        if (function_exists('mb_internal_encoding')) {
            mb_internal_encoding('UTF-8');
        }

        return $response;
    }
}