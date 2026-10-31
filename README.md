# RHU Inventory System

**Manage medicine supplies, monitor stock movements, and plan for future demand.**

RHU Inventory System is a web application for Provincial Health Office (PHO) and Rural Health Unit (RHU) operations. It brings batch inventory, dispensing, supply allocations, transfers, patient services, disease reporting, and statistical forecasting into one system, with separate public and management access.

## The problem it solves

Keeping an inventory count does not always show which batches should be used first, where supplies are needed, or how long available stock may last. Receipts, dispensing, allocations, and transfers must remain consistent so that one transaction does not produce duplicate credits, unexplained deductions, or incorrect balances.

Health personnel also need to coordinate patient services, immunization, maternal care, and disease reports. Incomplete reporting can distort trends, while mixing different reporting periods can make comparisons misleading.

RHU Inventory System connects these records to practical decisions: identify stock priorities, track each movement, coordinate supplies, review reporting completeness, and estimate future demand from recorded usage.

## How the system helps

| Health service need | System support |
| --- | --- |
| Check medicine availability before visiting | Public medicine search with RHU selection and stock availability, without a staff account. |
| Keep batch inventory organized | Record medicines, quantities, batches, expiry dates, packaging, and storage locations. |
| Use stock before it expires | Dispense from unexpired batches using **First Expiry, First Out (FEFO)**. |
| Explain changes in stock balances | Maintain stock logs and exact batch movements, with transactional reversals for supported edits and deletions. |
| Coordinate supplies between facilities | Reserve accepted transfers, record receipt once, and restore reservations when cancelled. |
| Support RHU and midwife supply workflows | Record allocations, supply requests, review outcomes, and recipient confirmation where applicable. |
| Coordinate patient services | Maintain patient registration, immunization, prenatal, postpartum, and family-planning records within permitted access. |
| Identify stock requiring attention | Review low-stock, expiry, activity, and forecasting information on role-specific dashboards. |
| Estimate future medicine demand | Analyze recorded medicine releases and calculate projected need, stock coverage, shortages, and reorder points. |
| Understand disease case trends | Separate weekly and monthly reports, review completeness, and display OLS trends and Holt case projections. |
| Prepare operational reports | View and export permitted inventory, movement, clinical, and service reports. |
| Separate citizens from management | Serve public search and private management on distinct configurable hostnames with separate session cookies. |

## Who it is for

The system supports public visitors and authorized health personnel, including PHO and RHU operations in Antique.

| User | Main purpose |
| --- | --- |
| **Public visitor** | Search medicines and vaccines and check availability at a selected RHU. No staff login is required. |
| **PHO administrator** | Monitor provincial operations, receive DOH supplies, coordinate distribution, and manage permitted reference records and accounts. |
| **RHU administrator** | Manage facility inventory, service operations, supply workflows, and permitted administrative functions. |
| **RHU staff** | Record stock intake and dispensing, support RHU services, and review permitted reports and forecasts. |
| **Midwife** | Access assigned patients, record supported clinical services, dispense within permitted scope, and manage own supply requests and allocation receipts. |

Permissions depend on role, assigned facility, patient assignments, and record ownership. Public access does not grant inventory-editing or patient-record access. The public RHU map has been removed; administrative GIS remains protected.

## How it works

Public visitors open the public domain, search for a medicine or vaccine, select an RHU, and review availability before visiting.

For authorized health personnel:

1. Sign in through the management domain and open the appropriate role dashboard.
2. Receive supplies and record the correct medicine, facility, batch, expiry, and quantity.
3. Dispense medicines or process permitted allocations, with stock deductions recorded against the actual batches used.
4. Coordinate transfers through request, acceptance, reservation, receipt, or cancellation.
5. Record permitted patient, immunization, maternal-care, and family-planning activities.
6. Submit disease totals for weekly or monthly periods and confirm completeness after each period ends.
7. Review stock priorities, medicine forecasts, disease trends, notifications, and operational reports.

The [overall flowchart](docs/diagrams/overall-system-flowchart.drawio), [use case diagram](docs/diagrams/overall-use-case-diagram.drawio), and [database ERD](docs/diagrams/database-erd.drawio) provide editable views of these workflows and relationships.

## The role of mathematical models

Medicine forecasting uses **ordinary least squares (OLS) linear regression** on recorded weekly medicine releases. The model fits `y = a + bt`, where `y` is released units and `t` is the period number. Short histories use a labeled weekly-average fallback. By default, the system reviews up to 12 completed rolling seven-day periods and projects 90 days of demand to calculate coverage, shortages, and reorder quantities.

Disease analysis uses **OLS regression** for case trends and **Holt's additive level-and-trend exponential smoothing** for case projections. Weekly and monthly reports remain separate. Missing or unconfirmed reports stay unknown, while confirmed zero-case reports remain observations. Projections require sufficient confirmed history; Holt requires consecutive recent observations.

These are statistical estimates based on recorded activity. Historical R? describes model fit, not guaranteed forecast accuracy. Disease case projections do not automatically declare outbreaks or calculate medicine quantities. See the [medicine forecasting model](docs/FORECAST_MODEL.md) and [disease analysis model](docs/DISEASE_ANALYSIS.md) for equations, validation, and limitations.

## Project status

The project includes an implemented Laravel application, public medicine search, role-specific management workflows, dashboards, stock movement tracking, clinical services, and statistical forecasting. The latest recorded development verification passed **75 tests with 1,278 assertions** on SQLite and disposable MariaDB, including competing transfer receipt checks. The production asset build and route caching also passed.

Vercel deployment configuration is included for separate public and management projects. Hosted MySQL/MariaDB, shared Redis, private S3-compatible storage, and an authenticated scheduler must be configured before going live. These external services have not yet been provisioned, and the Docker image and live Vercel deployment remain unverified in the hosting environment.

The stack uses **Laravel 12**, **PHP**, **Blade**, **Vite**, **Tailwind CSS**, **Chart.js**, and **MySQL/MariaDB**. The Vercel container configuration uses **PHP 8.4 with FrankenPHP**, with Redis for shared sessions/cache and private object storage for prescription attachments. Public and midwife interfaces support mobile layouts; management pages prioritize desktop use.

## Getting started

Follow the [installation and verification guide](docs/INSTALLATION.md) for local requirements, fresh installation, imported-database upgrades, account provisioning, and test commands.

For deployment, follow the [Vercel deployment guide](docs/VERCEL_DEPLOYMENT.md) and [domain separation guide](docs/DOMAIN_SPLIT.md). Environment examples contain placeholders; actual credentials and private records must stay out of the repository.

## Project documentation

- [Installation and verification](docs/INSTALLATION.md)
- [Medicine forecasting model](docs/FORECAST_MODEL.md)
- [Disease reporting and analysis](docs/DISEASE_ANALYSIS.md)
- [Public and management domain separation](docs/DOMAIN_SPLIT.md)
- [Vercel deployment guide](docs/VERCEL_DEPLOYMENT.md)
- [Consistent sample stock data](docs/SAMPLE_DATA.md)
- [Diagram guide](docs/diagrams/README.md)
- [Overall system flowchart](docs/diagrams/overall-system-flowchart.drawio)
- [Overall use case diagram](docs/diagrams/overall-use-case-diagram.drawio)
- [Detailed role use cases](docs/diagrams/system-use-cases.drawio)
- [Database ERD](docs/diagrams/database-erd.drawio)
- [Original code review](CODE_REVIEW.md)
- [Repair and verification notes](REPAIR_NOTES.md)
