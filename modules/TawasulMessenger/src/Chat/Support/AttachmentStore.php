<?php
namespace Tos\Module\TawasulChat\Support;

use Tos\Module\TawasulChat\Domain\ChatGateway;

/**
 * Files sent in a message.
 *
 * TawasulOS keeps uploads on disk and stores the relative path, and chat does
 * the same. The rules that matter here are the ones a chat module introduces
 * and a document module does not: a hard extension denylist, because an
 * attachment is served back to every participant and an uploaded .php would be
 * executable by the web server; and an extension allowlist on top of that,
 * because chat is the one place a user can hand an arbitrary file to someone
 * else.
 *
 * A generated name is used for the stored file. The name the sender chose is
 * kept in fileName and shown in the UI, but never reaches the filesystem, so a
 * name like "photo.php" cannot become a path.
 */
class AttachmentStore
{
    /** Never accepted or served, whatever the allowlist says. */
    const FORBIDDEN_EXTENSIONS = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'htaccess', 'htpasswd',
        'cgi', 'pl', 'py', 'sh', 'bash', 'exe', 'com', 'bat', 'cmd', 'msi', 'dll', 'so',
        'js', 'mjs', 'html', 'htm', 'xhtml', 'svg', 'xml',
    ];

    /** What chat accepts. Keeps the denylist above honest for the cases it misses. */
    const ALLOWED_EXTENSIONS = [
        // images
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico',
        // video
        'mp4', 'm4v', 'webm', 'mov',
        // audio, including voice notes
        'mp3', 'm4a', 'ogg', 'oga', 'opus', 'wav', 'aac', 'flac',
        // documents
        'pdf', 'txt', 'rtf', 'csv', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'zip',
    ];

    /** Extensions that are rendered inline in the conversation rather than as a file. */
    const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];
    const VIDEO_EXTENSIONS = ['mp4', 'm4v', 'webm', 'mov'];
    const AUDIO_EXTENSIONS = ['mp3', 'm4a', 'ogg', 'oga', 'opus', 'wav', 'aac', 'flac'];

    /** @var string */
    private $uploadRoot;

    /** @var Settings */
    private $settings;

    public function __construct(string $absolutePath, Settings $settings)
    {
        $this->uploadRoot = rtrim(str_replace('\\', '/', $absolutePath), '/').'/uploads';
        $this->settings = $settings;
    }

    public function maxBytes(): int
    {
        return $this->settings->getInt('attachmentMaxSizeMB', 1, 512) * 1024 * 1024;
    }

    /**
     * Store an uploaded file.
     *
     * @param  array $file      an entry from $_FILES
     * @param  string $uploadedBy
     * @return array{filePath: string, fileName: string, fileMimeType: string, fileSize: int, width: int|null, height: int|null, thumbnailPath: string|null}
     */
    public function store(array $file, string $uploadedBy): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException($this->uploadErrorMessage((int) ($file['error'] ?? 4)));
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0) {
            throw new \RuntimeException('The file was empty.');
        }
        if ($size > $this->maxBytes()) {
            throw new \RuntimeException(sprintf(
                'That file is %s MB. The limit for a chat attachment is %d MB.',
                round($size / 1048576, 1),
                $this->settings->getInt('attachmentMaxSizeMB', 1, 512)
            ));
        }

        $originalName = (string) ($file['name'] ?? 'attachment');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if ($extension === '' || !in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Files of that type cannot be sent in a chat.');
        }
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw new \RuntimeException('Files of that type cannot be sent in a chat.');
        }

        // Check what the bytes actually are, not what the sender claimed. An
        // image extension on a script would otherwise pass the allowlist.
        $mime = $this->detectMime((string) $file['tmp_name'], $extension);
        if ($mime === null) {
            throw new \RuntimeException('That file could not be identified.');
        }
        if (in_array($extension, self::IMAGE_EXTENSIONS, true) && !str_starts_with($mime, 'image/')) {
            throw new \RuntimeException('That file is not an image.');
        }

        $relative = $this->targetPath($extension, $uploadedBy);
        $absolute = $this->uploadRoot.'/'.$relative;

        $directory = dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The upload folder could not be created.');
        }

        $moved = is_uploaded_file((string) $file['tmp_name'])
            ? @move_uploaded_file((string) $file['tmp_name'], $absolute)
            : @rename((string) $file['tmp_name'], $absolute);
        if (!$moved) {
            throw new \RuntimeException('The file could not be saved.');
        }
        @chmod($absolute, 0644);

        $dimensions = $this->imageDimensions($absolute, $extension);

        return [
            'filePath' => 'uploads/'.$relative,
            'fileName' => mb_substr($originalName, 0, 255),
            'fileMimeType' => $mime,
            'fileSize' => $size,
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'thumbnailPath' => $this->makeThumbnail($absolute, $relative, $extension, $dimensions),
        ];
    }

    /**
     * Store bytes that never passed through an upload form, which is how a
     * recorded voice note arrives: the browser encodes it and posts the blob as
     * the request body.
     */
    public function storeBlob(string $contents, string $extension, string $originalName, string $uploadedBy): array
    {
        $size = strlen($contents);
        if ($size <= 0) {
            throw new \RuntimeException('The recording was empty.');
        }
        if ($size > $this->maxBytes()) {
            throw new \RuntimeException(sprintf(
                'That recording is %s MB. The limit is %d MB.',
                round($size / 1048576, 1),
                $this->settings->getInt('attachmentMaxSizeMB', 1, 512)
            ));
        }
        if (!in_array(strtolower($extension), self::ALLOWED_EXTENSIONS, true)) {
            throw new \RuntimeException('Files of that type cannot be sent in a chat.');
        }

        $relative = $this->targetPath(strtolower($extension), $uploadedBy);
        $absolute = $this->uploadRoot.'/'.$relative;

        $directory = dirname($absolute);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new \RuntimeException('The upload folder could not be created.');
        }
        if (@file_put_contents($absolute, $contents) === false) {
            throw new \RuntimeException('The recording could not be saved.');
        }
        @chmod($absolute, 0644);

        $mime = $this->detectMime($absolute, strtolower($extension));

        return [
            'filePath' => 'uploads/'.$relative,
            'fileName' => mb_substr($originalName, 0, 255),
            'fileMimeType' => $mime ?? 'application/octet-stream',
            'fileSize' => $size,
            'width' => null,
            'height' => null,
            'thumbnailPath' => null,
        ];
    }

    /**
     * Resolve a stored path for reading, refusing anything outside the uploads
     * folder.
     *
     * This is the check that keeps a crafted filePath in the database from
     * serving tawasul.php or /etc/passwd.
     */
    public function resolveForRead(string $storedPath): ?string
    {
        $storedPath = str_replace('\\', '/', trim($storedPath));
        if ($storedPath === '') {
            return null;
        }

        $relative = str_starts_with($storedPath, 'uploads/') ? substr($storedPath, 8) : $storedPath;
        if ($relative === '' || str_contains($relative, '..')) {
            return null;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            return null;
        }

        $absolute = realpath($this->uploadRoot.'/'.$relative);
        $root = realpath($this->uploadRoot);
        if ($absolute === false || $root === false) {
            return null;
        }
        if (!str_starts_with($absolute, $root.'/') || !is_file($absolute)) {
            return null;
        }

        return $absolute;
    }

    public function remove(?string $storedPath): void
    {
        if ($storedPath === null) {
            return;
        }
        $absolute = $this->resolveForRead($storedPath);
        if ($absolute !== null) {
            @unlink($absolute);
        }
    }

    /** Which of the three inline renderers a stored extension belongs to. */
    public static function kindOf(string $extension): string
    {
        $extension = strtolower($extension);

        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return 'image';
        }
        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }
        if (in_array($extension, self::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }

        return 'file';
    }

    /**
     * uploads/YYYY/MM/chat_<person>_<random>.<ext>
     *
     * Dated by month so a busy chat cannot put a million files in one directory,
     * which is the point at which ext4 and most backup tools start to suffer.
     */
    private function targetPath(string $extension, string $uploadedBy): string
    {
        $person = preg_replace('/\D/', '', ChatGateway::pad($uploadedBy));
        $random = bin2hex(random_bytes(8));

        return date('Y/m').'/chat_'.($person !== '' ? $person : '0').'_'.$random.'.'.$extension;
    }

    /** @return string|null the detected MIME type, or null if it cannot be trusted */
    private function detectMime(string $absolutePath, string $extension): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        // finfo is authoritative about the bytes. It is absent on some minimal
        // builds, in which case fall back to a map of the extensions chat accepts.
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $absolutePath);
            finfo_close($finfo);
            if (is_string($mime) && $mime !== '' && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }

        $map = [
            'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
            'webp' => 'image/webp', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
            'mp4' => 'video/mp4', 'm4v' => 'video/x-m4v', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
            'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg',
            'opus' => 'audio/opus', 'wav' => 'audio/wav', 'aac' => 'audio/aac', 'flac' => 'audio/flac',
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv', 'rtf' => 'application/rtf',
            'zip' => 'application/zip',
        ];

        return $map[$extension] ?? null;
    }

    /** @return array{width: int|null, height: int|null} */
    private function imageDimensions(string $absolutePath, string $extension): array
    {
        if (!function_exists('getimagesize') || !in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return ['width' => null, 'height' => null];
        }

        $size = @getimagesize($absolutePath);

        return [
            'width' => $size ? (int) $size[0] : null,
            'height' => $size ? (int) $size[1] : null,
        ];
    }

    /**
     * A downscaled copy for the conversation strip.
     *
     * Best effort: a missing GD extension or an image it cannot read leaves the
     * original to be served instead, which is worse looking but not broken.
     */
    private function makeThumbnail(string $absolutePath, string $relative, string $extension, array $dimensions): ?string
    {
        if (!in_array($extension, self::IMAGE_EXTENSIONS, true)
            || !function_exists('imagecreatetruecolor')
            || !function_exists('imagecopyresampled')
            || $dimensions['width'] === null || $dimensions['height'] === null) {
            return null;
        }

        // Already small enough that a copy would not save anything.
        if ($dimensions['width'] <= 320 && $dimensions['height'] <= 320) {
            return null;
        }

        $source = match ($extension) {
            'jpg', 'jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($absolutePath) : null,
            'png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($absolutePath) : null,
            'gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($absolutePath) : null,
            'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($absolutePath) : null,
            default => null,
        };
        if ($source === false || $source === null) {
            return null;
        }

        $scale = min(320 / $dimensions['width'], 320 / $dimensions['height'], 1);
        $width = max(1, (int) round($dimensions['width'] * $scale));
        $height = max(1, (int) round($dimensions['height'] * $scale));

        $thumb = imagecreatetruecolor($width, $height);
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $width, $height, $dimensions['width'], $dimensions['height']);

        $thumbRelative = preg_replace('/\.([^.]+)$/', '_thumb.$1', $relative);
        $thumbAbsolute = $this->uploadRoot.'/'.$thumbRelative;

        $written = match ($extension) {
            'jpg', 'jpeg' => @imagejpeg($thumb, $thumbAbsolute, 80),
            'png' => @imagepng($thumb, $thumbAbsolute, 6),
            'gif' => @imagegif($thumb, $thumbAbsolute),
            'webp' => function_exists('imagewebp') ? @imagewebp($thumb, $thumbAbsolute, 80) : false,
            default => false,
        };

        imagedestroy($source);
        imagedestroy($thumb);

        return $written ? 'uploads/'.$thumbRelative : null;
    }

    private function uploadErrorMessage(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => sprintf(
                'That file is larger than the server accepts. The chat limit is %d MB.',
                $this->settings->getInt('attachmentMaxSizeMB', 1, 512)
            ),
            UPLOAD_ERR_PARTIAL => 'The upload was interrupted.',
            UPLOAD_ERR_NO_FILE => 'No file was received.',
            default => 'The file could not be uploaded.',
        };
    }
}
