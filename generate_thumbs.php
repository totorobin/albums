<?php
/**
 * Génère UNE miniature pour une image donnée d'un album.
 * Usage: generate_thumbs.php?album=nom-album&file=image.jpg
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$photosDir = __DIR__ . '/photos';
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
$thumbWidth = 400;
$thumbQuality = 75;

$albumPath = isset($_GET['album']) ? $_GET['album'] : '';
$fileName = isset($_GET['file']) ? $_GET['file'] : '';

if (!$albumPath || !$fileName) {
    echo json_encode(['error' => 'Paramètres album et file requis']);
    exit;
}

// Sécurité : empêcher la traversée de répertoire
$albumPath = str_replace('\\', '/', $albumPath);
if (strpos($albumPath, '..') !== false || strpos($fileName, '..') !== false || strpos($fileName, '/') !== false) {
    echo json_encode(['error' => 'Chemin invalide']);
    exit;
}

$fullDir = $photosDir . '/' . $albumPath;
if (!is_dir($fullDir)) {
    echo json_encode(['error' => 'Album introuvable']);
    exit;
}

$srcPath = $fullDir . '/' . $fileName;
if (!file_exists($srcPath)) {
    echo json_encode(['error' => 'Fichier introuvable']);
    exit;
}

$ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
if (!in_array($ext, $extensions)) {
    echo json_encode(['error' => 'Extension non supportée']);
    exit;
}

$thumbsDir = $fullDir . '/thumbs';
if (!is_dir($thumbsDir)) {
    mkdir($thumbsDir, 0755, true);
}

$thumbPath = $thumbsDir . '/' . $fileName;

// Si la miniature existe déjà et est plus récente, on skip
if (file_exists($thumbPath) && filemtime($thumbPath) >= filemtime($srcPath)) {
    echo json_encode(['status' => 'skipped', 'file' => $fileName]);
    exit;
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
        echo json_encode(['error' => 'Impossible de lire l\'image', 'file' => $fileName]);
        exit;
    }

    $origW = imagesx($img);
    $origH = imagesy($img);

    if ($origW <= $thumbWidth) {
        copy($srcPath, $thumbPath);
        imagedestroy($img);
        echo json_encode(['status' => 'generated', 'file' => $fileName]);
        exit;
    }

    $newW = $thumbWidth;
    $newH = (int)round($origH * ($thumbWidth / $origW));

    $thumb = imagecreatetruecolor($newW, $newH);

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
    echo json_encode(['status' => 'generated', 'file' => $fileName]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Erreur lors de la génération', 'file' => $fileName]);
}
