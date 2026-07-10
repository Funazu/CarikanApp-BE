<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageService
{
    /**
     * Compress and convert an uploaded image to WebP format.
     *
     * @param UploadedFile $file The uploaded file.
     * @param string $destinationPath The directory to save the file (relative to public_path).
     * @param int $quality Compression quality (0-100).
     * @return string The filename of the saved WebP image.
     */
    public static function convertToWebp(UploadedFile $file, string $destinationPath, int $quality = 80): string
    {
        $filePath = $file->getRealPath();
        $mime = $file->getMimeType();

        // Load image resource based on mime type
        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                $image = imagecreatefromjpeg($filePath);
                break;
            case 'image/png':
                $image = imagecreatefrompng($filePath);
                if ($image) {
                    // Keep transparency for PNG
                    imagepalettetotruecolor($image);
                    imagealphablending($image, true);
                    imagesavealpha($image, true);
                }
                break;
            case 'image/gif':
                $image = imagecreatefromgif($filePath);
                break;
            case 'image/webp':
                $image = imagecreatefromwebp($filePath);
                break;
            default:
                // Fallback: if GD cannot load, just move the original file
                $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                $file->move(public_path($destinationPath), $filename);
                return $filename;
        }

        if (!$image) {
            // If image creation failed, fallback to moving the original file
            $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $file->move(public_path($destinationPath), $filename);
            return $filename;
        }

        // Convert palette-based images to truecolor to avoid GD errors with WebP
        if (!imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        // Generate unique filename with .webp extension
        $filename = time() . '_' . uniqid() . '.webp';
        $fullPath = public_path($destinationPath . '/' . $filename);

        // Ensure directory exists
        if (!file_exists(public_path($destinationPath))) {
            mkdir(public_path($destinationPath), 0755, true);
        }

        // Save as WebP
        imagewebp($image, $fullPath, $quality);
        imagedestroy($image);

        return $filename;
    }
}
