# Public / management domain split

Public visitors use the root domain; staff, administrators and midwives use a management subdomain. The application is ready for these hostnames but this change does not register a domain, create DNS records, install TLS certificates, or modify Apache/Windows hosts outside the project.

## What the application enforces

- `PUBLIC_DOMAIN`: public medicine search at `/`, `/public`, medicine details, public autocomplete and legal pages. No public map.
- `MANAGEMENT_DOMAIN`: `/` redirects to staff login or the authenticated dashboard. Login, patient services, stock management, forecasts, exports and management AJAX endpoints are on this hostname only.
- Private routes on the public hostname return 404, including login POSTs. Public search routes are not mirrored on the management hostname.
- All management authorization and role/facility/ownership checks remain in place. Hostnames do not grant permissions.
- Cookie names differ by portal, omit the Domain attribute, and use path `/`, HttpOnly and SameSite=Lax. HTTPS mode uses Secure cookies with `__Host-` session names. A configured shared `SESSION_DOMAIN` is deliberately overridden.
- `PORTAL_SURFACE=public` rejects management-host requests on that deployment; `management` rejects public-host requests. `both` is for local development only.
- Production refuses web access when the split is disabled, the surface is `both`, or HTTPS-only mode is off. HTTPS mode rejects requests Laravel sees as HTTP; redirect HTTP at the web server before it reaches PHP.

The Laravel health endpoint `/up` and web-server static assets are separate from business-route groups. Restrict hosts at the web server as shown below. Do not configure wildcard host aliases or a catch-all production virtual host serving this application.

## Local XAMPP setup

The defaults are `rhu-inventory.test` and `management.rhu-inventory.test`. They are local names, not registered production domains. The previous localhost/subdirectory URL will not match the new domain-bound routes.

1. Add these entries to the Windows hosts file **with administrator access**:

   ```text
   127.0.0.1 rhu-inventory.test
   127.0.0.1 management.rhu-inventory.test
   ```

2. Add the virtual-host definitions in `deployment/apache-local-portals.conf.example` to the XAMPP Apache configuration, ensuring `httpd-vhosts.conf` is included. This example uses the existing project for both hosts in local development.
3. Set or retain the following values in the local environment:

   ```dotenv
   APP_URL=http://management.rhu-inventory.test
   PORTAL_DOMAIN_SPLIT=true
   PUBLIC_DOMAIN=rhu-inventory.test
   MANAGEMENT_DOMAIN=management.rhu-inventory.test
   PORTAL_SURFACE=both
   PORTAL_HTTPS_ONLY=false
   SESSION_DOMAIN=null
   ```

4. Run `php artisan config:clear` and `php artisan route:clear`, then restart Apache.
5. Open `http://rhu-inventory.test/` for public search and `http://management.rhu-inventory.test/` for login.

For temporary legacy single-host development only, `PORTAL_DOMAIN_SPLIT=false` restores the previous shared landing and routes. This is blocked in production. Existing staff sessions must log in again because their cookie name has changed.

## Production: two deployments

`.org` alone is a domain suffix. Replace `your-rhu.org` below with a domain you actually register and control.

1. Create DNS records for `your-rhu.org` and `management.your-rhu.org`, pointing to the respective deployment servers or load balancers.
2. Create **two application instances** from this repository. Use separate release directories, PHP worker pools/containers or servers, storage directories, session/cache stores, environment files and independently generated APP_KEY values. They can be upgraded independently, but must remain compatible with the shared schema. A shared codebase remains; this change does not create two separate repositories.
3. Configure public instance:

   ```dotenv
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-rhu.org
   PORTAL_DOMAIN_SPLIT=true
   PUBLIC_DOMAIN=your-rhu.org
   MANAGEMENT_DOMAIN=management.your-rhu.org
   PORTAL_SURFACE=public
   PORTAL_HTTPS_ONLY=true
   SESSION_DOMAIN=null
   SESSION_SECURE_COOKIE=true
   ```

4. Configure management instance with the same two domain values, but `APP_URL=https://management.your-rhu.org` and `PORTAL_SURFACE=management`. Generate its own APP_KEY. Never copy a public instance's secret key into management.
5. Use a dedicated **read-only database account** for the public instance. Prefer grants only on the medicine/usable-stock/facility data it reads, or a dedicated read replica/public projection. Do not grant it access to patient, clinical or staff tables. Public sessions/cache must use their own writable file/Redis storage instead of a read-only database connection. Management uses a separate database account with the permissions it needs. Database grants must be configured by the deployment administrator; the application does not apply them.
6. Configure each web-server virtual host with its exact `ServerName` and its own instance's **`public` directory** as DocumentRoot. Install TLS certificates covering both names and redirect port 80 to HTTPS. Reject other Host values at the server. Separate subdomains alone are not complete security isolation; independent processes, credentials and infrastructure provide that boundary.
7. If TLS terminates at a trusted proxy, configure Laravel to trust only that deployment's proxy addresses so it correctly recognizes HTTPS. Do not blindly trust forwarded headers from the internet. Ensure the proxy preserves the intended Host.
8. Build assets in each release, apply reviewed database migrations through management only, and run `php artisan config:cache` and `php artisan route:cache` for each instance using its own environment. Do not run unrestricted migrations against the legacy imported database; follow the explicit upgrade guidance in README.md. No sample seeders in production.
9. Run the scheduler/queues only on the management deployment. Restrict the database port to application infrastructure; do not expose it publicly.

Use one shared business database initially so public availability reflects management updates, with distinct credentials and limited public read permissions. Separate business databases require an explicit synchronization design; they are not automatically synchronized by the domain split.

## Verify before publishing

| Request | Expected result |
| --- | --- |
| Public `/` | Medicine search, without a management-choice landing |
| Public `/login`, `/dashboard`, `/inventory` | 404 |
| Public POST `/login` or `/inventory/store` | 404, no business writes |
| Management `/` without login | Management `/login` redirect |
| Management `/inventory` without login | Management login redirect |
| Management login as midwife | Midwife dashboard, existing permissions enforced |
| Wrong hostname sent to either dedicated instance | 404 |
| HTTPS session cookie | Correct portal-specific `__Host-` name, Secure, HttpOnly, path `/`, no Domain |

Use separate browser profiles/private windows to check public and management behavior. Authorization must hold even when URLs and form requests are entered manually. Tests in `PortalDomainSplitTest` exercise these boundaries; the older regression suite uses explicit single-host testing mode.

Laravel references: [domain routing](https://laravel.com/docs/12.x/routing#subdomain-routing), [deployment](https://laravel.com/docs/12.x/deployment).
