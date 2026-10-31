# RHU inventory diagrams

These are editable native draw.io files generated from this application's implementation and the local database schema on 31 October 2026. They are not screenshots.

Public medicine search and private management now use separate hostnames. The overall flow identifies the public domain and management subdomain; deployment details are in `../DOMAIN_SPLIT.md`.

| File | Contents |
| --- | --- |
| `overall-system-flowchart.drawio` | One overall system flow: public access, login, role dashboards, permitted actions, validation, record processing / analysis, results and logout |
| `overall-use-case-diagram.drawio` | One overall use case diagram: five actors, system boundary, and 13 main use cases |
| `system-use-cases.drawio` | Five pages: public visitor, PHO administrator, RHU administrator, RHU staff, and midwife |
| `database-erd.drawio` | Seven module pages covering every table in the live schema snapshot; shared tables repeat for context |
| `receiving-flowchart.drawio` | RHU stock intake, with a note explaining the separate PHO shipment path |
| `dispensing-flowchart.drawio` | Validation, FEFO, transactional deductions, dispensing records, per-batch logs and movements |
| `transfer-flowchart.drawio` | Pending request, source acceptance, reservation, receipt, cancellation, and repeat-receipt protection |
| `forecasting-flowchart.drawio` | Medicine usage history, OLS/average fallback, projections, coverage, shortages and reorder quantities |
| `disease-analysis-flowchart.drawio` | Reporting completeness, OLS case trends, Holt forecasting and holdout evaluation |

## Open and edit

For a single overall flowchart of the system, open **`overall-system-flowchart.drawio`** first. The receiving, dispensing, transfer and forecasting files provide optional process-level detail.

1. Open https://app.diagrams.net/ in your browser.
2. Choose **Open Existing Diagram**, or **File → Open From → Device** if already in the editor.
3. Select any `.drawio` file in this folder. You can also drag the file onto the editor.
4. Use the page tabs at the bottom for the multi-page ERD and use case diagrams.
5. Edit labels or move shapes as needed; save the original as `.drawio`.
6. Use **File → Export as → PNG** or **PDF** for your report. Check page size and text readability before submission.

## Reading the ERD

- Solid relationships are foreign keys actually enforced by the local database.
- Dashed relationships are backed by an Eloquent `belongsTo` relationship or an explicit service/query reference, but have no enforced FK in this schema.
- The parent endpoint is `1`, or `0..1` for a nullable child reference. The child endpoint is `0..many` unless the column has a single-column unique/primary key.
- `PK` means primary key, `UK` a schema unique key, `FK` a referenced column, and `?` a nullable column. Dashed FK labels indicate application references, not database constraints.
- All columns are included. Long SQL type definitions such as enum lists are abbreviated in the drawing; exact types are in `schema-metadata.json`.
- Module pages repeat shared entities. Relationships to entities absent from a page are omitted on that page; consult the other modules.
- Stock movements use a polymorphic `owner_type` / `owner_id` pair. It is deliberately not drawn as an enforced FK to one owner table.
- Dispensing stores `patient_name`; the diagram does not invent a patient ID relationship to `public_users`.
- `midwife_barangay` currently has only `id` and timestamps in the live schema, although application models expect assignment fields. Those missing fields are not fabricated in this diagram.
- The `supply_requests` table exists, while the current SupplyRequestController uses `allocations` for its request workflow. Schema existence does not establish that a table drives the active workflow.

## Scope and sources

Use cases summarize representative supported actions rather than every route. Permissions also depend on assigned facility, patient assignments, ownership, and controller guards. They are not a substitute for a complete authorization audit. The transfer chart describes the shared transactional service; PHO direct allocations and midwife supply allocations have separate workflows.

The medicine model uses rolling seven-day periods ending yesterday, while disease reporting uses ISO weeks or calendar months. The charts distinguish those conventions. Default model settings are shown; changing configuration requires updating the diagrams.

Sources: `routes/web.php`, `routes/console.php`, application controllers and services, Eloquent model relationships, and the live MySQL/MariaDB schema metadata. `schema-metadata.json` contains table/column/key definitions only, with no database rows, credentials, or patient information.

To regenerate the drawings from the saved metadata:

```powershell
python tools/generate-diagrams.py
```

This regenerates the `.drawio` files and checks XML cell IDs and edge references. Manual diagram edits will be replaced by regeneration. The saved metadata is a snapshot; schema changes require a fresh metadata export and review.
