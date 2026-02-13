<?php

declare(strict_types=1);

namespace DuelDesk\Controllers;

use DuelDesk\Http\Response;
use DuelDesk\Repositories\GameRepository;
use DuelDesk\Support\Auth;
use DuelDesk\Support\Csrf;
use DuelDesk\Support\Flash;
use DuelDesk\View;

final class AdminGameController
{
    /** @param array<string, string> $params */
    public function index(array $params = []): void
    {
        Auth::requireAdmin();

        $repo = new GameRepository();
        $query = trim((string)($_GET['q'] ?? ''));
        $sort = trim((string)($_GET['sort'] ?? 'name'));
        if (!in_array($sort, ['name', 'newest'], true)) {
            $sort = 'name';
        }

        View::render('admin/games', [
            'title' => 'Jeux | Admin | DuelDesk',
            'games' => $repo->search($query, $sort),
            'old' => ['name' => ''],
            'errors' => [],
            'csrfToken' => Csrf::token(),
            'imageReq' => $this->imageRequirements(),
            'query' => $query,
            'sort' => $sort,
        ]);
    }

    /** @param array<string, string> $params */
    public function create(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $name = trim((string)($_POST['name'] ?? ''));
        $old = ['name' => $name];
        $errors = [];

        if ($name === '' || $this->strlenSafe($name) < 2 || $this->strlenSafe($name) > 80) {
            $errors['name'] = 'Nom requis (2 a 80).';
        }

        $repo = new GameRepository();
        if ($name !== '' && $repo->findByName($name) !== null) {
            $errors['name'] = 'Ce jeu existe deja.';
        }

        $req = $this->imageRequirements();
        $imageMeta = null;

        if ($errors === []) {
            $imageMeta = $this->validateImage($_FILES['image'] ?? null, $req['mime'], $req['width'], $req['height']);
            if ($imageMeta['ok'] !== true) {
                $errors['image'] = (string)$imageMeta['error'];
            }
        }

        if ($errors !== []) {
            View::render('admin/games', [
                'title' => 'Jeux | Admin | DuelDesk',
                'games' => $repo->all(),
                'old' => $old,
                'errors' => $errors,
                'csrfToken' => Csrf::token(),
                'imageReq' => $req,
                'query' => '',
                'sort' => 'name',
            ]);
            return;
        }

        $slug = $repo->uniqueSlug($name);
        $filename = $slug . $req['ext'];

        $absDir = DUELDESK_ROOT . '/public/uploads/games';
        if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
            Response::badRequest('Upload: dossier non accessible.');
        }

        $absPath = $absDir . '/' . $filename;
        $tmpPath = (string)($imageMeta['tmp_name'] ?? '');

        if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
            Response::badRequest('Upload: echec.');
        }

        $tmpOut = $absPath . '.tmp-' . bin2hex(random_bytes(4));
        if (!$this->writeCroppedPng($tmpPath, $tmpOut, $req['width'], $req['height'])) {
            @unlink($tmpOut);
            Response::badRequest('Upload: traitement image impossible.');
        }
        if (!@rename($tmpOut, $absPath)) {
            @unlink($tmpOut);
            Response::badRequest('Upload: echec.');
        }

        @chmod($absPath, 0644);

        $imagePath = '/uploads/games/' . $filename;
        $repo->create($name, $slug, $imagePath, (int)$req['width'], (int)$req['height'], (string)$req['mime']);

        Flash::set('success', 'Jeu ajoute.');
        Response::redirect('/admin/games');
    }

    /** @param array<string, string> $params */
    public function edit(array $params = []): void
    {
        Auth::requireAdmin();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new GameRepository();
        $game = $repo->findById($id);
        if ($game === null) {
            Response::notFound();
        }

        View::render('admin/game_edit', [
            'title' => 'Modifier un jeu | Admin | DuelDesk',
            'game' => $game,
            'old' => [
                'name' => (string)($game['name'] ?? ''),
            ],
            'errors' => [],
            'csrfToken' => Csrf::token(),
            'imageReq' => $this->imageRequirements(),
        ]);
    }

    /** @param array<string, string> $params */
    public function update(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new GameRepository();
        $game = $repo->findById($id);
        if ($game === null) {
            Response::notFound();
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $old = ['name' => $name];
        $errors = [];

        if ($name === '' || $this->strlenSafe($name) < 2 || $this->strlenSafe($name) > 80) {
            $errors['name'] = 'Nom requis (2 a 80).';
        }

        $existing = $name !== '' ? $repo->findByName($name) : null;
        if (is_array($existing) && (int)($existing['id'] ?? 0) !== $id) {
            $errors['name'] = 'Ce jeu existe deja.';
        }

        $req = $this->imageRequirements();

        $imageMeta = null;
        $file = $_FILES['image'] ?? null;
        $hasFile = is_array($file) && (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;

        if ($errors === [] && $hasFile) {
            $imageMeta = $this->validateImage($file, $req['mime'], $req['width'], $req['height']);
            if ($imageMeta['ok'] !== true) {
                $errors['image'] = (string)$imageMeta['error'];
            }
        }

        if ($errors !== []) {
            View::render('admin/game_edit', [
                'title' => 'Modifier un jeu | Admin | DuelDesk',
                'game' => $game,
                'old' => $old,
                'errors' => $errors,
                'csrfToken' => Csrf::token(),
                'imageReq' => $req,
            ]);
            return;
        }

        if ($name !== (string)($game['name'] ?? '')) {
            $repo->updateName($id, $name);
        }

        if ($hasFile) {
            $imagePath = (string)($game['image_path'] ?? '');
            if ($imagePath === '' || !str_starts_with($imagePath, '/uploads/games/')) {
                Response::badRequest('Chemin image invalide.');
            }

            $absPath = DUELDESK_ROOT . '/public' . $imagePath;
            $absDir = dirname($absPath);

            if (!is_dir($absDir) && !@mkdir($absDir, 0775, true)) {
                Response::badRequest('Upload: dossier non accessible.');
            }

            $tmpPath = (string)($imageMeta['tmp_name'] ?? '');
            if ($tmpPath === '' || !is_uploaded_file($tmpPath)) {
                Response::badRequest('Upload: echec.');
            }

            $tmpOut = $absPath . '.tmp-' . bin2hex(random_bytes(4));
            if (!$this->writeCroppedPng($tmpPath, $tmpOut, $req['width'], $req['height'])) {
                @unlink($tmpOut);
                Response::badRequest('Upload: traitement image impossible.');
            }
            if (!@rename($tmpOut, $absPath)) {
                @unlink($tmpOut);
                Response::badRequest('Upload: echec.');
            }

            @chmod($absPath, 0644);
            $repo->updateImageMeta($id, (int)$req['width'], (int)$req['height'], (string)$req['mime']);
        }

        Flash::set('success', 'Jeu mis a jour.');
        Response::redirect('/admin/games');
    }

    /** @param array<string, string> $params */
    public function delete(array $params = []): void
    {
        Auth::requireAdmin();

        if (!Csrf::validate($_POST['csrf_token'] ?? null)) {
            Response::badRequest('Invalid CSRF token');
        }
        Csrf::rotate();

        $id = (int)($params['id'] ?? 0);
        if ($id <= 0) {
            Response::notFound();
        }

        $repo = new GameRepository();
        $game = $repo->findById($id);
        if ($game === null) {
            Response::notFound();
        }

        $imagePath = (string)($game['image_path'] ?? '');

        $repo->delete($id);

        if ($imagePath !== '' && str_starts_with($imagePath, '/uploads/games/')) {
            $absPath = DUELDESK_ROOT . '/public' . $imagePath;
            if (is_file($absPath)) {
                @unlink($absPath);
            }
        }

        Flash::set('success', 'Jeu supprime.');
        Response::redirect('/admin/games');
    }

    /** @return array{width:int,height:int,mime:string,ext:string,label:string} */
    private function imageRequirements(): array
    {
        $w = (int)(getenv('GAME_IMAGE_WIDTH') ?: 512);
        $h = (int)(getenv('GAME_IMAGE_HEIGHT') ?: 512);

        if ($w <= 0) {
            $w = 512;
        }
        if ($h <= 0) {
            $h = 512;
        }

        return [
            'width' => $w,
            'height' => $h,
            'mime' => 'image/png',
            'ext' => '.png',
            'label' => "PNG (auto resize/crop -> {$w}x{$h})",
        ];
    }

    /**
     * @param mixed $file
     * @return array{ok:bool,error?:string,tmp_name?:string,mime?:string,width?:int,height?:int}
     */
    private function validateImage(mixed $file, string $mime, int $w, int $h): array
    {
        if (!is_array($file)) {
            return ['ok' => false, 'error' => 'Image requise.'];
        }

        $err = (int)($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($err !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'error' => 'Upload image invalide.'];
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > 6 * 1024 * 1024) {
            return ['ok' => false, 'error' => 'Image trop lourde (max 6MB).'];
        }

        $tmp = (string)($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            return ['ok' => false, 'error' => 'Fichier manquant.'];
        }

        $info = @getimagesize($tmp);
        if (!is_array($info) || !isset($info[0], $info[1])) {
            return ['ok' => false, 'error' => 'Image invalide.'];
        }

        $iw = (int)$info[0];
        $ih = (int)$info[1];
        $im = (string)($info['mime'] ?? '');

        if ($im !== $mime) {
            return ['ok' => false, 'error' => "Format requis: {$mime}."];
        }

        return ['ok' => true, 'tmp_name' => $tmp, 'mime' => $im, 'width' => $iw, 'height' => $ih];
    }

    private function writeCroppedPng(string $srcPath, string $destPath, int $targetW, int $targetH): bool
    {
        if (!function_exists('imagecreatefrompng')) {
            return false;
        }

        $src = @imagecreatefrompng($srcPath);
        if (!$src) {
            return false;
        }

        $sw = (int)imagesx($src);
        $sh = (int)imagesy($src);
        if ($sw <= 0 || $sh <= 0) {
            imagedestroy($src);
            return false;
        }

        $srcAspect = $sw / $sh;
        $dstAspect = $targetW / $targetH;

        if ($srcAspect > $dstAspect) {
            // Source is wider: crop width.
            $cropH = $sh;
            $cropW = (int)round($sh * $dstAspect);
            $cropX = (int)floor(($sw - $cropW) / 2);
            $cropY = 0;
        } else {
            // Source is taller: crop height.
            $cropW = $sw;
            $cropH = (int)round($sw / $dstAspect);
            $cropX = 0;
            $cropY = (int)floor(($sh - $cropH) / 2);
        }

        $dst = imagecreatetruecolor($targetW, $targetH);
        if (!$dst) {
            imagedestroy($src);
            return false;
        }

        // Preserve alpha channel.
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $targetW, $targetH, $transparent);

        $ok = imagecopyresampled($dst, $src, 0, 0, $cropX, $cropY, $targetW, $targetH, $cropW, $cropH);
        if (!$ok) {
            imagedestroy($dst);
            imagedestroy($src);
            return false;
        }

        $ok = imagepng($dst, $destPath, 6);
        imagedestroy($dst);
        imagedestroy($src);

        return (bool)$ok;
    }

    private function strlenSafe(string $value): int
    {
        if (function_exists('mb_strlen')) {
            return (int)mb_strlen($value);
        }

        return strlen($value);
    }
}
