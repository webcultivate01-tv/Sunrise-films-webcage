<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Profile photo uploads (ProfileController). Stored filenames are always
 * generated here — never taken from the client — so a user can neither choose
 * the path nor overwrite another user's file, and the real file content is
 * checked rather than the claimed MIME type or extension.
 */
final class PhotoUploadService
{
    /** @var array<string, string> real MIME type => stored extension */
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    /**
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    public static function validate(array $file): ?string
    {
        if (in_array($file['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
            return 'That image is too large.';
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            return 'The image could not be uploaded. Please try again.';
        }

        if (!is_uploaded_file($file['tmp_name'])) {
            return 'The image could not be uploaded. Please try again.';
        }

        $maxKb = (int) Config::get('uploads.max_kb', 2048);

        if ($file['size'] <= 0 || $file['size'] > $maxKb * 1024) {
            return sprintf('Please choose an image smaller than %d KB.', $maxKb);
        }

        $mime = (string) (mime_content_type($file['tmp_name']) ?: '');

        if (!isset(self::ALLOWED[$mime]) || @getimagesize($file['tmp_name']) === false) {
            return 'Please upload a JPG, PNG, GIF or WEBP image.';
        }

        return null;
    }

    /**
     * Move an already-validated upload into public/uploads under a random
     * name and return that filename for storage on the user's row.
     *
     * @param array{name: string, type: string, tmp_name: string, error: int, size: int} $file
     */
    public static function store(array $file): string
    {
        $mime      = (string) mime_content_type($file['tmp_name']);
        $extension = self::ALLOWED[$mime] ?? 'jpg';
        $filename  = bin2hex(random_bytes(16)) . '.' . $extension;
        $path      = self::directory() . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $path)) {
            throw new \RuntimeException('The uploaded image could not be saved.');
        }

        chmod($path, 0644);

        return $filename;
    }

    /**
     * Remove a previously stored photo. A missing file is not an error - the
     * caller is replacing or clearing a photo either way.
     */
    public static function delete(string $filename): void
    {
        $path = self::directory() . '/' . basename($filename);

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private static function directory(): string
    {
        return (string) Config::get('uploads.photos_path');
    }
}
