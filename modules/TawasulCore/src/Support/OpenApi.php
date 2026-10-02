<?php
namespace Tos\Module\TawasulCore\Support;

use Tos\Module\TawasulCore\Kernel;
use Tos\Module\TawasulCore\Resource\Registry;

/**
 * Builds an OpenAPI 3.0 document straight from the resource registry.
 *
 * Because the same registry drives the live endpoints, the specification can
 * never describe a filter, field or method the API does not actually honour.
 */
class OpenApi
{
    public static function build(string $baseUrl, string $moduleVersion = Kernel::VERSION): array
    {
        $document = [
            'openapi' => '3.0.3',
            'info' => [
                'title' => 'TawasulOS REST API',
                'version' => $moduleVersion,
                'description' => self::overview(),
                'license' => ['name' => 'GPL-3.0-or-later'],
            ],
            'servers' => [['url' => $baseUrl]],
            'components' => self::components(),
            'security' => [['bearerAuth' => []]],
            'tags' => [],
            'paths' => self::authPaths() + self::metaPaths() + self::compositePaths()
                + self::accountingPaths() + self::filePaths(),
        ];

        $tags = [];

        foreach (Registry::all() as $name => $resource) {
            $tag = $resource['group'];
            $tags[$tag] = true;

            $collection = [];
            $item = [];

            if (in_array('GET', $resource['methods'], true)) {
                $collection['get'] = [
                    'tags' => [$tag],
                    'summary' => 'List '.$resource['title'],
                    'description' => self::resourceDescription($resource),
                    'operationId' => 'list'.self::operationId($name),
                    'parameters' => self::collectionParameters($resource),
                    'responses' => [
                        '200' => self::collectionResponse($resource),
                        '401' => self::errorRef('Missing or invalid credential'),
                        '403' => self::errorRef('The credential lacks the required scope'),
                        '429' => self::errorRef('Rate limit exceeded; see the X-RateLimit-* headers'),
                    ],
                ];

                $item['get'] = [
                    'tags' => [$tag],
                    'summary' => 'Fetch a single '.$resource['singular'],
                    'operationId' => 'get'.self::operationId($name),
                    'responses' => [
                        '200' => self::itemResponse($resource),
                        '404' => self::errorRef('No such record'),
                    ],
                ];
            }

            if (in_array('POST', $resource['methods'], true)) {
                $collection['post'] = [
                    'tags' => [$tag],
                    'summary' => 'Create a '.$resource['singular'],
                    'operationId' => 'create'.self::operationId($name),
                    'requestBody' => self::requestBody($resource, true),
                    'responses' => [
                        '201' => self::itemResponse($resource),
                        '405' => self::errorRef('This resource is read-only'),
                        '422' => self::errorRef('Validation failed'),
                        '409' => self::errorRef('A matching record already exists'),
                    ],
                ];

                $document['paths']['/'.$name.'/bulk'] = ['post' => [
                    'tags' => [$tag],
                    'summary' => 'Create, update or delete many '.$resource['title'].' in one transaction',
                    'description' => 'Every item succeeds or the whole request is rolled back. '
                        .'The number of items allowed is capped by the Bulk Request Limit setting.',
                    'operationId' => 'bulk'.self::operationId($name),
                    'requestBody' => self::bulkRequestBody($resource),
                    'responses' => [
                        '200' => ['description' => 'A per-item result list.'],
                        '405' => self::errorRef('This resource is read-only'),
                        '422' => self::errorRef('One or more items failed validation; nothing was written'),
                    ],
                ]];
            }

            if (in_array('PATCH', $resource['methods'], true) || in_array('PUT', $resource['methods'], true)) {
                $item['patch'] = [
                    'tags' => [$tag],
                    'summary' => 'Update a '.$resource['singular'].' (partial)',
                    'operationId' => 'patch'.self::operationId($name),
                    'requestBody' => self::requestBody($resource, false),
                    'responses' => [
                        '200' => self::itemResponse($resource),
                        '404' => self::errorRef('No such record'),
                        '422' => self::errorRef('Validation failed'),
                    ],
                ];
                $item['put'] = [
                    'tags' => [$tag],
                    'summary' => 'Replace a '.$resource['singular'].' (full update)',
                    'operationId' => 'replace'.self::operationId($name),
                    'requestBody' => self::requestBody($resource, false),
                    'responses' => [
                        '200' => self::itemResponse($resource),
                        '404' => self::errorRef('No such record'),
                        '422' => self::errorRef('Validation failed, or a required field was omitted'),
                    ],
                ];
            }

            if (in_array('DELETE', $resource['methods'], true)) {
                $item['delete'] = [
                    'tags' => [$tag],
                    'summary' => 'Delete a '.$resource['singular'],
                    'operationId' => 'delete'.self::operationId($name),
                    'responses' => [
                        '200' => ['description' => 'The record was deleted'],
                        '404' => self::errorRef('No such record'),
                    ],
                ];
            }

            if (!empty($collection)) {
                $document['paths']['/'.$name] = $collection;
            }
            if (!empty($item)) {
                $item['parameters'] = [self::idParameter($resource)];
                $document['paths']['/'.$name.'/{id}'] = $item;
            }

            // Relation traversal: /{resource}/{id}/{relation}
            foreach ($resource['relations'] ?? [] as $relation => $definition) {
                $single = ($definition['type'] ?? 'belongsTo') !== 'hasMany';

                $document['paths']['/'.$name.'/{id}/'.$relation] = [
                    'get' => [
                        'tags' => [$tag],
                        'summary' => ($single ? 'Fetch the ' : 'List the ').$relation.' of a '.$resource['singular'],
                        'description' => 'Follows '.$resource['primaryKey'].' → '
                            .$definition['resource'].'.'.($definition['foreignKey'] ?? '')
                            .'. The credential needs the '.$definition['resource'].'.read scope as well.',
                        'operationId' => 'get'.self::operationId($name).self::operationId($relation),
                        'parameters' => $single
                            ? [self::idParameter($resource)]
                            : array_merge([self::idParameter($resource)], [
                                self::parameter('page', 'Page number, starting at 1.', 'integer'),
                                self::parameter('pageSize', 'Records per page.', 'integer'),
                                self::parameter('sort', 'Sort field, prefix with - for descending.'),
                            ]),
                        'responses' => [
                            '200' => ['description' => $single
                                ? 'The related '.$definition['resource'].' record.'
                                : 'A page of related '.$definition['resource'].' records.'],
                            '403' => self::errorRef('The credential lacks the scope for the related resource'),
                            '404' => self::errorRef('No such record, or no related record'),
                        ],
                    ],
                ];
            }
        }

        foreach (array_keys($tags) as $tag) {
            $document['tags'][] = ['name' => $tag];
        }

        return $document;
    }

    protected static function collectionParameters(array $resource): array
    {
        $parameters = [
            self::parameter('page', 'Page number, starting at 1.', 'integer'),
            self::parameter('pageSize', 'Records per page, up to the configured maximum.', 'integer'),
            self::parameter('sort', 'Sort field, prefix with - for descending. Example: -timestamp'),
            self::parameter('search', 'Free-text search across the searchable fields.'),
            self::parameter('fields', 'Comma-separated list of fields to return.'),
        ];

        if (!empty($resource['schoolYearColumn'])) {
            $parameters[] = self::parameter('school_year_id', 'Restrict to one school year. Defaults to the current year.');
        }

        foreach ($resource['filters'] as $filter) {
            $parameters[] = self::parameter($filter, 'Filter by '.$filter.'. Numeric and date fields also accept '.$filter.'From and '.$filter.'To.');
        }

        return $parameters;
    }

    protected static function parameter(string $name, string $description, string $type = 'string'): array
    {
        return [
            'name' => $name,
            'in' => 'query',
            'required' => false,
            'description' => $description,
            'schema' => ['type' => $type],
        ];
    }

    protected static function requestBody(array $resource, bool $create): array
    {
        return [
            'required' => true,
            'content' => ['application/json' => ['schema' => self::bodySchema($resource, $create)]],
        ];
    }

    protected static function bulkRequestBody(array $resource): array
    {
        return [
            'required' => true,
            'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'required' => ['items'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'description' => 'Each item is an object to create, an object carrying the '
                            .'primary key to update, or {"op":"delete","id":"..."} to remove a record.',
                        'items' => self::bodySchema($resource, false),
                    ],
                ],
            ]]],
        ];
    }

    protected static function bodySchema(array $resource, bool $create): array
    {
        $properties = [];
        foreach ($resource['writable'] as $field) {
            $properties[$field] = self::fieldSchema($resource, $field);
        }

        $schema = ['type' => 'object', 'properties' => $properties];
        if ($create && !empty($resource['required'])) {
            $schema['required'] = array_values($resource['required']);
        }

        return $schema;
    }

    /**
     * Field types, allowed values and length limits come from the same
     * definition the validator enforces, so a body the specification accepts
     * cannot be rejected for a rule the specification never mentioned.
     */
    protected static function fieldSchema(array $resource, string $field): array
    {
        $type = $resource['types'][$field] ?? 'string';
        $schema = [];

        switch ($type) {
            case 'integer':
                $schema['type'] = 'integer';
                break;
            case 'number':
            case 'decimal':
                $schema['type'] = 'number';
                break;
            case 'boolean':
                $schema['type'] = 'boolean';
                break;
            case 'date':
                $schema = ['type' => 'string', 'format' => 'date'];
                break;
            case 'datetime':
            case 'timestamp':
                $schema = ['type' => 'string', 'format' => 'date-time'];
                break;
            case 'time':
                $schema = ['type' => 'string', 'example' => '08:30:00'];
                break;
            default:
                $schema['type'] = 'string';
        }

        if (!empty($resource['enums'][$field])) {
            $schema['enum'] = array_values($resource['enums'][$field]);
        }

        if (!empty($resource['maxLength'][$field]) && ($schema['type'] ?? '') === 'string') {
            $schema['maxLength'] = (int) $resource['maxLength'][$field];
        }

        if (in_array($field, $resource['sensitive'] ?? [], true)) {
            $schema['description'] = 'Sensitive field; requires a credential holding the write scope for this resource.';
        }

        return $schema;
    }

    protected static function idParameter(array $resource): array
    {
        return [
            'name' => 'id',
            'in' => 'path',
            'required' => true,
            'description' => 'The '.$resource['primaryKey'].' of the record.',
            'schema' => ['type' => 'string'],
        ];
    }

    protected static function resourceDescription(array $resource): string
    {
        $description = (string) $resource['description'];

        if (!empty($resource['readOnlyReason'])) {
            $description .= "\n\nRead-only: ".$resource['readOnlyReason'];
        } elseif ($resource['methods'] === ['GET']) {
            $description .= "\n\nRead-only: this resource is published for reading only.";
        }

        return $description;
    }

    protected static function itemResponse(array $resource): array
    {
        return [
            'description' => 'A single '.$resource['singular'].'.',
            'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => ['data' => ['type' => 'object']],
            ]]],
        ];
    }

    protected static function collectionResponse(array $resource): array
    {
        return [
            'description' => 'A page of '.$resource['title'].'.',
            'content' => ['application/json' => ['schema' => [
                'type' => 'object',
                'properties' => [
                    'data' => ['type' => 'array', 'items' => ['type' => 'object']],
                    'meta' => ['$ref' => '#/components/schemas/Pagination'],
                ],
            ]]],
        ];
    }

    protected static function errorRef(string $description): array
    {
        return [
            'description' => $description,
            'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]],
        ];
    }

    protected static function components(): array
    {
        return [
            'securitySchemes' => [
                'bearerAuth' => ['type' => 'http', 'scheme' => 'bearer', 'description' => 'An API key (tws_...) or a user token (tok_...).'],
            ],
            'schemas' => [
                'Pagination' => ['type' => 'object', 'properties' => [
                    'resource' => ['type' => 'string'],
                    'page' => ['type' => 'integer'],
                    'pageSize' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                    'totalPages' => ['type' => 'integer'],
                ]],
                'Error' => ['type' => 'object', 'properties' => [
                    'error' => ['type' => 'object', 'properties' => [
                        'code' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                        'details' => ['type' => 'object'],
                    ]],
                ]],
            ],
        ];
    }

    protected static function authPaths(): array
    {
        return [
            '/auth/login' => ['post' => [
                'tags' => ['Authentication'],
                'summary' => 'Exchange credentials for a token',
                'operationId' => 'authLogin',
                'security' => [],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['username', 'password'],
                    'properties' => [
                        'username' => ['type' => 'string', 'description' => 'Username, email, or phone number (phone1–phone4) when loginMethod is phone or all.'],
                        'password' => ['type' => 'string', 'format' => 'password'],
                        'scopes' => ['type' => 'string', 'description' => 'Comma-separated scopes to request. Defaults to *.'],
                    ],
                ]]]],
                'responses' => [
                    '200' => ['description' => 'A token, a refresh token and the signed-in user.'],
                    '401' => self::errorRef('Those credentials were not accepted'),
                    '403' => self::errorRef('Password login is disabled or the account is locked'),
                ],
            ]],
            '/auth/refresh' => ['post' => [
                'tags' => ['Authentication'],
                'summary' => 'Exchange a refresh token for a fresh token pair',
                'operationId' => 'authRefresh',
                'security' => [],
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'required' => ['refreshToken'],
                    'properties' => ['refreshToken' => ['type' => 'string']],
                ]]]],
                'responses' => ['200' => ['description' => 'A new token pair.'], '401' => self::errorRef('Expired or unknown refresh token')],
            ]],
            '/auth/logout' => ['post' => [
                'tags' => ['Authentication'],
                'summary' => 'Revoke the current token',
                'operationId' => 'authLogout',
                'responses' => ['200' => ['description' => 'The token was revoked.']],
            ]],
            '/auth/me' => ['get' => [
                'tags' => ['Authentication'],
                'summary' => 'Describe the current credential',
                'operationId' => 'authMe',
                'responses' => ['200' => ['description' => 'The credential, its scopes and the linked person.']],
            ]],
        ];
    }

    /**
     * The prose an integrator needs before their first call: credentials,
     * limits, transactions, files and change events.
     */
    protected static function overview(): string
    {
        return "A complete REST interface to this TawasulOS installation.\n\n"
            ."## Authentication\n"
            ."Send `Authorization: Bearer tws_...` for an API key, or exchange a TawasulOS username, email, "
            ."or phone number (when loginMethod permits) and password at `POST /v2/auth/login` for a "
            ."`tok_...` bearer token plus a refresh token. `GET /v2/auth/me` describes the current credential and its scopes.\n\n"
            ."## Reading collections\n"
            ."Every collection supports `page`, `pageSize`, `sort` (prefix `-` to reverse), "
            ."`search` and `fields`, plus the filters listed on each operation. Numeric and date "
            ."filters also accept `<filter>From` and `<filter>To`. Records are scoped to the "
            ."current school year unless you pass `school_year_id`.\n\n"
            ."## Relations\n"
            ."`GET /{resource}/{id}/{relation}` follows a link to its parent record or lists the "
            ."children pointing back at it. Each relation is listed as its own operation, and the "
            ."credential needs the read scope of the related resource as well as this one.\n\n"
            ."## Writing\n"
            ."Single writes run in a database transaction, and `POST /{resource}/bulk` puts a whole "
            ."batch in one transaction: every item succeeds or nothing is written. Audit and "
            ."history tables are published read-only and answer 405 to a write, with the reason in "
            ."the error body. String fields carry the column's own length limit, and fields backed "
            ."by a database enum list their allowed values.\n\n"
            ."## Rate limits\n"
            ."Each response carries `X-RateLimit-Limit`, `X-RateLimit-Remaining` and "
            ."`X-RateLimit-Reset`. API keys use their own per-minute limit; tokens issued by "
            ."password share the Token Request Limit setting. Exceeding either returns 429. "
            ."Repeated failed sign-ins from one address are locked out for the configured window.\n\n"
            ."## Files\n"
            ."`GET /v2/files/{resource}/{id}` lists the file fields on a record; the routes below "
            ."it download, replace and detach the file itself. File transfer is off until an "
            ."administrator enables it, and uploads are checked against the allowed extensions and "
            ."the maximum size.\n\n"
            ."## Change events\n"
            ."When webhooks are enabled, every create, update and delete is posted to the "
            ."subscribed URLs as `{resource}.created`, `{resource}.updated` or "
            ."`{resource}.deleted`, signed with `X-TawasulOS-Signature` "
            ."(`sha256=` HMAC of the raw body using the subscription secret). Deliveries and their "
            ."responses are recorded in TawasulOS so a failure can be traced.\n\n"
            ."## Errors\n"
            ."Failures return `{\"error\":{\"code\",\"message\",\"details\"}}` with the matching HTTP "
            ."status, and every response carries `X-Request-Id` for matching against the request log.";
    }

    protected static function compositePaths(): array
    {
        $paths = [];

        $composites = [
            '/students/{id}/profile' => ['Student profile', 'The student, their family, enrolment, timetable, attendance summary and recent behaviour in one response.'],
            '/staff/{id}/profile' => ['Staff profile', 'The staff member, their contract, role, departments, taught classes and absences.'],
            '/families/{id}/profile' => ['Family profile', 'The family, its adults with contact details and its children.'],
            '/courses/{id}/overview' => ['Course overview', 'The course, its classes and the enrolment count for each.'],
            '/students/{id}/finance' => ['Finance summary', 'Invoices, fees and payments for one person, with outstanding totals.'],
            '/students/{id}/timetable' => ['Timetable', 'The timetabled periods for one person, by day.'],
            '/classes/{id}/roster' => ['Class roster', 'The students and teachers attached to one class.'],
        ];

        foreach ($composites as $path => [$summary, $description]) {
            $paths[$path] = ['get' => [
                'tags' => ['Composites'],
                'summary' => $summary,
                'description' => $description.' Each part is only included when the credential holds '
                    .'the read scope for it, so a narrow credential gets a smaller response rather than an error.',
                'operationId' => 'composite'.self::operationId(trim(str_replace(['/', '{id}'], ['-', ''], $path), '-')),
                'parameters' => [[
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'description' => 'The record this summary is built for.',
                    'schema' => ['type' => 'string'],
                ]],
                'responses' => [
                    '200' => ['description' => $summary.'.'],
                    '403' => self::errorRef('The credential lacks the read scope for the main record'),
                    '404' => self::errorRef('No such record'),
                ],
            ]];
        }

        return $paths;
    }

    /**
     * The ledger write and report routes.
     *
     * These are the only endpoints in the API that hand work to TawasulFinance's
     * posting engine rather than to CrudController, which is why they cannot be
     * described by a resource definition. /journal-entries and /journal-lines
     * are deliberately GET-only in the registry: an entry has to balance, land in
     * an open period and take the next number from a locked sequence, and only
     * this engine knows how to do that.
     */
    protected static function accountingPaths(): array
    {
        $tag = 'Accounting';

        $journalLine = [
            'type' => 'object',
            'properties' => [
                'tawasulFinanceAccountID' => ['type' => 'integer', 'description' => 'A posting account. Headings (isPosting = N) are refused.'],
                'debit' => ['type' => 'number', 'description' => 'Debit amount. Must not be positive at the same time as credit.'],
                'credit' => ['type' => 'number', 'description' => 'Credit amount. Must not be positive at the same time as debit.'],
                'amount' => ['type' => 'number', 'description' => 'Alternative to debit/credit: give the amount and a direction.'],
                'direction' => ['type' => 'string', 'enum' => ['DEBIT', 'CREDIT'], 'description' => 'Used only with amount. Defaults to DEBIT.'],
                'tawasulFinanceCostCenterID' => ['type' => 'integer', 'nullable' => true],
                'tawasulPersonID' => ['type' => 'integer', 'nullable' => true, 'description' => 'Attaches the line to a person, such as a staff member or supplier.'],
                'memo' => ['type' => 'string', 'nullable' => true],
            ],
        ];

        $journalBody = [
            'type' => 'object',
            'required' => ['date', 'lines'],
            'properties' => [
                'date' => ['type' => 'string', 'format' => 'date', 'description' => 'The accounting date. Its fiscal period must exist and be open.'],
                'description' => ['type' => 'string'],
                'documentType' => ['type' => 'string', 'description' => 'Two to six letters, used with the sequence to build the document number. Defaults to JV.'],
                'draft' => ['type' => 'boolean', 'description' => 'Save an unbalanced draft instead of posting. Drafts never appear in the reports.'],
                'sourceType' => ['type' => 'string', 'nullable' => true],
                'sourceID' => ['type' => 'integer', 'nullable' => true],
                'lines' => ['type' => 'array', 'minItems' => 2, 'items' => $journalLine],
            ],
        ];

        $entryResponse = [
            '200' => ['description' => 'The entry, with its lines.'],
            '400' => self::errorRef('The date, lines or documentType are malformed'),
            '403' => self::errorRef('The credential lacks the accounting.write scope or the TawasulOS action'),
            '404' => self::errorRef('The TawasulFinance module is not installed'),
            '422' => self::errorRef('The entry does not balance, hits a heading, or its period is closed'),
        ];

        $asAt = self::parameter('asAt', 'Report only movements up to this date (YYYY-MM-DD). Omit for all time.', 'string');
        $from = self::parameter('from', 'Report only movements from this date (YYYY-MM-DD).', 'string');

        $reports = [
            'trial-balance' => [
                'Trial balance',
                'One row per posting account with its movement, closing balance and whether that balance is a debit or a credit.',
            ],
            'general-ledger' => [
                'General ledger',
                'Every posted line with a running balance, optionally narrowed to one account. Debit-normal and credit-normal accounts are reported in their own sign convention.',
            ],
            'account-types' => [
                'Account type summary',
                'Debits and credits rolled up by account type, for an income or dashboard summary.',
            ],
            'health' => [
                'Ledger health',
                'Whether the ledger nets to zero, plus the open period count and posted entry count.',
            ],
        ];

        $reportPaths = [];
        foreach ($reports as $name => [$summary, $description]) {
            $parameters = [$asAt];
            if ($name !== 'account-types' && $name !== 'health') {
                $parameters[] = $from;
            }
            if ($name === 'general-ledger') {
                $parameters[] = self::parameter('accountID', 'Limit to one account.', 'integer');
            }

            $reportPaths['/accounting/reports/'.$name] = ['get' => [
                'tags' => [$tag],
                'summary' => $summary,
                'description' => $description.' Only posted entries are included.',
                'operationId' => 'report'.self::operationId(str_replace('-', ' ', $name)),
                'parameters' => $parameters,
                'responses' => [
                    '200' => ['description' => $summary.'.'],
                    '400' => self::errorRef('A date was not in YYYY-MM-DD form'),
                    '403' => self::errorRef('The credential lacks the accounting.read scope or the TawasulOS action'),
                ],
            ]];
        }

        return [
            '/accounting/journal' => ['post' => [
                'tags' => [$tag],
                'summary' => 'Post a journal entry',
                'description' => 'Posts a balanced double entry: it must total at least two lines, debits must equal credits to the cent, '
                    .'every account must be active and posting, the date must fall in an open fiscal period, and the document number is taken '
                    .'from the locked sequence for the document type. Everything is written in one transaction, so a failure leaves no partial entry. '
                    .'Set draft to true to store an unbalanced entry for later instead.',
                'operationId' => 'postJournalEntry',
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $journalBody]]],
                'responses' => $entryResponse + ['201' => ['description' => 'The entry was posted.']],
            ]],

            '/accounting/journal/validate' => ['post' => [
                'tags' => [$tag],
                'summary' => 'Validate a journal entry without saving it',
                'description' => 'Runs every posting rule and writes nothing, returning all the reasons an entry would be refused plus the debit and credit '
                    .'totals. Use this while the user is still typing so they see the same errors the ledger would raise.',
                'operationId' => 'validateJournalEntry',
                'requestBody' => ['required' => true, 'content' => ['application/json' => ['schema' => $journalBody]]],
                'responses' => [
                    '200' => ['description' => 'isValid with the list of errors (empty when valid) and the totals.'],
                    '400' => self::errorRef('The date, lines or documentType are malformed'),
                    '403' => self::errorRef('The credential lacks the accounting.write scope'),
                ],
            ]],

            '/accounting/journal/{id}/reverse' => ['post' => [
                'tags' => [$tag],
                'summary' => 'Reverse a posted journal entry',
                'description' => 'Writes a mirror-image entry with the debits and credits swapped, so the pair nets to zero. The original stays Posted, '
                    .'because dropping it as well would reverse the transaction twice and leave the accounts wrong; it is linked to the reversal through '
                    .'reversedEntryID. Reversing an entry twice is refused.',
                'operationId' => 'reverseJournalEntry',
                'parameters' => [[
                    'name' => 'id',
                    'in' => 'path',
                    'required' => true,
                    'description' => 'The tawasulFinanceJournalEntryID to reverse.',
                    'schema' => ['type' => 'string'],
                ]],
                'requestBody' => ['content' => ['application/json' => ['schema' => [
                    'type' => 'object',
                    'properties' => ['date' => ['type' => 'string', 'format' => 'date', 'description' => 'Date of the reversal entry. Defaults to today.']],
                ]]]],
                'responses' => [
                    '200' => ['description' => 'The original and the reversal entry.'],
                    '403' => self::errorRef('The credential lacks the accounting.write scope or the TawasulOS action'),
                    '404' => self::errorRef('No such entry'),
                    '422' => self::errorRef('The entry is not posted, or has already been reversed'),
                ],
            ]],

        ] + $reportPaths;
    }

    protected static function filePaths(): array
    {
        $resourceParam = [
            'name' => 'resource', 'in' => 'path', 'required' => true,
            'description' => 'Any resource name from /v2/resources.',
            'schema' => ['type' => 'string'],
        ];
        $idParam = [
            'name' => 'id', 'in' => 'path', 'required' => true,
            'description' => 'The primary key of the record.',
            'schema' => ['type' => 'string'],
        ];
        $fieldParam = [
            'name' => 'field', 'in' => 'path', 'required' => true,
            'description' => 'A file field on that record, as listed by /v2/files/{resource}/{id}.',
            'schema' => ['type' => 'string'],
        ];
        $disabled = self::errorRef('File transfer is switched off for this TawasulOS');

        return [
            '/files/{resource}/{id}' => ['get' => [
                'tags' => ['Files'],
                'summary' => 'List the file fields on a record',
                'description' => 'Reports each file field, whether a file is attached, and its download URL.',
                'operationId' => 'listRecordFiles',
                'parameters' => [$resourceParam, $idParam],
                'responses' => ['200' => ['description' => 'The file fields on that record.'], '404' => self::errorRef('No such record'), '503' => $disabled],
            ]],
            '/files/{resource}/{id}/{field}' => [
                'parameters' => [$resourceParam, $idParam, $fieldParam],
                'get' => [
                    'tags' => ['Files'],
                    'summary' => 'Download the attached file',
                    'description' => 'Streams the file as an attachment. Pass `?disposition=inline` to display it instead. '
                        .'Paths are confined to this Tos\'s own file storage.',
                    'operationId' => 'downloadRecordFile',
                    'parameters' => [self::parameter('disposition', 'Set to inline to display rather than download.')],
                    'responses' => [
                        '200' => ['description' => 'The file.', 'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
                        '404' => self::errorRef('No record, no such field, or no file attached'),
                        '503' => $disabled,
                    ],
                ],
                'post' => [
                    'tags' => ['Files'],
                    'summary' => 'Attach or replace a file',
                    'description' => 'Send multipart form-data with a `file` part, or JSON with `filename` and '
                        .'`contentBase64`. The record is updated to point at the stored file and the file it '
                        .'replaced is deleted.',
                    'operationId' => 'uploadRecordFile',
                    'requestBody' => ['required' => true, 'content' => [
                        'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => [
                            'file' => ['type' => 'string', 'format' => 'binary'],
                        ]]],
                        'application/json' => ['schema' => ['type' => 'object',
                            'required' => ['filename', 'contentBase64'],
                            'properties' => [
                                'filename' => ['type' => 'string'],
                                'contentBase64' => ['type' => 'string', 'description' => 'Base64 content; a data URL prefix is accepted.'],
                            ],
                        ]],
                    ]],
                    'responses' => [
                        '201' => ['description' => 'The updated record and the stored file.'],
                        '413' => self::errorRef('The file is larger than the configured maximum'),
                        '422' => self::errorRef('No file was sent, or its extension is not allowed'),
                        '503' => $disabled,
                    ],
                ],
                'delete' => [
                    'tags' => ['Files'],
                    'summary' => 'Detach and delete the file',
                    'operationId' => 'detachRecordFile',
                    'responses' => [
                        '200' => ['description' => 'The field was cleared and the file removed.'],
                        '404' => self::errorRef('There was no file attached'),
                        '503' => $disabled,
                    ],
                ],
            ],
        ];
    }

    protected static function metaPaths(): array
    {
        return [
            '/health' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'Service health', 'operationId' => 'health', 'security' => [],
                'responses' => ['200' => ['description' => 'The API is reachable and the database responds.']],
            ]],
            '/resources' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'List every available resource', 'operationId' => 'resources',
                'responses' => ['200' => ['description' => 'Resources grouped by area, with their filters and methods.']],
            ]],
            '/scopes' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'List every scope an API key can hold', 'operationId' => 'scopes',
                'responses' => ['200' => ['description' => 'All read and write scopes.']],
            ]],
            '/events' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'List every webhook event', 'operationId' => 'events',
                'responses' => ['200' => ['description' => 'Change events a webhook can subscribe to.']],
            ]],
            '/stats' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'School-wide statistics', 'operationId' => 'stats',
                'responses' => ['200' => ['description' => 'Counts of students, staff, courses, classes, form groups, year groups, API usage and more.']],
            ]],
            '/analytics' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'Request analytics and trends', 'operationId' => 'analytics',
                'parameters' => [
                    ['name' => 'days', 'in' => 'query', 'required' => false,
                     'description' => 'Lookback window in days (1-365, default 1).',
                     'schema' => ['type' => 'integer', 'default' => 1]],
                ],
                'responses' => ['200' => ['description' => 'Request counts, error rates, daily trends, busiest endpoints and method/status breakdowns.']],
            ]],
            '/openapi.json' => ['get' => [
                'tags' => ['Meta'], 'summary' => 'This document', 'operationId' => 'openapi',
                'responses' => ['200' => ['description' => 'The OpenAPI 3 specification.']],
            ]],
        ];
    }

    protected static function operationId(string $name): string
    {
        return str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
    }

    /**
     * A compact route table for the in-TawasulOS documentation page.
     */
    public static function routeTable(): array
    {
        $table = [];

        foreach (Registry::groups() as $group => $resources) {
            foreach ($resources as $name => $resource) {

                $table[$group][] = [
                    'resource' => $name,
                    'title' => $resource['title'],
                    'description' => $resource['description'],
                    'methods' => $resource['methods'],
                    // Taken from the definition, not derived from the name: a
                    // resource may share a base scope with its siblings (all
                    // eight accounting resources answer to "accounting"), and
                    // "<resource>.read" would then name a scope that authorise()
                    // never checks, so a key built from this document would be
                    // refused on the first call.
                    'scopeRead' => $resource['scope'].'.read',
                    'scopeWrite' => $resource['scope'].'.write',
                    'scopeSharedWith' => self::siblingResources($name, $resource['scope']),
                    'filters' => $resource['filters'],
                    'module' => $resource['module'],
                ];
            }
        }

        return $table;
    }

    /**
     * The other resources that answer to the same base scope.
     *
     * Worth stating plainly because the grouping decides what a credential can
     * reach: "accounting.read" covers the whole ledger, not just accounts, and
     * a caller who granted it expecting one endpoint has granted all of them.
     */
    protected static function siblingResources(string $name, string $scope): array
    {
        $siblings = [];

        foreach (Registry::all() as $other => $resource) {
            if ($other !== $name && $resource['scope'] === $scope) {
                $siblings[] = $other;
            }
        }

        sort($siblings);

        return $siblings;
    }
}
