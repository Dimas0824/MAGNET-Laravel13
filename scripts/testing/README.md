# Testing tooling

Helpers to run the Pest suite and measure coverage for this project.

## Run tests

Tests use **MySQL** (`db_magnet_test`, see `phpunit.xml`). `RefreshDatabase` runs
`migrate:fresh` before each Feature test — **never run the suite in parallel**
against the same database.

Load xdebug from the in-repo scan dir so `php artisan test` and coverage work:

```powershell
$env:PHP_INI_SCAN_DIR="$PWD\scripts\testing\phpini"
php artisan test
```

## Coverage

```powershell
powershell -File scripts\testing\coverage.ps1            # summary table + overall %
powershell -File scripts\testing\coverage.ps1 -Filter=DataPreprocessing
```

Options:
- `-PhpExe <path>` — override the PHP binary (defaults to `php` on PATH, then the FlyEnv path).

## xdebug ini

`scripts/testing/phpini/zz-xdebug.ini` points at this machine's xdebug DLL.
On another machine, update the `zend_extension=` path to your xdebug build
(and make sure `xdebug.mode=coverage`).

Generated output (`coverage-clover.xml`) is git-ignored.
