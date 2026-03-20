# Architecture: statsdata

## Purpose

A PrestaShop statistics module providing raw data export and overview tables for orders, customers, and products within a selected date range — useful as a data-quality baseline.

## Directory Structure

```
statsdata.php   - Module class; all business logic
upgrade/        - Migration scripts
tests/          - PHPUnit test stubs and PHPStan bootstrap
translations/   - Locale string overrides
```

## Key Design Decisions

- **Raw export focus**: Prioritises presenting tabular data rather than aggregated charts.
- **Single-file module**: Follows PrestaShop module conventions.

## Extension Points

- Override `hookDisplayAdminStatsModules()` to add additional data tables or export formats.

## Dependency Flow

```
statsdata (Module)
  └─> hookDisplayAdminStatsModules() — renders data tables
        └─> Db::getInstance()
```
