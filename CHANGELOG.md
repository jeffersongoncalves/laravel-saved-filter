# Changelog

All notable changes to this project will be documented in this file.

## 1.2.0 - 2026-10-17

Migrations now ship as publishable stubs, like the other packages: `create_saved_filters_table` and `add_pagination_and_grouping_to_saved_filters_table`, declared in order with `hasMigrations()`. They are no longer loaded from the package.

New installs:

```bash
php artisan vendor:publish --tag="saved-filter-migrations"
php artisan migrate

```
Upgrading from 1.0/1.1 (which ran the migrations from the package): nothing to do. Publishing and migrating is safe too — the stubs skip a table or columns that already exist, so a 1.0 install gets the pagination/grouping columns and a 1.1 install is left unchanged.

## 1.1.0 - 2026-10-17

- Saved filters also store the table's records per page (`records_per_page`) and grouping (`grouping`, `grouping_direction`), included in export/import.

Upgrading from 1.0: run `php artisan migrate` — a new migration adds the three nullable columns.

## 1.0.0 - 2026-10-17

First release: saved table filters (filters, sort, search, toggled columns) per owner, tenant and resource.

- `SavedFilter` model with polymorphic owner (int/UUID/ULID keys; null = preset), string `tenant_id`, default/public/locked flags and ordering
- Scopes: `forResource`, `forTenant`, `ownedBy`, `visibleTo`, `presets`, `ordered`; one default per owner, tenant and resource
- `saved-filter:export` / `saved-filter:import` (JSON presets between environments) and `saved-filter:prune` (deleted owners, missing resource classes, `--resource`, `--dry-run`)
- Events: `SavedFilterCreated`, `SavedFilterDeleted`, `SavedFilterApplied`
- Laravel 12/13, PHP 8.2+

## [Unreleased]
