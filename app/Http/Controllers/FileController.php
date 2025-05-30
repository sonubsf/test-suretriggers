<?php

namespace App\Http\Controllers;

use finfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class FileController extends Controller
{
    public function fetchBinary(Request $request): Response
    {
        $request->validate([
            'url' => 'required|url',
        ]);

        $url = $request->get('url');
        if (!is_string($url) || !filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['error' => 'Invalid file URL.'], Response::HTTP_BAD_REQUEST);
        }

        $path = parse_url($url, PHP_URL_PATH);
        if (!is_string($path)) {
            return response()->json(['error' => 'Unable to parse path.'], Response::HTTP_BAD_REQUEST);
        }

        if (preg_match('/\.env|\.php|\.ini|\.conf|\.config|\.sql|\.sh/i', $path)) {
            return response()->json(['error' => 'Access to this file type is forbidden.'], Response::HTTP_FORBIDDEN);
        }

        $blockedHosts = [
            '127.0.0.1',
            'localhost',
            '192.168.0.0/16',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '169.254.0.0/16',
            '0.0.0.0',
            '0.0.0.0/0',
            '169.254.169.254',
        ];

        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || in_array($host, $blockedHosts, true)) {
            return response()->json(['error' => 'Access to this URL is blocked.'], Response::HTTP_FORBIDDEN);
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
            'http' => [
                'timeout' => 30,
                'follow_location' => 0,
                'max_redirects' => 0,
            ],
        ]);

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            return response()->json(['error' => 'Unable to fetch file.'], Response::HTTP_BAD_REQUEST);
        }

        $fileSizeInBytes = strlen($content);
        $maxSizeInBytes = config('constants.max_file_size_mb') * 1024 * 1024;
        if ($fileSizeInBytes > $maxSizeInBytes) {
            return response()->json([
                'error' => 'File exceeds the maximum allowed size of ' . config('media.max_file_size_mb') . ' MB.'
            ], Response::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $mimeType = (new \finfo(FILEINFO_MIME_TYPE))->buffer($content);
        if (!is_string($mimeType)) {
            return response()->json(['error' => 'Unable to determine MIME type.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $blockedTypes = [
            'application/x-php',
            'text/x-php',
            'application/javascript',
            'text/javascript',
            'application/x-sh',
        ];

        if (in_array($mimeType, $blockedTypes, true)) {
            return response()->json(['error' => 'File type not allowed.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        return response($content, 200)->header('Content-Type', $mimeType);
    }

    public function uploadBinaryToS3(Request $request): Response
    {
        $content = $request->getContent();
        $contentType = $request->header('Content-Type');

        if (!is_string($contentType) || empty($content)) {
            return response()->json(['error' => 'Invalid file or Content-Type missing.'], Response::HTTP_BAD_REQUEST);
        }

        $mimeToExtension = [
            'image/png' => '.png',
            'image/jpeg' => '.jpg',
            'image/gif' => '.gif',
            'application/pdf' => '.pdf',
            'image/bmp' => '.bmp',
            'text/plain' => '.txt',
            'image/webp' => '.webp',
            'image/tiff' => '.tiff',
            'image/x-icon' => '.ico',
            'audio/mpeg' => '.mp3',
            'audio/ogg' => '.ogg',
            'audio/wav' => '.wav',
            'video/mp4' => '.mp4',
            'video/ogg' => '.ogg',
            'video/webm' => '.webm',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'application/vnd.ms-excel' => '.xls',
            'application/vnd.oasis.opendocument.text' => '.odt',
            'application/rtf' => '.rtf',
            'application/epub+zip' => '.epub',
        ];

        if (!isset($mimeToExtension[$contentType])) {
            return response()->json(['error' => 'Unsupported file type.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMimeType = $finfo->buffer($content);

        if (!is_string($realMimeType) || $realMimeType !== $contentType) {
            return response()->json(['error' => 'MIME type mismatch.'], Response::HTTP_UNSUPPORTED_MEDIA_TYPE);
        }

        $fileExtension = $mimeToExtension[$contentType];
        $fileName = 'file_' . time() . $fileExtension;
        $filePath = Str::random(60) . '_' . $fileName;

        try {
            Storage::disk('s3-files')->put($filePath, $content);
            $fileUrl = Storage::disk('s3-files')->url($filePath);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'S3 Upload failed.',
                'details' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return response()->json(['file_url' => $fileUrl], Response::HTTP_OK);
    }

}
