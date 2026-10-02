# Tawasul School OS — server setup

The core is now a white-labelled GibbonEdu core (Track A in the blueprint docs).
Nothing here has been run or tested yet. Run these steps on the server
`tos.fiksutiliratkaisut.fi` (PHP 8.1+, MySQL 8, Composer).

## 1. Install PHP dependencies
```bash
composer install --no-dev
```

## 2. Database
- **New install:** open `https://tos.fiksutiliratkaisut.fi/installer/install.php` and follow the steps. It uses `tawasul.sql`.
- **Existing `tos_*` data (tawasul_os.sql):** import it, then run
  `mysql your_db < migrations/tos_snake_to_camel.sql`
  so the tables/columns match the code (`tos_person` -> `tawasulPerson`, `person_id` -> `tawasulPersonID`).
  Back up first.

## 3. Config
The installer writes `config.php` in the root. Never commit it.

## 4. Mobile app API
- Enable the **TawasulCore** module in System Admin -> Manage Modules.
- API address: `https://tos.fiksutiliratkaisut.fi/modules/TawasulCore/api.php/v2`
  (fallback when PATH_INFO is off: `api.php?endpoint=/v2/...`).
- API keys now start with `tws_`. Create one under TawasulCore -> Manage Keys if the app needs a pre-login key.

## 5. What moved
- The old login/dashboard shell (`tawasul.php`, `src/`, `public/`, `config/`, `migrate_data.php`) is in `_legacy_shell/`. It exposed secrets in `config/config.php`, so **change those database passwords and salts**.
- Rebrand scripts: `tools/rebrand/` (re-run against a newer Gibbon release to update the core).

## 6. Licence
The core is GPL-3.0. Original copyright headers are kept in the source (never customer-facing). As in docs/04, sell it as SaaS or managed hosting only until the in-house engine replaces it.
