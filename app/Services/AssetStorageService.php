<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AssetStorageService
{
    public function store(UploadedFile $file): array
    {
        $disk = config('production.asset_disk');
        $directory = 'assets/'.now()->format('Y/m');
        $path = $file->store($directory, $disk);
        $thumbnail = $this->createThumbnail($disk, $path, $file->getMimeType());

        return [
            'disk' => $disk,
            'file_path' => $path,
            'thumbnail_path' => $thumbnail,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
        ];
    }

    public function createThumbnail(string $disk, string $path, ?string $mime): ?string
    {
        if (! $mime || ! str_starts_with($mime, 'image/') || ! function_exists('imagecreatefromstring')) {
            return null;
        }
        $source = @imagecreatefromstring(Storage::disk($disk)->get($path));
        if (! $source) {
            return null;
        }
        $width = imagesx($source);
        $height = imagesy($source);
        $targetWidth = min(640, $width);
        $targetHeight = max(1, (int) round($height * ($targetWidth / $width)));
        $thumb = imagecreatetruecolor($targetWidth, $targetHeight);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);
        ob_start();
        imagejpeg($thumb, null, 82);
        $bytes = ob_get_clean();
        imagedestroy($source);
        imagedestroy($thumb);
        $thumbPath = 'thumbnails/'.Str::uuid().'.jpg';
        Storage::disk($disk)->put($thumbPath, $bytes);

        return $thumbPath;
    }
}
