<?php
namespace Tos\Module\TawasulCore\Http;

use Tos\Module\TawasulCore\Controller\AccountingController;
use Tos\Module\TawasulCore\Resource\Registry;

/**
 * Turns a request path into a route descriptor.
 *
 * Static routes are matched before the generic resource routes, so a resource
 * could never be named "auth" and shadow the login endpoint. Everything is
 * resolved here and handled in the Kernel, which keeps dispatching in one
 * readable place instead of spread across controller constructors.
 */
class Router
{
    /** Routes that may be called without a credential. */
    protected const PUBLIC_ROUTES = ['meta.index', 'meta.health', 'auth.login', 'auth.refresh'];

    /**
     * @return array{name: string, params: array, public: bool}
     */
    public function match(string $path, string $method): array
    {
        $segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        // The version prefix is optional so /health and /v2/health both work.
        if (!empty($segments) && $segments[0] === 'v2') {
            array_shift($segments);
        }

        $count = count($segments);

        if ($count === 0) {
            return $this->route('meta.index');
        }

        if ($count === 1) {
            switch ($segments[0]) {
                case 'health':
                    return $this->route('meta.health');
                case 'scopes':
                    return $this->route('meta.scopes');
                case 'resources':
                    return $this->route('meta.resources');
                case 'openapi':
                case 'openapi.json':
                    return $this->route('meta.openapi');
                case 'events':
                    return $this->route('meta.events', [], 'GET', $method);
                case 'dashboard':
                    return $this->route('meta.dashboard', [], 'GET', $method);

                case 'permissions':
                    return $this->route('meta.permissions', [], 'GET', $method);

                case 'stats':
                    return $this->route('meta.stats', [], 'GET', $method);

                case 'analytics':
                    return $this->route('meta.analytics', [], 'GET', $method);


            }
        }

        if ($count === 2 && $segments[0] === 'auth') {
            switch ($segments[1]) {
                case 'login':
                    return $this->route('auth.login', [], 'POST', $method);
                case 'refresh':
                    return $this->route('auth.refresh', [], 'POST', $method);
                case 'logout':
                    return $this->route('auth.logout', [], 'POST', $method);
                case 'me':
                    return $this->route('auth.me', [], 'GET', $method);
            }
        }

        // File transfer: /files/{resource}/{id} and /files/{resource}/{id}/{field}.
        // Matched before anything else so a resource can never be named "files".
        if ($segments[0] === 'files' && ($count === 3 || $count === 4)) {
            $params = ['resource' => $segments[1], 'id' => $segments[2], 'field' => $segments[3] ?? null];

            if (!Registry::has($params['resource'])) {
                throw ApiException::notFound('Unknown resource "'.$params['resource'].'". Call /v2/resources for the full list.');
            }

            if ($count === 3) {
                return $this->route('file.index', $params, 'GET', $method);
            }

            switch ($method) {
                case 'GET':
                    return $this->route('file.download', $params);
                case 'POST':
                case 'PUT':
                    return $this->route('file.upload', $params);
                case 'DELETE':
                    return $this->route('file.detach', $params);
            }

            throw new ApiException(405, 'method_not_allowed',
                $method.' is not supported on a file field.',
                ['allowed' => ['GET', 'POST', 'PUT', 'DELETE']]
            );
        }

        // Ledger writes and reports. Matched before the generic resource routes
        // so that "accounting" can never be shadowed by a resource of that name,
        // and so /accounting/journal/validate is read as an action rather than
        // as an entry whose document number is "validate".
        if ($segments[0] === 'accounting' && $count >= 2) {
            switch ($segments[1]) {
                case 'journal':
                    if ($count === 2) {
                        return $this->route('accounting.postJournal', [], 'POST', $method);
                    }
                    if ($count === 3 && $segments[2] === 'validate') {
                        return $this->route('accounting.validateJournal', [], 'POST', $method);
                    }
                    if ($count === 4 && $segments[3] === 'reverse') {
                        return $this->route('accounting.reverseJournal', ['id' => $segments[2]], 'POST', $method);
                    }
                    break;

                case 'reports':
                    if ($count === 3) {
                        // Refused here rather than in the controller: an unknown
                        // report should not cost a database round trip, and the
                        // 404 can then list the reports that do exist.
                        if (!in_array($segments[2], AccountingController::REPORTS, true)) {
                            throw ApiException::notFound(
                                'There is no "' . $segments[2] . '" accounting report. Use '
                                . implode(', ', AccountingController::REPORTS) . '.'
                            );
                        }

                        return $this->route('accounting.report', ['report' => $segments[2]], 'GET', $method);
                    }
                    break;
            }

            throw ApiException::notFound('There is no ' . implode('/', array_slice($segments, 1)) . ' accounting endpoint.');
        }

        // Composite convenience endpoints and relation traversal:
        // /{resource}/{id}/{relation}
        if ($count === 3) {
            [$resource, $id, $relation] = $segments;

            if (in_array($resource, ['students', 'users', 'people'], true) && $relation === 'profile') {
                return $this->route('composite.studentProfile', ['id' => $id], 'GET', $method);
            }
            if ($resource === 'staff' && $relation === 'profile') {
                return $this->route('composite.staffProfile', ['id' => $id], 'GET', $method);
            }
            if ($resource === 'families' && $relation === 'profile') {
                return $this->route('composite.familyProfile', ['id' => $id], 'GET', $method);
            }
            if ($resource === 'courses' && $relation === 'overview') {
                return $this->route('composite.courseOverview', ['id' => $id], 'GET', $method);
            }
            if (in_array($resource, ['students', 'users', 'people', 'families'], true) && $relation === 'finance') {
                return $this->route('composite.financeSummary', ['id' => $id], 'GET', $method);
            }
            if (in_array($resource, ['users', 'people', 'staff', 'students'], true) && $relation === 'timetable') {
                return $this->route('composite.personTimetable', ['id' => $id], 'GET', $method);
            }
            if ($resource === 'classes' && $relation === 'roster') {
                return $this->route('composite.classRoster', ['id' => $id], 'GET', $method);
            }

            if (Registry::has($resource)) {
                return $this->route(
                    'resource.relation',
                    ['resource' => $resource, 'id' => $id, 'relation' => $relation],
                    'GET',
                    $method
                );
            }

            throw ApiException::notFound('There is no '.$relation.' endpoint under '.$resource.'.');
        }

        // POST /v2/batch — multiple API calls in a single request.
        if ($count === 1 && $segments[0] === 'batch') {
            return $this->route('batch.handle', [], 'POST', $method);
        }

        if ($count <= 2) {
            $name = $segments[0];

            if (!Registry::has($name)) {
                throw ApiException::notFound(
                    'Unknown resource "'.$name.'". Call /v2/resources for the full list.'
                );
            }

            // /{resource}/bulk is a batch write, never a record with id "bulk".
            if (($segments[1] ?? null) === 'bulk') {
                return $this->route('resource.bulk', ['resource' => $name]);
            }

            // /{resource}/export — unpaginated CSV/JSON download.
            if (($segments[1] ?? null) === 'export') {
                return $this->route('resource.export', ['resource' => $name]);
            }

            // /{resource}/aggregate — summary statistics.
            if (($segments[1] ?? null) === 'aggregate') {
                return $this->route('resource.aggregate', ['resource' => $name]);
            }

            // /{resource}/distinct — unique values for a field.
            if (($segments[1] ?? null) === 'distinct') {
                return $this->route('resource.distinct', ['resource' => $name], 'GET', $method);
            }

            return $this->route('resource', ['resource' => $name, 'id' => $segments[1] ?? null]);
        }

        throw ApiException::notFound('That endpoint does not exist.');
    }

    protected function route(string $name, array $params = [], ?string $requiredMethod = null, ?string $actualMethod = null): array
    {
        if ($requiredMethod !== null && $actualMethod !== null && $requiredMethod !== $actualMethod) {
            throw new ApiException(405, 'method_not_allowed',
                $actualMethod.' is not supported here.',
                ['allowed' => [$requiredMethod]]
            );
        }

        return [
            'name' => $name,
            'params' => $params,
            'public' => in_array($name, self::PUBLIC_ROUTES, true),
        ];
    }
}
