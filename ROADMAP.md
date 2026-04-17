# VA AA Districts Map Plugin — Roadmap

## Investigate

- [x] ~~Figure out what happened to District 44 (Richmond West)~~ — D44 combined into D18 per DCM D18 (Tibby H., 2026-04-12). Merge handled by `DISTRICT_MERGES` in `scripts/generate-boundaries.py`.

- [ ] find district 30, Fredricksburg


## Features

- [ ] **Add to Calendar links for district meetings**
  Parse meeting schedule strings (e.g. "2nd Monday at 7:00 PM") into structured data and generate ICS downloads with RRULE for recurring events. Roughly 35 districts have parseable schedules; edge cases like "Quarterly" or "month varies" would skip the button. Render a small "Add to Calendar" button in the detail panel.

- [ ] **Link meeting locations to Google Maps**
  Make the location address in the detail panel a clickable link that opens Google Maps directions (e.g. `https://www.google.com/maps/search/?api=1&query=...`). Should work for all districts that have a physical location.

- [ ] **Integrate with the 12 Step Meeting List plugin**
  The site already uses the [12 Step Meeting List](https://wordpress.org/plugins/12-step-meeting-list/) plugin which powers the meetings API at `wp-admin/admin-ajax.php?action=meetings`. Explore deeper integration: clicking a district on the map could link to the filtered meeting list page (`/meetings/?search="District XX"`), or embed a mini meeting list in the detail panel.

- [ ] **Redraw district boundaries from meetings data**
  Add an admin tool (or enhance the sync feature) that regenerates the Voronoi district boundary polygons from the live meetings API data. Currently the boundaries are pre-computed in `district-boundaries.json` — this would let admins refresh them when meetings are added/moved without needing to run the Python script manually.

- [x] ~~remove or fix visual admin tool~~ — removed in v2.0.0, replaced by wp-admin district editor
