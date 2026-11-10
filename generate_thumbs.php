<?php
/**
 * Génère les miniatures pour un album donné.
 * Appelé en arrière-plan depuis le frontend.
 * Usage: generate_thumbs.php?album=nom-album
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$photosDir = __DIR__ . '/photos';
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$thumbWidth = 400;
$thumbQuality = 75;

$albumPath = isset($_GET['album']) ? $_GET['album'] : '';
if (!$albumPath) {
    echo json_encode(['error' => 'Paramètre album manquant']);
    exit;
}

// Sécurité : empêcher la traversée de répertoire
$albumPath = str_replace('\\', '/', $albumPath);
if (strpos($albumPath, '..') !== false) {
    echo json_encode(['error' => 'Chemin invalide']);
    exit;
}

$fullDir = $photosDir . '/' . $albumPath;
if (!is_dir($fullDir)) {
    echo json_encode(['error' => 'Album introuvable']);
    exit;
}

$thumbsDir = $fullDir . '/thumbs';
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

$generated = 0;
$skipped = 0;
$errors = [];

$files = scandir($fullDir);
foreach ($files as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) continue;

    $srcPath = $fullDir . '/' . $file;
    $thumbPath = $thumbsDir . '/' . $file;

    // Si la miniature existe déjà et est plus récente que l'original, on skip
    if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($srcPath)) {
        $skipped++;
        continue;
    }

    try {
        $img = null;
        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                $img = @imagecreatefromjpeg($srcPath);
                break;
            case 'png':
                $img = @imagecreatefrompng($srcPath);
                break;
            case 'gif':
                $img = @imagecreatefromgif($srcPath);
                break;
            case 'webp':
                $img = @imagecreatefromwebp($srcPath);
                break;
            case 'bmp':
                $img = @imagecreatefrombmp($srcPath);
                break;
        }

        if (!$img) {
            $errors[] = $file;
            continue;
        }

        $origW = imagesx($img);
        $origH = imagesy($img);

        if ($origW <= $thumbWidth) {
            // Image déjà assez petite, copier telle quelle
            copy($srcPath, $thumbPath);
            imagedestroy($img);
            $generated++;
            continue;
        }

        $newW = $thumbWidth;
        $newH = (int)round($origH * ($thumbWidth / $origW));

        $thumb = imagecreatetruecolor($newW, $newH);

        // Préserver la transparence pour PNG/GIF
        if ($ext === 'png' || $ext === 'gif') {
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
            imagefill($thumb, 0, 0, $transparent);
        }

        imagecopyresampled($thumb, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

        switch ($ext) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($thumb, $thumbPath, $thumbQuality);
                break;
            case 'png':
                imagepng($thumb, $thumbPath, 6);
                break;
            case 'gif':
                imagegif($thumb, $thumbPath);
                break;
            case 'webp':
                imagewebp($thumb, $thumbPath, $thumbQuality);
                break;
            case 'bmp':
                imagejpeg($thumb, $thumbPath, $thumbQuality);
                break;
        }

        imagedestroy($img);
        imagedestroy($thumb);
        $generated++;
    } catch (Exception $e) {
        $errors[] = $file;
    }
}

echo json_encode([
    'album' => $albumPath,
    'generated' => $generated,
    'skipped' => $skipped,
    'errors' => $errors,
], JSON_UNESCAPED_UNICODE);
