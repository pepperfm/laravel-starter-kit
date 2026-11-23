# Laravel Installer lifecycle contract

Verified against [laravel/installer v5.33.0](https://github.com/laravel/installer/blob/v5.33.0/src/NewCommand.php)
(November 10, 2026) and [Laravel 13's ApiInstallCommand](https://github.com/laravel/framework/blob/13.x/src/Illuminate/Foundation/Console/ApiInstallCommand.php).
These are source-code observations, not an assertion that every installed global version behaves identically.
Run `laravel --version` on the actual machine before reproduction.

## Real order for `laravel new app --using=pepperfm/laravel-starter-kit --database=pgsql`

| Stage | Owner / environment | Notes |
| --- | --- | --- |
| 1. `composer create-project` | Installer / host | Custom `--using` kits run Composer scripts. `LARAVEL_INSTALLER_DEFER_HOOKS=1` is set during this stage. |
| 2. Composer scripts | Composer / host | `post-root-package-install` ensures `.env` exists. Our `post-create-project-cmd` skips key generation with the defer flag; direct `composer create-project` still generates it. |
| 3. `composer run post-root-package-install`, `artisan key:generate` | Installer / host | Explicit initialization, before our hook. Avoids a second key rotation. |
| 4. `extra.laravel.installer.post-create-project` | Installer / host | Runs `artisan starter:setup --installer-hook`. Hook prompts and validates files/tools, prepares host-compatible `.env`, optionally starts Sail and waits for DB/Redis health, installs missing Composer packages in the selected runtime, publishes frontend files and vendor assets, then runs selected *non-migration* Artisan commands. |
| 5. `APP_URL`, `DB_CONNECTION` and DB placeholders | Installer / host | Installer modifies both `.env` and `.env.example` for the selected driver. Existing custom DB names/credentials are retained unless they match Installer's placeholder strings. |
| 6. `artisan migrate` | Installer / host | With explicit `--database=pgsql`, migration is unconditionally attempted. The starter hook must NOT run it earlier. |
| 7. Git/Pest integration | Installer / host | Optional flags only. |
| 8. Node manager selection, install, build | Installer / host | Honors `--bun`, `--npm`, `--pnpm`, `--yarn`, `--no-node`; otherwise lockfile detection and optional interactive prompt. Installer removes lockfiles from other managers. |
| 9. Boost and closing instructions | Installer / host | Optional Boost work and final output. |

References in `NewCommand.php` v5.33.0: `execute()` lines 630–760;
`configureDefaultDatabaseConnection()` ~890–950; `promptForDatabaseOptions()`
~1023–1052; `installNodeDependencies()` ~1175–1210;
`runInstallerHooks()` ~1386–1401. Direct verification is important when
upgrading the globally installed Laravel Installer.

## Ownership and runtime boundaries

**Composer:** Laravel Installer's initial `composer create-project` always runs on
the host. For the starter hook's optional `composer require` commands, Sail mode
starts the app container first and runs Composer there; Host mode keeps running
Composer on the host. The host must still satisfy the initial project's PHP and
Composer requirements.

**Artisan publication:** After dependency installation, before DB migration.
Runs through Sail if selected and running, otherwise on the host. Sanctum API
scaffolding is created directly, without invoking `install:api` (which itself
calls Composer). Vendor migrations for Pulse, Telescope, etc. are published
before the Installer's migration stage.

**Docker:** Only when Sail is selected. `docker compose --progress plain up -d
--build --wait --wait-timeout 180` is run with streaming output before the
starter hook's optional Composer packages are installed. Already-running
containers use `up -d --wait` without a rebuild. Docker Compose healthchecks
establish DB/Redis readiness. A Compose failure stops the setup hook.

**Database / Redis:** The main `.env` uses host-reachable addresses so the
Installer's *host* migration can succeed. In the application container
`LARAVEL_SAIL=1` makes `config/database.php` translate loopback addresses
and forwarded ports to `pgsql:5432` and `redis:6379`. Explicit remote
hostnames/URLs remain untouched. A custom `FORWARD_DB_PORT` must be reflected
in the host's `DB_PORT` if used.

**Frontend:** Admin preset only publishes project files in the Installer hook;
the Installer then selects ONE package manager and installs/builds. With
`--no-node` the hook receives `LARAVEL_INSTALLER_NO_NODE=1` and neither phase
installs Node dependencies. Manual `starter:setup` can install dependencies
after publication and uses the applicable lockfile.

## Important limitation of Option A

When `--database=pgsql` is passed, the Installer does not provide a public
`--no-migrate` switch: `promptForDatabaseOptions()` sets `migrate=true` for an
explicit database, and `execute()` migrates **on the host after the hook**.

Therefore we cannot simultaneously promise to preserve `laravel new ... --using`
unchanged, avoid the Installer's host migration, and offer a true migration
opt-out. An Artisan hook does not control the parent Installer's option
state. Never implement this by silently resetting DB_CONNECTION, suppressing
an error, or adding a background process.

For users requiring a migration opt-out, use `composer create-project` and
then run `php artisan starter:setup --no-migrate` manually. A separate custom
new-project orchestrator is not introduced solely for this edge case.

Other constraints:

- The host must have PHP 8.5, Composer and `pdo_pgsql` for the normal
  `--database=pgsql` command. Running migrations inside Sail during the hook
  does **not** remove this Installer requirement.
- Host mode assumes PostgreSQL/Redis are already reachable; it does not
  start containers.
- The Laravel Installer still performs its own later migrations on the host
  when explicitly requested with `--database=pgsql`, even if Sail was chosen
  for the starter hook.
- Docker Compose must be reachable if Sail mode is selected.
- Installer Node operations are host operations, not Sail operations. Use
  `--no-node` and install manually inside Sail if the host lacks the chosen manager.
- Composer and vendor publishing are not rolled back on failure. Re-run setup
  after fixing the failure; it skips installed requirements and known
  already-published assets. A partially failed *individual* vendor publish
  may need manual review.

## Smoke-test matrix (manual, not CI assertions)

Use fresh temporary directories and the actual global Installer to verify:

1. Run `laravel new smoke-minimal --using=pepperfm/laravel-starter-kit --database=pgsql --no-node`;
   choose host, no optional packages. Check `APP_KEY`, `DB_HOST`, and migrations.
2. Repeat with Sail, select `api` / `admin` / `full` components on separate
   projects. Check streaming Docker output, `docker compose ps` health, published
   files, database migration count, and frontend build.
3. Repeat with explicit `--bun` or `--npm`. Ensure a single manager is used,
   alternate lockfiles are removed, and no Node install runs in the hook.
4. Repeat with `--no-node`. Ensure no frontend node_modules are created.
5. Run `php artisan starter:setup --preset=full --no-migrate --skip-node`
   twice. Verify no repeated Composer require for installed packages and no
   overwritten admin files.
6. Disconnect Docker or break Composer resolution before confirming setup.
   Check the failure is visible and rerunning after restoring services works.
7. Test non-default forwarded DB/Redis ports, a custom remote DB_URL, and a
   host-only PostgreSQL installation.

Full Docker/Installer smoke tests require PHP 8.5, a working Docker daemon,
PostgreSQL host access, and downloaded dependencies. Unit and source tests
alone do not prove these end-to-end scenarios.
