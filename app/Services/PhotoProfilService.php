<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class PhotoProfilService
{
    private const DISK = 'public';
    private const DIRECTORY = 'photos';

    public function enregistrer(?UploadedFile $photo): ?string
    {
        if (!$photo) {
            return null;
        }

        $chemin = $photo->store(self::DIRECTORY, self::DISK);

        if (!is_string($chemin) || $chemin === '') {
            throw new RuntimeException('La photo n’a pas pu être enregistrée.');
        }

        return $chemin;
    }

    public function supprimer(?string $chemin): void
    {
        if (!$chemin || !str_starts_with($chemin, self::DIRECTORY . '/')) {
            return;
        }

        Storage::disk(self::DISK)->delete($chemin);
    }
}