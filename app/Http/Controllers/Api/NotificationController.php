<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(
        Request $request
    ): JsonResponse {
        $notifications =
            Notification::where(
                'id_utilisateur',
                $request->user()->id_utilisateur
            )
            ->orderByDesc(
                'id_notification'
            )
            ->get();

        return response()->json([
            'success' => true,
            'message' =>
                'Notifications récupérées avec succès.',
            'total' =>
                $notifications->count(),
            'non_lues' =>
                $notifications
                    ->where('lu', false)
                    ->count(),
            'notifications' =>
                $notifications,
        ]);
    }

    public function read(
        Request $request,
        int $id
    ): JsonResponse {
        $notification =
            Notification::where(
                'id_notification',
                $id
            )
            ->where(
                'id_utilisateur',
                $request->user()->id_utilisateur
            )
            ->first();

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' =>
                    'Notification introuvable.',
            ], 404);
        }

        $notification->update([
            'lu' => true,
            'date_lecture' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Notification marquée comme lue.',
            'notification' =>
                $notification,
        ]);
    }

    public function readAll(
        Request $request
    ): JsonResponse {
        Notification::where(
            'id_utilisateur',
            $request->user()->id_utilisateur
        )
        ->where(
            'lu',
            false
        )
        ->update([
            'lu' => true,
            'date_lecture' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' =>
                'Toutes les notifications ont été marquées comme lues.',
        ]);
    }
}
