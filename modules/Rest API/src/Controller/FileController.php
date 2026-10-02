<?php
namespace Gibbon\Module\RestAPI\Controller;

use Gibbon\Module\RestAPI\Http\ApiException;
use Gibbon\Module\RestAPI\Http\Request;
use Gibbon\Module\RestAPI\Support\FileStore;

/**
 * The bytes behind a record: download, upload, replace, detach.
 *
 * Gibbon stores a relative path in the record and the file itself on disk, so
 * a JSON-only API can describe a student photo or a personal document but never
 * hand it over. These endpoints close that gap for every resource in the
 * registry at once — any column that holds a path is reachable as
 * /v2/files/{resource}/{id}/{field} — instead of one bespoke endpoint per
 * feature.
 *
 * Permissions are not re-invented here. Reading a file requires the same GET
 * rights as reading the record, and changing one requires the same PATCH rights
 * as writing the field, both checked by CrudController.
 */
class FileController
{
    protected $crud;
    protected $store;

    public function __construct(CrudController $crud, FileStore $store)
    {
        $this->crud = $crud;
        $this->store = $store;
    }

    /**
     * GET /files/{resource}/{id} — which fields hold files, and which of those
     * actually have one for this record.
     */
    public function index(string $name, string $id): array
    {
        $this->store->assertEnabled();

        ['resource' => $resource, 'row' => $row] = $this->crud->readRecord($name, $id);
        $fields = FileStore::fileFieldsOf($resource);

        if (empty($fields)) {
            throw ApiException::notFound($resource['title'].' records do not hold any files.');
        }

        $files = [];
        foreach ($fields as $field) {
            $stored = (string) ($row[$field] ?? '');
            $entry = [
                'field' => $field,
                'stored' => $stored !== '' ? $stored : null,
                'present' => false,
                'writable' => in_array($field, $resource['writable'] ?? [], true),
                'download' => null,
            ];

            if ($stored !== '') {
                try {
                    $resolved = $this->store->resolveForRead($stored);
                    $entry['present'] = true;
                    $entry['size'] = $resolved['size'];
                    $entry['mime'] = $resolved['mime'];
                    $entry['filename'] = $resolved['name'];
                    $entry['download'] = 'files/'.$resource['name'].'/'.$id.'/'.$field;
                } catch (ApiException $e) {
                    // A path that no longer resolves is reported, not fatal:
                    // knowing the record points at a missing file is the useful
                    // answer here.
                    $entry['problem'] = $e->getMessage();
                }
            }

            $files[] = $entry;
        }

        return ['data' => $files, 'meta' => ['resource' => $resource['name'], 'id' => $id]];
    }

    /**
     * GET /files/{resource}/{id}/{field} — streams the file itself.
     *
     * Returns a marker the Kernel recognises rather than writing output here,
     * so the request is still logged and rate-limit headers are still sent.
     */
    public function download(string $name, string $id, string $field, Request $request): array
    {
        $this->store->assertEnabled();

        ['resource' => $resource, 'row' => $row] = $this->crud->readRecord($name, $id);
        $this->assertFileField($resource, $field);

        if (!array_key_exists($field, $row)) {
            throw ApiException::forbidden($field.' is not readable on '.$resource['name'].'.');
        }

        $file = $this->store->resolveForRead((string) $row[$field]);

        return ['__file' => $file, 'inline' => $request->query('disposition') === 'inline'];
    }

    /**
     * POST or PUT /files/{resource}/{id}/{field} — stores an upload and points
     * the record at it.
     */
    public function upload(string $name, string $id, string $field, Request $request): array
    {
        $this->store->assertEnabled();

        ['resource' => $resource, 'row' => $row] = $this->crud->readRecord($name, $id);
        $this->assertFileField($resource, $field);
        $this->assertWritable($resource, $field);

        $upload = $request->getUploadedFile();
        if ($upload === null) {
            throw ApiException::unprocessable(
                'Send the file as multipart form-data in a "file" field, or as JSON with "filename" and "contentBase64".'
            );
        }

        $previous = (string) ($row[$field] ?? '');
        $stored = $this->store->store($upload, $resource['name']);

        try {
            $record = $this->crud->updateRecord($resource, $id, [$field => $stored['path']]);
        } catch (\Throwable $e) {
            // The record still points at the old file, so the new one is
            // orphaned rubbish — remove it rather than leave it on disk.
            $this->store->remove($stored['path']);
            throw $e;
        }

        // Only once the record is safely pointing at the new file is the old
        // one removed.
        if ($previous !== '' && $previous !== $stored['path']) {
            $stored['replaced'] = $this->store->remove($previous) ? $previous : null;
        }

        return [
            'data' => $record,
            'meta' => ['file' => $stored, 'field' => $field],
            'created' => true,
        ];
    }

    /**
     * DELETE /files/{resource}/{id}/{field} — clears the field and deletes the
     * file behind it.
     */
    public function detach(string $name, string $id, string $field): array
    {
        $this->store->assertEnabled();

        ['resource' => $resource, 'row' => $row] = $this->crud->readRecord($name, $id);
        $this->assertFileField($resource, $field);
        $this->assertWritable($resource, $field);

        $stored = (string) ($row[$field] ?? '');
        if ($stored === '') {
            throw ApiException::notFound('There is no file attached to '.$field.' on that record.');
        }

        $record = $this->crud->updateRecord($resource, $id, [$field => null]);
        $removed = $this->store->remove($stored);

        return [
            'data' => $record,
            'meta' => ['field' => $field, 'detached' => $stored, 'fileDeleted' => $removed],
        ];
    }

    protected function assertFileField(array $resource, string $field): void
    {
        if (!FileStore::isFileField($resource, $field)) {
            throw ApiException::notFound(
                $field.' does not hold a file on '.$resource['name'].'.',
                ['fileFields' => FileStore::fileFieldsOf($resource)]
            );
        }
    }

    protected function assertWritable(array $resource, string $field): void
    {
        $this->crud->guard($resource, 'PATCH');

        if (!in_array($field, $resource['writable'] ?? [], true)) {
            throw ApiException::forbidden(
                $field.' cannot be changed through the API on '.$resource['name'].'.',
                ['writableFields' => $resource['writable'] ?? []]
            );
        }
    }
}
