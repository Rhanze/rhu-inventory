# Deploy the RHU system on Vercel

This repository uses Vercel's **Container** preset with `Dockerfile.vercel`, PHP 8.4 / FrankenPHP, and built Vite assets. It does not use the community PHP runtime. Public and management remain separate Vercel projects. No cloud services, databases, DNS records or deployments have been created by these code changes.

## 1. Prepare external services

You need:

- A hosted **MySQL or MariaDB** database reachable from Vercel. Do not select a Postgres integration without rewriting and testing the application's MySQL-specific queries. XAMPP's `127.0.0.1` database cannot be used by a remote deployment.
- Shared Redis using its **TCP/TLS connection**, not a REST-only URL. The PHP Redis extension is included in the image. Public and management should use separate credentials/instances; key prefixes alone do not isolate a compromised client.
- A **private S3-compatible bucket** for management prescription attachments. The S3 adapter is included. Vercel Blob is not automatically S3-compatible with this implementation.
- An authenticated scheduler calling management's `/internal/scheduler` once per minute. This replaces relying on a continuously running local scheduler process.

Choose services in compatible nearby regions and retain their connection details in your password manager/Vercel environment settings. No provider account or paid resource is provisioned automatically.

## 2. Prepare the database

For your current imported installation, back up and export the local database through phpMyAdmin, then import it into the hosted MySQL database. Transfer data only when you are authorized to publish it to that hosting environment. Keep SQL exports out of the repository and web root.

For a new empty database, follow README.md's fresh-install migration/reference-seeding instructions from a trusted administration machine connected to the hosted database. For an imported database, follow the explicit October upgrade paths; do not replay old unrecorded creation migrations. The Docker image **never runs migrations or sample seeders automatically**.

Create a dedicated read-only public database account limited to public medicine, inventory and facility data, and a separate management account. Use provider-required TLS, pool/connection limits, firewall settings and backups. Do not expose a local XAMPP MySQL port to the internet. Provision the initial active PHO administrator through your trusted admin process; never ship a default password.

## 3. Create two Vercel projects

Create projects such as `rhu-public` and `rhu-management`, importing the existing repository into each with repository root as Root Directory. Choose **Container**, not Vite. `Dockerfile.vercel` and `vercel.json` select that preset. An initial deploy can fail until the required environment variables are entered; configure them and redeploy.

If the source is not yet in your connected repository, it must be reviewed and uploaded through your chosen deployment workflow first. No Git commit or push was performed by this change. Alternatively use Vercel CLI after account login and project linking. Keep the two project links separate; do not accidentally deploy the public configuration into management.

You may initially use each project's stable `*.vercel.app` address. Owning an `.org` domain is not required to start. Later attach your registered public domain and management subdomain in each project's Domains settings and update both domain settings accordingly.

## 4. Configure project environment variables

Use `deployment/vercel-public.env.example` and `deployment/vercel-management.env.example` as checklists. Add values in **Vercel → Project → Settings → Environment Variables**; never commit real credentials or upload your XAMPP `.env`.

Both projects need the same `PUBLIC_DOMAIN` and `MANAGEMENT_DOMAIN` values, with no scheme or path. For example:

| Setting | Public project | Management project |
| --- | --- | --- |
| `PUBLIC_DOMAIN` | `rhu-public.vercel.app` | `rhu-public.vercel.app` |
| `MANAGEMENT_DOMAIN` | `rhu-management.vercel.app` | `rhu-management.vercel.app` |
| `APP_URL` | `https://rhu-public.vercel.app` | `https://rhu-management.vercel.app` |
| `PORTAL_SURFACE` | `public` | `management` |
| `APP_KEY` | Unique public key | Different management key |
| Database account | Public read-only account | Management account |
| Redis credentials | Public session/cache instance | Management session/cache instance |

Generate an APP_KEY locally for each project with:

```powershell
php artisan key:generate --show
```

Paste each key directly into that project's secret environment setting. Do not post the generated values in chat. Keep keys stable across subsequent deployments; rotating them invalidates encrypted sessions and other encrypted data.

Use `SESSION_DRIVER=redis`, `CACHE_STORE=redis`, `REDIS_CLIENT=phpredis`, and the provider's TLS host/port/user/password. Set `REDIS_DB=0` and `REDIS_CACHE_DB=0` if the provider supports only database 0. Leave sessions host-only; the application enforces separate portal cookie names. Public sessions and cache must not depend on write access to the read-only business database.

For database TLS, `/etc/ssl/cert.pem` uses the image's trusted CA bundle. Use the provider's required CA if it needs a different one, provided securely to the container; never disable certificate verification to fix a connection error.

Management additionally needs `PRESCRIPTION_DISK=s3`, private bucket credentials, region, bucket and a random `CRON_SECRET` of at least 32 characters. Set `AWS_ENDPOINT` only for a provider requiring a custom endpoint; omit it for standard AWS S3. Configure path-style addressing according to the provider. No public bucket permissions are required.

Existing local prescription files are **not copied automatically**. Copy `storage/app/private/prescriptions/` into the private bucket under the same `prescriptions/` keys before switching the production disk, then verify authorized downloads. Do not place them under `public/`.

Docker defaults production logs to stderr, queues to synchronous execution, and framework/cache compilation files to `/tmp`. `/tmp` is scratch space only; session data and attachments never belong there. Runtime startup validates required configuration and creates framework caches with that deployment's environment. It does not need your production secrets during image build.

## 5. Deploy and verify

Redeploy both projects after setting environment variables. In the build logs, confirm the Container preset and Composer/Vite stages run successfully. The startup verifier gives configuration-variable names when settings are missing, without printing their secret values.

Test:

1. Public root: medicine search with no login required.
2. Public `/login`, `/dashboard` and `/inventory`: 404.
3. Management root: staff login redirect.
4. Management `/inventory` without a session: login redirect.
5. Staff, administrator and midwife login: appropriate dashboard and permissions.
6. Logout/login after instance restart: shared sessions remain consistent.
7. Upload a test prescription, redeploy, then download it as an authorized user. Unauthorized requests must still be denied.
8. Public search reflects a test management stock update, using the shared business database.

`/up` checks application boot, not the health of MySQL, Redis or private object storage. Unknown preview/deployment aliases intentionally do not match the configured production hostnames. To test a preview, supply dedicated preview domain/environment values and non-production services; never widen the hostname guard to every `*.vercel.app` domain.

Vercel terminates TLS before the container. The bootstrap trusts forwarded protocol/port only when Vercel's `VERCEL=1` system variable is present. It deliberately does not trust forwarded Host for portal routing. Ensure Vercel system variables are exposed and the intended Host reaches PHP. Do not expose the container port as a public bypass outside the platform.

## 6. Enable the scheduler

Configure a scheduler to send GET requests every minute to the **exact management hostname**:

```text
https://rhu-management.vercel.app/internal/scheduler
Authorization: Bearer <your CRON_SECRET>
```

Only management accepts the endpoint; no browser login is needed because the long bearer secret authenticates it. Missing/wrong secrets return 403. Shared Redis locks prevent concurrent invocations, and successful invocations are deduplicated within a minute. Monitor HTTP failures and management logs. The existing four scheduled tasks retain the application's Asia/Manila timing.

You may use Vercel Cron if your plan supports the required frequency. Add a cron entry for `/internal/scheduler` **only to the management project** and configure `CRON_SECRET`; Vercel sends it in the Authorization header. Verify the hostname Vercel actually calls matches `MANAGEMENT_DOMAIN`. With custom domains or an incompatible schedule limit, use an external scheduler targeting the exact management URL instead. Daily-only invocation will not preserve the hourly/minute scheduler behavior.

No cron entry is enabled in the shared root `vercel.json`, so importing the public project cannot accidentally activate management jobs. HTTP jobs must finish within the configured function duration. Use a duration below the 360-second invocation lock; larger workloads need a supported external worker/daemon and explicit queue setup. Do not launch background workers from the web entrypoint.

## Verification limits

After the compatibility changes and dependency security updates, the regression suite passed on SQLite and disposable MariaDB: **75 tests, 1,278 assertions**. The competing transfer receipt check, Composer manifest validation, production asset build and Laravel route caching also passed. Composer reported no security advisories after updating compatible dependency releases. This does not constitute a full infrastructure/security audit.

Automated tests cover private file streaming, scheduler authentication/deduplication, configuration validation, existing business workflows, and domain isolation. Docker is not installed in the current workspace environment, so the image has not been built or run here. Once Docker is available, build with `docker build -f Dockerfile.vercel -t rhu-vercel .`, then test with non-production service credentials before going live. Cloud service connectivity, provider permissions and live Vercel behavior require an actual deployment.

References: [Vercel's PHP/Docker guide](https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker), [Container preset detection](https://vercel.com/changelog/deploy-a-container-from-a-plain-dockerfile), [cron authentication](https://vercel.com/docs/cron-jobs/manage-cron-jobs).
