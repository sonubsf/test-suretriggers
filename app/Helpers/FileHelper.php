<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class FileHelper
{
    public static function uploadFileToAws(mixed $file, string $filename = 'Default', string $path = ''): string
    {
        $filenameWithExtension = $filename.'.'.$file->getClientOriginalExtension();
        $file->storeAs($path, $filenameWithExtension);

        return self::generateFullAwsUrl($filename, $file->getClientOriginalExtension());
    }

    public static function uploadBase64ImageToAws(
        mixed $base64Image,
        mixed $filename = 'Default',
        string $path = ''
    ): string {
        $dataParts = explode(',', $base64Image);
        $imageData = $dataParts[1];
        $image = base64_decode($imageData);
        $extension = 'png';
        $filenameWithExtension = $filename.'.'.$extension;
        Storage::disk()->put($path.'/'.$filenameWithExtension, $image);

        return self::generateFullAwsUrl($filename);
    }

    public static function generateFullAwsUrl(mixed $filename, string $extension = 'png'): string
    {
        if (str_contains($filename, '@')) {
            $filename = explode('@', $filename)[0];
        }

        return self::getAwsFolderPath().'/'.$filename.'.'.$extension;
    }

    public static function getAwsFolderPath(): string
    {
        return 'https://'.config('aws.bucket').
            '.s3.'.config('aws.region').
            '.amazonaws.com/'.config('aws.bucket_folder');
    }

    public static function copyImageOnAws(mixed $originalIntegrationName, mixed $newIntegrationName): void
    {
        $newIntegrationPath = self::generateRelativeAwsPath($newIntegrationName);
        $originalIntegrationPath = self::generateRelativeAwsPath($originalIntegrationName);
        try {
            Storage::disk()->copy($originalIntegrationPath, $newIntegrationPath);
        } catch (\Exception $e) {
            \Log::error('Error copying file on S3: '.$e->getMessage());
        }
    }

    public static function generateRelativeAwsPath(mixed $filename, string $extension = 'png'): string
    {
        return $filename.'.'.$extension;
    }
}
