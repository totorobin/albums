<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$photosDir = __DIR__ . '/photos';
$photosUrl = 'photos';
$extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

function findFirstImageInSubdirs($dirPath, $dirUrl, $extensions) {
    $entries = scandir($dirPath);
    sort($entries);
    foreach ($entries as $name) {
        if ($name === '.' || $name === '..' || $name === 'thumbs') continue;
        $subPath = $dirPath . '/' . $name;
        if (!is_dir($subPath)) continue;
        $subUrl = $dirUrl . '/' . rawurlencode($name);
        $files = scandir($subPath);
        sort($files);
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                return $subUrl . '/' . rawurlencode($file);
            }
        }
        // Chercher récursivement dans les sous-sous-dossiers
        $found = findFirstImageInSubdirs($subPath, $subUrl, $extensions);
        if ($found !== null) return $found;
    }
    return null;
}

function scanAlbums($dirPath, $baseUrl, $prefix, $extensions) {
    $albums = [];
    if (!is_dir($dirPath)) return $albums;

    $dirs = array_filter(scandir($dirPath), function ($name) use ($dirPath) {
        return $name !== '.' && $name !== '..' && $name !== 'thumbs' && is_dir($dirPath . '/' . $name);
    });

    foreach ($dirs as $dir) {
        $subPath = $dirPath . '/' . $dir;
        $albumId = $prefix ? $prefix . '/' . $dir : $dir;
        $albumUrl = $baseUrl . '/' . rawurlencode($dir);

        // Priorité : _info.md (nouveau nom), fallback info.md (rétrocompatibilité)
        $infoPath = file_exists($subPath . '/_info.md') ? $subPath . '/_info.md' : $subPath . '/info.md';
        if (!file_exists($infoPath)) {
            $infoPath = $subPath . '/_info.md';
            $template = "# $dir\n\n- **Date** : \n- **Auteur** : \n- **Lieu** : \n- **thumbnail** : \n\n";
            file_put_contents($infoPath, $template);
        }
        $info = file_get_contents($infoPath);

        $images = [];
        $imageNames = [];
        $thumbs = [];
        $thumbsDir = $subPath . '/thumbs';
        $hasThumbs = is_dir($thumbsDir);
        $files = scandir($subPath);
        foreach ($files as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (in_array($ext, $extensions)) {
                $images[] = $albumUrl . '/' . rawurlencode($file);
                $imageNames[] = $file;
                if ($hasThumbs && file_exists($thumbsDir . '/' . $file)) {
                    $thumbs[] = $albumUrl . '/thumbs/' . rawurlencode($file);
                } else {
                    $thumbs[] = null;
                }
            }
        }

        $title = $dir;
        if (preg_match('/^#\s+(.+)$/m', $info, $matches)) {
            $title = $matches[1];
        }

        // Extraire date, auteur, lieu depuis info.md
        $date = null;
        $author = null;
        $lieu = null;
        if (preg_match('/^-\s*\*\*Date\*\*\s*:\s*(.+)$/im', $info, $m)) $date = trim($m[1]);
        if (preg_match('/^-\s*\*\*Auteur\*\*\s*:\s*(.+)$/im', $info, $m)) $author = trim($m[1]);
        if (preg_match('/^-\s*\*\*Lieu\*\*\s*:\s*(.+)$/im', $info, $m)) $lieu = trim($m[1]);

        // Thumbnail : priorité info.md, sinon première photo du dossier, sinon première photo d'un sous-dossier
        $thumbnail = count($images) > 0 ? $images[0] : findFirstImageInSubdirs($subPath, $albumUrl, $extensions);
        if (preg_match('/^-\s*\*\*thumbnail\*\*\s*:\s*(.+)$/im', $info, $thumbMatch)) {
            $thumbFile = trim($thumbMatch[1]);
            if ($thumbFile !== '') {
                $idx = array_search($thumbFile, $imageNames);
                if ($idx !== false) {
                    $thumbnail = $images[$idx];
                } else {
                    // Chercher dans un sous-dossier par chemin relatif
                    $thumbSubPath = $subPath . '/' . $thumbFile;
                    $thumbSubUrl = $albumUrl . '/' . implode('/', array_map('rawurlencode', explode('/', $thumbFile)));
                    if (file_exists($thumbSubPath)) {
                        $thumbnail = $thumbSubUrl;
                    }
                }
            }
        }

        $thumbnailThumb = null;
        $thumbIdx = array_search($thumbnail, $images);
        if ($thumbIdx !== false && isset($thumbs[$thumbIdx]) && $thumbs[$thumbIdx] !== null) {
            $thumbnailThumb = $thumbs[$thumbIdx];
        }

        $albums[] = [
            'id' => $albumId,
            'title' => $title,
            'info' => $info,
            'thumbnail' => $thumbnail,
            'thumbnailThumb' => $thumbnailThumb,
            'images' => $images,
            'imageNames' => $imageNames,
            'thumbs' => $thumbs,
            'hasThumbs' => $hasThumbs,
            'date' => $date,
            'author' => $author,
            'lieu' => $lieu,
        ];

        // Sous-dossiers récursifs
        $subAlbums = scanAlbums($subPath, $albumUrl, $albumId, $extensions);
        $albums = array_merge($albums, $subAlbums);
    }

    return $albums;
}

$albums = scanAlbums($photosDir, $photosUrl, '', $extensions);
echo json_encode($albums, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
