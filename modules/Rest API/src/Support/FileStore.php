<?php
namespace Gibbon\Module\RestAPI\Support;

use Gibbon\Module\RestAPI\Http\ApiException;

/**
 * The file half of the API.
 *
 * Gibbon keeps documents, photos, logos and report exports on disk and stores
 * only the relative path in the database. Until now the API could read and
 * write that path but never the bytes behind it, so no integration could sync
 * a student photo or fetch a personal document. This class owns every disk
 * operation: which columns hold files, where new uploads land, and — most
 * importantly — the guarantee that a stored path can never escape the Gibbon
 * installation folder.
 */
class FileStore
{
    /**
     * Columns that hold a path to a file rather than ordinary data. Gibbon is
     * consistent enough about naming that this covers person photos
     * (image_240), personal documents (path), library covers, form uploads,
     * report archives and module attachments.
     */
    const FILE_COLUMN = '/(^image_?\d*$|^logo$|^signature$|path|file|attachment|photo|cover|thumbnail)/i';

    /** Never served or accepted, whatever the settings say. */
    const FORBIDDEN_EXTENSIONS = ['php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'phps', 'phar', 'htaccess', 'htpasswd', 'cgi', 'pl', 'py', 'sh', 'exe', 'js', 'html', 'htm', 'svg'];

    const MIME_TYPES = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif',
        'webp' => 'image/webp', 'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
        'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'ppt' => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        'zip' => 'application/zip', 'rtf' => 'application/rtf',
        'mp3' => 'audio/mpeg', 'mp4' => 'video/mp4',
    ];

    protected $settings;
    protected $absolutePath;

    public function __construct(Settings $settings, string $absolutePath)
    {
        $this->settings = $settings;
        $this->absolutePath = rtrim(str_replace('\\', '/', $absolutePath), '/');
    }

    public function isEnabled(): bool
    {
        return $this->settings->isOn('filesEnabled', true);
    }

    public function assertEnabled(): void
    {
        if (!$this->isEnabled()) {
            throw ApiException::forbidden('File transfer through the API is switched off for this Gibbon.');
        }
    }

    /**
     * Columns of a resource that hold a file path, so /v2/files/... can tell a
     * caller what is available instead of making them guess.
     */
    public static function fileFieldsOf(array $resource): array
    {
        $candidates = array_unique(array_merge(
            array_keys($resource['types'] ?? []),
            $resource['writable'] ?? []
        ));

        $fields = [];
        foreach ($candidates as $column) {
            if (in_array($column, $resource['sensitive'] ?? [], true)) {
                continue;
            }
            $type = ($resource['types'] ?? [])[$column] ?? 'string';
            if ($type === 'string' && preg_match(self::FILE_COLUMN, $column)) {
                $fields[] = $column;
            }
        }

        sort($fields);

        return $fields;
    }

    public static function isFileField(array $resource, string $field): bool
    {
        return in_array($field, self::fileFieldsOf($resource), true);
    }

    /**
     * Turns a stored path into an absolute one, refusing anything that points
     * outside the Gibbon folder. This is the whole security model of the
     * download endpoint: the database is treated as untrusted input, because a
     * path column can be written by any Gibbon screen or by this API.
     */
    public function resolveForRead(string $storedPath): array
    {
        $storedPath = trim(str_replace('\\', '/', $storedPath));

        if ($storedPath === '') {
            throw ApiException::notFound('That record has no file attached.');
        }
        if (strpos($storedPath, '..') !== false || strpos($storedPath, "\0") !== false) {
            throw ApiException::badRequest('That stored file path is not usable.');
        }
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $storedPath)) {
            throw ApiException::badRequest('That record points at a remote URL rather than a stored file.');
        }

        $absolute = $storedPath;
        if (strpos($storedPath, $this->absolutePath) !== 0) {
            $absolute = $this->absolutePath.'/'.ltrim($storedPath, '/');
        }

        $real = realpath($absolute);
        if ($real === false) {
            throw ApiException::notFound('The stored file is missing from the server.');
        }

        $real = str_replace('\\', '/', $real);
        if (strpos($real, $this->absolutePath.'/') !== 0) {
            throw ApiException::forbidden('That file is outside the Gibbon installation and will not be served.');
        }

        $extension = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw ApiException::forbidden('Files of type .'.$extension.' are never served by the API.');
        }

        return [
            'path' => $real,
            'name' => basename($real),
            'size' => (int) filesize($real),
            'mime' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
            'relative' => ltrim(substr($real, strlen($this->absolutePath)), '/'),
        ];
    }

    /**
     * Writes an uploaded file into Gibbon's uploads folder using the same
     * year/month layout core uses, and returns the relative path to store in
     * the record. Nothing is overwritten: every upload gets its own name.
     *
     * @param array $file ['name' => original, 'bytes' => contents]
     */
    public function store(array $file, string $prefix = 'api'): array
    {
        $this->assertEnabled();

        $originalName = (string) ($file['name'] ?? '');
        $bytes = (string) ($file['bytes'] ?? '');

        if ($bytes === '') {
            throw ApiException::unprocessable('The uploaded file is empty.');
        }

        $maxBytes = $this->settings->getInt('fileMaxSizeMB', 20) * 1024 * 1024;
        if ($maxBytes > 0 && strlen($bytes) > $maxBytes) {
            throw ApiException::unprocessable(
                'That file is larger than the '.$this->settings->getInt('fileMaxSizeMB', 20).' MB limit.',
                ['bytes' => strlen($bytes), 'maxBytes' => $maxBytes]
            );
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension === '') {
            throw ApiException::unprocessable('The file name must include an extension, for example photo.jpg.');
        }
        if (in_array($extension, self::FORBIDDEN_EXTENSIONS, true)) {
            throw ApiException::unprocessable('Files of type .'.$extension.' cannot be uploaded.');
        }

        $allowed = array_filter(array_map('trim', explode(',', strtolower(
            (string) $this->settings->get('fileExtensions', 'jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,ppt,pptx,csv,txt,odt,ods,zip')
        ))), 'strlen');

        if (!empty($allowed) && !in_array($extension, $allowed, true)) {
            throw ApiException::unprocessable(
                '.'.$extension.' files are not accepted.',
                ['allowedExtensions' => $allowed]
            );
        }

        $directory = 'uploads/'.date('Y').'/'.date('m');
        $absoluteDirectory = $this->absolutePath.'/'.$directory;

        if (!is_dir($absoluteDirectory) && !@mkdir($absoluteDirectory, 0755, true)) {
            throw new ApiException(500, 'storage_error', 'The uploads folder could not be created.');
        }
        if (!is_writable($absoluteDirectory)) {
            throw new ApiException(500, 'storage_error', 'The uploads folder is not writable by the web server.');
        }

        $stem = preg_replace('/[^A-Za-z0-9_-]/', '-', pathinfo($originalName, PATHINFO_FILENAME));
        $stem = trim(mb_substr($stem, 0, 40), '-');
        $name = $prefix.'_'.($stem !== '' ? $stem.'_' : '').bin2hex(random_bytes(6)).'.'.$extension;

        $target = $absoluteDirectory.'/'.$name;
        if (file_put_contents($target, $bytes) === false) {
            throw new ApiException(500, 'storage_error', 'The file could not be written to disk.');
        }
        @chmod($target, 0644);

        return [
            'path' => $directory.'/'.$name,
            'name' => $name,
            'originalName' => $originalName,
            'size' => strlen($bytes),
            'mime' => self::MIME_TYPES[$extension] ?? 'application/octet-stream',
        ];
    }

    /**
     * Removes a stored file. A missing file is not an error: the point is that
     * the record no longer points at anything.
     */
    public function remove(string $storedPath): bool
    {
        try {
            $resolved = $this->resolveForRead($storedPath);
        } catch (ApiException $e) {
            return false;
        }

        return @unlink($resolved['path']);
    }
}
