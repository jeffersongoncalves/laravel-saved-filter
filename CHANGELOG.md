# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-10-17

First release: saved table filters (filters, sort, search, toggled columns) per owner, tenant and resource.

- `SavedFilter` model with polymorphic owner (int/UUID/ULID keys; null = preset), string `tenant_id`, default/public/locked flags and ordering
- Scopes: `forResource`, `forTenant`, `ownedBy`, `visibleTo`, `presets`, `ordered`; one default per owner, tenant and resource
- `saved-filter:export` / `saved-filter:import` (JSON presets between environments) and `saved-filter:prune` (deleted owners, missing resource classes, `--resource`, `--dry-run`)
- Events: `SavedFilterCreated`, `SavedFilterDeleted`, `SavedFilterApplied`
- Laravel 12/13, PHP 8.2+

## [Unreleased]
