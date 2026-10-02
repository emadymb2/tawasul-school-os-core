<?php
namespace Gibbon\Module\RestAPI\Resource;

use Gibbon\Module\RestAPI\Http\ApiException;

/**
 * The single source of truth for the API surface.
 *
 * Every REST resource is described declaratively here: the SQL that reads it,
 * the filters, search and sort columns it accepts, the columns that may be
 * written, the OAuth-style scope it sits behind and the Gibbon module/action
 * used for role-permission enforcement. CrudController serves all of them,
 * the docs screen renders them and the OpenAPI generator describes them, so
 * documentation can never drift from behaviour.
 *
 * Definition keys
 *   title       Human readable name.
 *   group       Docs grouping.
 *   scope       Base scope; ".read" and ".write" are appended per method.
 *   module      Gibbon module that must be installed and active (null = core).
 *   action      Gibbon action name used when role permissions are enforced.
 *   table       Table used for writes.
 *   primaryKey  Primary key column of that table.
 *   select      Full SELECT ... FROM ... JOIN ... (no WHERE / ORDER / LIMIT).
 *   idColumn    Qualified column matched by /{id} (defaults to table.primaryKey).
 *   where       Always applied conditions.
 *   filters     queryParam => qualified column.
 *   yearFilter  Qualified school-year column; filled from the request year.
 *   search      Qualified columns searched by ?search=.
 *   sort        Allowed ?sort= columns (maps alias => qualified column).
 *   defaultSort Default ORDER BY clause.
 *   writable    Columns accepted in POST/PATCH bodies.
 *   required    Columns required on POST.
 *   methods     Allowed HTTP methods.
 *   sensitive   Columns always stripped from output.
 */
class Registry
{
    protected static $resources = null;
    protected static $actionMap = null;

    public static function all(): array
    {
        if (self::$resources === null) {
            // Generated definitions cover the whole schema; the hand-written
            // ones are loaded last and always win, so a curated endpoint keeps
            // its joins, friendly filters and safety rules even when a table
            // of the same name is auto-described.
             self::$resources = array_merge(
                self::load(__DIR__.'/definitions/generated/core.php'),
                self::load(__DIR__.'/definitions/generated/modules.php'),
                require __DIR__.'/definitions/people.php',
                require __DIR__.'/definitions/school.php',
                require __DIR__.'/definitions/academics.php',
                require __DIR__.'/definitions/wellbeing.php',
                require __DIR__.'/definitions/operations.php',
                require __DIR__.'/definitions/system.php',
                require __DIR__.'/definitions/modules.php',
                require __DIR__.'/definitions/messenger.php'
            );

            foreach (self::$resources as $name => &$resource) {
                $resource = self::normalise($name, $resource);
                $resource = self::applyActionMap($resource);
            }
            unset($resource);

            ksort(self::$resources);
        }

        return self::$resources;
    }

    /**
     * Generated resources describe a raw table and carry no Gibbon action, so
     * role enforcement had nothing to check. actionMap.php supplies the module
     * and action that own each table, derived from tawasulAction.URLList and the
     * core module pages (see tools/generate-action-map.mjs). Curated
     * definitions already state their own action and are left untouched.
     */
    protected static function actionMap(): array
    {
        if (self::$actionMap === null) {
            $path = __DIR__.'/actionMap.php';
            $map = is_readable($path) ? require $path : [];
            self::$actionMap = is_array($map) ? $map : [];
        }

        return self::$actionMap;
    }

    protected static function applyActionMap(array $resource): array
    {
        if (!empty($resource['action']) || empty($resource['table'])) {
            return $resource;
        }

        $entry = self::actionMap()[$resource['table']] ?? null;
        if ($entry === null) {
            return $resource;
        }

        $resource['module'] = $resource['module'] ?: $entry['module'];
        $resource['writeModule'] = $resource['writeModule'] ?: ($entry['writeModule'] ?? null);
        $resource['action'] = $entry['action'];
        $resource['writeAction'] = $entry['writeAction'] ?? $entry['action'];
        $resource['actionConfidence'] = $entry['confidence'] ?? 'low';

        return $resource;
    }


    protected static function load(string $path): array
    {
        if (!is_readable($path)) {
            return [];
        }

        $definitions = require $path;

        return is_array($definitions) ? $definitions : [];
    }

    public static function singularise(string $word): string
    {
        if (substr($word, -3) === 'ies') {
            return substr($word, 0, -3).'y';
        }
        if (preg_match('/(ses|xes|zes|ches|shes)$/', $word)) {
            return substr($word, 0, -2);
        }
        if (substr($word, -1) === 's') {
            return substr($word, 0, -1);
        }

        return $word;
    }

    protected static function normalise(string $name, array $resource): array
    {
        return array_merge([
            'name' => $name,
            'title' => ucfirst(str_replace('-', ' ', $name)),
            'singular' => self::singularise(str_replace('-', ' ', $name)),

            'group' => 'Other',
            'scope' => $name,
            'module' => null,
            'writeModule' => null,
            'action' => null,
            'writeAction' => null,
            'actionConfidence' => null,
            'table' => null,
            'primaryKey' => null,
            'select' => null,
            'idColumn' => null,
            'where' => [],
            'filters' => [],
            'yearFilter' => null,
            'search' => [],
            'sort' => [],
            'defaultSort' => null,
            'writable' => [],
            'required' => [],
            'methods' => ['GET', 'POST', 'PATCH', 'PUT', 'DELETE'],
            'sensitive' => [],
            'description' => '',

            // Extended metadata used by validation, relation expansion and
            // the OpenAPI generator. Curated definitions may set these too.
            'types' => [],
            'enums' => [],
            'maxLength' => [],
            'relations' => [],
            'readOnlyReason' => '',
            'generated' => false,
        ], $resource);
    }

    /**
     * Resources whose definition was produced by tools/generate-definitions.mjs.
     */
    public static function generated(): array
    {
        return array_filter(self::all(), function ($resource) {
            return !empty($resource['generated']);
        });
    }

    public static function curated(): array
    {
        return array_filter(self::all(), function ($resource) {
            return empty($resource['generated']);
        });
    }

    /**
     * Every change event a webhook can subscribe to.
     */
    public static function events(): array
    {
        $events = ['*' => 'Every change event'];

        foreach (self::all() as $name => $resource) {
            if (!array_intersect($resource['methods'], ['POST', 'PATCH', 'PUT', 'DELETE'])) {
                continue;
            }
            $events[$name.'.*'] = 'Any change to '.$resource['title'];
            $events[$name.'.created'] = 'A '.$resource['singular'].' was created';
            $events[$name.'.updated'] = 'A '.$resource['singular'].' was updated';
            $events[$name.'.replaced'] = 'A '.$resource['singular'].' was replaced';
            $events[$name.'.deleted'] = 'A '.$resource['singular'].' was deleted';
        }

        return $events;
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    public static function get(string $name): array
    {
        $all = self::all();
        if (!isset($all[$name])) {
            throw ApiException::notFound('Unknown resource: '.$name);
        }

        return $all[$name];
    }

    /**
     * Every scope the API understands, derived from the registry itself.
     */
    public static function scopes(): array
    {
        $scopes = ['*' => 'Full access to every endpoint'];

        foreach (self::all() as $resource) {
            $scopes[$resource['scope'].'.read'] = 'Read '.$resource['title'];
            if (array_intersect($resource['methods'], ['POST', 'PATCH', 'PUT', 'DELETE'])) {
                $scopes[$resource['scope'].'.write'] = 'Create, update and delete '.$resource['title'];
            }
        }

        ksort($scopes);

        return $scopes;
    }

    public static function groups(): array
    {
        $groups = [];
        foreach (self::all() as $name => $resource) {
            $groups[$resource['group']][$name] = $resource;
        }
        ksort($groups);

        return $groups;
    }
}
