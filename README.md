# Virginia AA Districts Map

Interactive SVG map of Virginia Area 71 AA districts for WordPress. District boundaries are derived from 2,600+ meeting locations using Voronoi diagrams.

## Installation

Download the latest release ZIP from [Releases](https://github.com/sblack4/va-aa-districts-map/releases) and install via **wp-admin > Plugins > Add New > Upload Plugin**.

Or install manually:
```sh
cp -r va-aa-districts-map/ /path/to/wp-content/plugins/
```

Then activate in wp-admin and add the shortcode to any page:
```
[va_aa_map]
[va_aa_map height="800px"]
```

## Local Development

Requires [Docker](https://www.docker.com/).

```sh
# First time — sets up WordPress, activates plugin, creates test page:
./scripts/setup-local.sh

# After that, just start/stop:
docker compose up -d
docker compose down        # stop
docker compose down -v     # stop and delete all data
```

| | URL |
|---|---|
| Map page | http://localhost:8888/?page_id=4 |
| WP Admin | http://localhost:8888/wp-admin/ |
| Plugin admin | http://localhost:8888/wp-admin/admin.php?page=vaaa-districts |

Login: `admin` / `admin`

Plugin files are mounted directly — edits to `va-aa-districts-map/` are reflected immediately.

## Regenerating District Boundaries

The district boundaries are pre-computed from meeting location data. To regenerate them:

```sh
pip install shapely
python scripts/generate-boundaries.py --fetch
```

This fetches live meeting data from aavirginia.org, filters outliers, computes Voronoi boundaries, and updates `district-boundaries.json` and `default-data.json`.

See [HOW-WE-BUILT-THIS.md](HOW-WE-BUILT-THIS.md) for the full methodology.

## Releasing

Push a version tag and GitHub Actions will build the ZIP and create a release:

```sh
# 1. Bump version in va-aa-districts-map.php (both the header and VAAA_VERSION constant)
# 2. Commit and tag
git tag v2.1.0
git push --tags
```

WordPress sites with the plugin installed will see the update notification automatically.

## Project Structure

```
va-aa-districts-map/          # WordPress plugin (what gets installed)
  va-aa-districts-map.php     # Main plugin file
  assets/css/                 # Frontend + admin styles
  assets/js/                  # Frontend map JS, admin JS, district boundaries
  includes/default-data.json  # Default district data (seeded on activation)
  uninstall.php               # Cleanup on plugin deletion
  readme.txt                  # WordPress plugin readme
scripts/                      # Development scripts (not included in plugin ZIP)
  generate-boundaries.py      # Regenerate Voronoi boundaries from meeting data
  build-zip.sh                # Build distribution ZIP
  setup-local.sh              # Set up local WordPress dev environment
docker-compose.yml            # Local development environment
```
