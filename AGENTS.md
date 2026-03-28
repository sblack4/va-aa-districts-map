# VA AA Districts Map

WordPress plugin. Shortcode `[va_aa_map]` renders an interactive district map.

## Versioning

Semver. Bump in two places in `va-aa-districts-map/va-aa-districts-map.php`:
- Plugin header comment (`Version: X.Y.Z`)
- `VAAA_VERSION` constant

## Release flow

1. `python scripts/generate-boundaries.py --fetch` (if boundary data changed)
2. Bump version
3. Commit, tag (`vX.Y.Z`), push with `--tags`
4. Plugin auto-updates via WordPress admin UI (GitHub-based updater in plugin)

## Deploy

```
scripts/deploy.sh staging        # WP Engine staging
scripts/deploy.sh prod           # WP Engine production (confirms first)
scripts/deploy.sh staging --reseed  # deploy + reset data to defaults
```

## Key files

- `scripts/generate-boundaries.py` — generates Voronoi boundaries from meeting API data
- `va-aa-districts-map/includes/default-data.json` — district metadata + FIPS mapping
- `va-aa-districts-map/assets/js/district-boundaries.json` — generated boundary polygons
- `va-aa-districts-map/va-aa-districts-map.php` — main plugin file
