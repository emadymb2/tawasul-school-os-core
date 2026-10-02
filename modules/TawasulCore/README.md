# TawasulOS REST API (v2)

A complete, read-and-write REST interface for TawasulOS v31, built as a standard
TawasulOS module. It exposes 343 resources across the core system and the community
modules — 87 curated by hand, the rest generated from the database schema —
with 489 navigable relations, API keys, user tokens, scopes, role enforcement,
filtering, pagination, bulk writes, file transfer, webhooks, rate limiting,
request logging and a generated OpenAPI 3 specification.

## Installing

1. Copy the `TawasulCore` folder into your TawasulOS installation's `modules` folder.
2. In TawasulOS, go to **Admin > System Admin > Manage Modules** and install
   **TawasulCore**.
3. Give the relevant roles access to the module's four actions under
   **Admin > User Admin > Manage Permissions**.
4. Open **TawasulCore > Manage Settings** and switch the API on.
5. Create a key under **TawasulCore > Manage API Keys**. The key is shown once.

## Calling the API

```
https://your-gibbon/modules/TawasulCore/api.php/v2/{resource}
```

If your server does not pass `PATH_INFO`, the same request works as
`api.php?endpoint=/v2/{resource}`.

```bash
curl -H 'Authorization: Bearer tws_xxx' \
  'https://your-gibbon/modules/TawasulCore/api.php/v2/students?pageSize=25&sort=surname'
```

Every collection endpoint supports:

| Parameter | Meaning |
| --- | --- |
| `page` | Page number, from 1 |
| `pageSize` | Records per page, capped by the `maxPageSize` setting |
| `sort` | Sort field, `-field` for descending |
| `search` | Free-text search over the resource's searchable fields |
| `fields` | Comma-separated list of fields to return |
| `school_year_id` | Work in a school year other than the current one |
| *(resource filters)* | Listed per resource in the docs page and in the spec |

Numeric and date filters also accept `{filter}From` and `{filter}To`.

## Authentication

**API keys** (`tws_...`) are for server-to-server integrations. A key may be
bound to a person, in which case requests are also limited by that person's
TawasulOS roles, and to a default school year. Keys support expiry dates, IP
allow lists (single addresses, ranges or CIDR) and per-minute rate limits.

**User tokens** (`tok_...`) come from a username, email, or phone number and password:

```bash
curl -X POST -H 'Content-Type: application/json' \
  -d '{"username":"jbloggs","password":"..."}' \
  'https://your-gibbon/modules/TawasulCore/api.php/v2/auth/login'
```

The `username` field accepts a TawasulOS username, email address, or phone number
(`phone1`–`phone4`), depending on the `loginMethod` setting (configurable under
**TawasulCore > Manage Settings** or **System Admin > Settings**). When set to
`all`, any identifier type is accepted; `phone` restricts to phone fields only;
`username` (the default) matches username and email.

Passwords are verified exactly the way TawasulOS's own login does, so account
status and lockout after failed attempts both still apply. Tokens are renewed
via `/auth/refresh` (the pair rotates on every refresh) and cancelled via
`/auth/logout`.

## Permissions

Two independent gates must both pass:

1. **Scopes** on the credential, for example `students.read` or `behaviour.write`.
   `*` grants everything; a bare `students` covers read and write.
2. **TawasulOS roles**, when `enforceRolePermissions` is on. The API mirrors
   `isActionAccessible()` against the same `tos_action`/`tos_permission`
   tables the web interface uses, so a credential can never reach data its
   person could not open in a browser.

Writes are additionally gated globally by the `allowWrites` setting, and
community-module endpoints by `exposeModules`.

## Endpoints

| | |
| --- | --- |
| `GET /v2` | What this API is and where to look next |
| `GET /v2/health` | Reachability, no credential needed |
| `GET /v2/resources` | Every resource with its methods, scopes and filters |
| `GET /v2/scopes` | Every scope a key can hold |
| `GET /v2/openapi.json` | The OpenAPI 3 specification |
| `POST /v2/auth/login` · `refresh` · `logout` · `GET /v2/auth/me` | Authentication |
| `GET/POST /v2/{resource}` | List and create |
| `GET/PATCH/DELETE /v2/{resource}/{id}` | Read, update and delete |
| `GET /v2/{resource}/{id}/{relation}` | Follow a link to its parent record, or list the children pointing back at it |
| `POST /v2/{resource}/bulk` | Create or update many records in one transaction |
| `GET /v2/students/{id}/profile` | Student, classes, family, attendance, behaviour and medical alerts in one call |
| `GET /v2/staff/{id}/profile` | Staff member, contract, role, departments, classes taught and absences |
| `GET /v2/families/{id}/profile` | Family with its adults, their contact details and its children |
| `GET /v2/courses/{id}/overview` | Course with its classes and enrolment counts |
| `GET /v2/students/{id}/finance` | Invoices, fees, payments and the outstanding total |
| `GET /v2/people/{id}/timetable?date=` | A day's timetable for a student or teacher |
| `GET /v2/classes/{id}/roster` | Teachers and students in a class |
| `GET /v2/files/{resource}/{id}` | The file fields on a record |
| `GET/POST/DELETE /v2/files/{resource}/{id}/{field}` | Download, replace or detach the file itself |

Composite endpoints re-check the scope for each section they return, and omit
sections the credential may not see rather than failing the whole request.

## Bulk writes and transactions

Single writes run in a database transaction. `POST /{resource}/bulk` takes up to
`bulkMaxItems` records and commits them together, so a batch either applies in
full or leaves nothing behind. Audit and history tables are published read-only
and answer 405 with the reason, so an audit trail cannot be rewritten through
the API.

## Files

File transfer is off until you switch on `filesEnabled`. Uploads accept either
multipart form-data with a `file` part or JSON with `filename` and
`contentBase64`, and are checked against `fileExtensions` and `fileMaxSizeMB`.
Reads and writes stay inside TawasulOS's own file storage, and downloads reuse the
resource's read scope.

## Rate limits and lockout

Every response carries `X-RateLimit-Limit`, `X-RateLimit-Remaining` and
`X-RateLimit-Reset`. API keys use their own per-minute limit; password-issued
tokens share the `tokenRateLimit` setting. Repeated failed sign-ins from one
address are locked out for `loginLockoutMinutes` after `loginMaxAttempts`.

## Webhooks

With webhooks enabled, every create, update and delete is posted to the
subscribed URLs as `{resource}.created`, `.updated` or `.deleted`, signed with
`X-TawasulOS-Signature` (`sha256=` HMAC of the raw body using the subscription
secret). Deliveries and their responses are recorded so failures can be traced.

## Responses

```json
{
  "data": [ ... ],
  "meta": { "resource": "students", "page": 1, "pageSize": 50, "total": 412, "totalPages": 9 }
}
```

Errors always take one shape, and never leak SQL or stack traces:

```json
{ "error": { "code": "forbidden", "message": "...", "details": { } } }
```

## How it is built

- `api.php` — the entry point. It deliberately bypasses `index.php`, booting
  only `tawasul.php` for configuration and the database, because the front
  controller assumes a browser session, renders HTML and runs the CSRF gate.
- `src/Resource/definitions/*.php` — the declarative registry. One entry per
  resource describes its table, selectable columns, filters, sorts, writable
  fields and sensitive columns.
- `src/Support/QueryBuilder.php` — turns a request into parameterised SQL from
  that definition. Unknown filters are rejected with the list of valid ones.
- `src/Controller/CrudController.php` — one implementation serving every
  resource, so behaviour is identical across all of them.
- `src/Support/OpenApi.php` — generates the specification and the in-TawasulOS
  documentation page from the same registry, so documentation cannot drift
  from behaviour.

## Security notes

- Key and token secrets are stored only as hashes; the plain value is shown
  once at creation.
- Sensitive columns (password hashes, salts, secrets) are stripped from output
  at the query layer, not the controller.
- The Query Builder and Credentials modules are intentionally not exposed.
- All queries are parameterised; identifiers come only from the registry, never
  from request input.

## Licence

GNU General Public License v3 or later, matching TawasulOS core.
