<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\ImageManager;

/**
 * Product photos live in public/uploads/product (same place and naming as v1)
 * and the DB stores the path relative to public/, e.g. uploads/product/1601….jpg.
 */
class ProductImage
{
    public const DIRECTORY = 'uploads/product';

    public const SIZE = 300;

    public static function store(UploadedFile $file): string
    {
        $directory = public_path(self::DIRECTORY);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $relative = self::DIRECTORY.'/'.time().random_int(1000, 9999).'.jpg';

        ImageManager::usingDriver(Driver::class)
            ->decodePath($file->getRealPath())
            ->cover(self::SIZE, self::SIZE)
            ->encodeUsingFormat(Format::JPEG, quality: 85)
            ->save(public_path($relative));

        return $relative;
    }
}
