<?php

function organizer_profile_image_url($profilePic): string
{
    $profilePic = trim((string) ($profilePic ?? ''));

    if ($profilePic === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $profilePic)) {
        return $profilePic;
    }

    $normalizedPath = str_replace('\\', '/', $profilePic);
    $fileName = basename($normalizedPath);
    $projectRoot = dirname(__DIR__);

    $possibleFiles = [
        $projectRoot . '/actions/uploads/profiles/' . $fileName,
        $projectRoot . '/uploads/profiles/' . $fileName,
        $projectRoot . '/uploads/profile/' . $fileName,
        $projectRoot . '/uploads/' . $fileName,
        $projectRoot . '/assets/uploads/profiles/' . $fileName,
        $projectRoot . '/assets/images/' . $fileName,
    ];

    $possibleUrls = [
        '../actions/uploads/profiles/' . rawurlencode($fileName),
        '../uploads/profiles/' . rawurlencode($fileName),
        '../uploads/profile/' . rawurlencode($fileName),
        '../uploads/' . rawurlencode($fileName),
        '../assets/uploads/profiles/' . rawurlencode($fileName),
        '../assets/images/' . rawurlencode($fileName),
    ];

    foreach ($possibleFiles as $index => $filePath) {
        if (is_file($filePath)) {
            return $possibleUrls[$index] . '?v=' . filemtime($filePath);
        }
    }

    return '';
}
