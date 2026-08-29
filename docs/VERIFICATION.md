# Verification Notes

## Verified in build environment
- PHP syntax lint for all PHP files under app, bootstrap, config, database, routes, and tests.
- JavaScript syntax check for `resources/js/app.js`.
- JSON parsing for `composer.json` and `package.json`.
- Required project structure and reference USP file presence.
- Git whitespace check (`git diff --check`).

## Not executable in build environment
Full Laravel test execution and Vite build require dependencies from Composer/NPM. The build environment has no Composer binary and outbound DNS/network is blocked, so `vendor/` and `node_modules/` cannot be installed here.

Run locally after dependency installation:

```bash
composer install
npm install
npm run build
php artisan test
```

Then test against a PostgreSQL database and read-only SQL Server ERP credentials.
