<?php

namespace App\Utils;

use DateTimeInterface;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class S3Helper
{
    public static function getTemporaryUrl(
        ?string $imagePath,
        DateTimeInterface $expiration,
        ?string $imageSize = null,
        array $options = []
    ): ?string {
        if (empty($imagePath)) {
            return null;
        }

        if (!is_null($imageSize)) {
            $imagePath = Str::replaceFirst('original', $imageSize, $imagePath);
        }

        $filesystem = Storage::disk('s3');

        return cache()->remember(
            $imagePath,
            $expiration,
            function () use ($filesystem, $imagePath, $expiration) {
                return $filesystem->temporaryUrl($imagePath, $expiration);
            }
        );
    }
}
