#!/usr/bin/env python3
"""
Generate Voronoi district boundary polygons from aavirginia.org meeting data.

Fetches live meeting data from the meetings API, groups by district,
filters mistagged outliers, computes Voronoi diagrams, clips to Virginia's
state boundary, and writes district-boundaries.json + updated default-data.json.

Requirements:
    pip install shapely

Usage:
    python generate-boundaries.py

    # Or fetch fresh meeting data (default uses cached /tmp/vac-meetings.json):
    python generate-boundaries.py --fetch
"""

import json
import os
import sys
import urllib.request
from collections import defaultdict
from pathlib import Path

from shapely.geometry import MultiPoint, Point, mapping, shape
from shapely.ops import unary_union, voronoi_diagram
from shapely import make_valid

# ── Paths ──
SCRIPT_DIR = Path(__file__).parent
PLUGIN_DIR = SCRIPT_DIR.parent / "va-aa-districts-map"
BOUNDARIES_OUT = PLUGIN_DIR / "assets" / "js" / "district-boundaries.json"
DEFAULT_DATA = PLUGIN_DIR / "includes" / "default-data.json"
MEETINGS_CACHE = Path("/tmp/vac-meetings.json")

# ── Config ──
MEETINGS_API = "https://aavirginia.org/wp-admin/admin-ajax.php?action=meetings"
TOPOJSON_URL = "https://cdn.jsdelivr.net/npm/us-atlas@3/counties-10m.json"
EXCLUDE_DISTRICTS = {48}  # Statewide (Spanish Speaking) — not geographic


def fetch_meetings(force=False):
    """Fetch meetings from API or use cached copy."""
    if not force and MEETINGS_CACHE.exists():
        print(f"Using cached meetings from {MEETINGS_CACHE}")
        with open(MEETINGS_CACHE) as f:
            return json.load(f)

    print(f"Fetching meetings from {MEETINGS_API}...")
    with urllib.request.urlopen(MEETINGS_API) as r:
        data = json.loads(r.read())
    with open(MEETINGS_CACHE, "w") as f:
        json.dump(data, f)
    print(f"  Cached {len(data)} meetings to {MEETINGS_CACHE}")
    return data


def load_virginia_boundary():
    """Load Virginia state boundary and county polygons from TopoJSON."""
    print(f"Loading TopoJSON from {TOPOJSON_URL}...")
    with urllib.request.urlopen(TOPOJSON_URL) as r:
        topo = json.loads(r.read())

    decoded_arcs = decode_arcs(topo["arcs"], topo["transform"])

    # State boundary
    va_boundary = None
    for geom in topo["objects"]["states"]["geometries"]:
        if geom["id"] == "51":
            va_boundary = make_valid(topo_to_shape(geom, decoded_arcs))
    assert va_boundary is not None, "Virginia state boundary not found"

    # County polygons
    va_counties = {}
    for geom in topo["objects"]["counties"]["geometries"]:
        fips = geom["id"]
        if not fips.startswith("51"):
            continue
        try:
            poly = make_valid(topo_to_shape(geom, decoded_arcs))
            if poly.is_valid:
                va_counties[fips] = poly
        except Exception:
            pass

    print(f"  State boundary: {va_boundary.geom_type}")
    print(f"  Counties loaded: {len(va_counties)}")
    return va_boundary, va_counties


def decode_arcs(arcs, transform):
    scale = transform["scale"]
    translate = transform["translate"]
    decoded = []
    for arc in arcs:
        coords = []
        x, y = 0, 0
        for dx, dy in arc:
            x += dx
            y += dy
            coords.append([x * scale[0] + translate[0], y * scale[1] + translate[1]])
        decoded.append(coords)
    return decoded


def arc_coords(arc_indices, decoded_arcs):
    coords = []
    for idx in arc_indices:
        arc = decoded_arcs[idx] if idx >= 0 else list(reversed(decoded_arcs[~idx]))
        coords.extend(arc[1:] if coords else arc)
    return coords


def topo_to_shape(geom, decoded_arcs):
    if geom["type"] == "Polygon":
        rings = [arc_coords(ring, decoded_arcs) for ring in geom["arcs"]]
        return shape({"type": "Polygon", "coordinates": rings})
    elif geom["type"] == "MultiPolygon":
        polys = [
            [arc_coords(ring, decoded_arcs) for ring in polygon]
            for polygon in geom["arcs"]
        ]
        return shape({"type": "MultiPolygon", "coordinates": polys})
    raise ValueError(f"Unsupported geometry type: {geom['type']}")


def parse_district_number(name):
    """Extract district number from string like 'District 21 - Springfield'."""
    if not name:
        return None
    parts = name.split(" - ")[0].replace("District ", "").strip()
    try:
        return int(parts)
    except ValueError:
        return None


def group_meetings_by_district(meetings, va_boundary):
    """Group meeting points by district, excluding non-geographic districts."""
    raw_points = defaultdict(list)
    for m in meetings:
        dnum = parse_district_number(m.get("district"))
        lat, lng = m.get("latitude"), m.get("longitude")
        if dnum and dnum not in EXCLUDE_DISTRICTS and lat and lng:
            p = Point(float(lng), float(lat))
            if va_boundary.distance(p) < 0.5:
                raw_points[dnum].append((p, m.get("name", "")))
    return raw_points


def filter_outliers(raw_points):
    """Remove meetings that are clearly mistagged (far from own district, close to another)."""
    # Compute centroids
    centroids = {}
    for dnum, pts in raw_points.items():
        cx = sum(p.x for p, _ in pts) / len(pts)
        cy = sum(p.y for p, _ in pts) / len(pts)
        centroids[dnum] = Point(cx, cy)

    filtered = defaultdict(list)
    removed = 0
    for dnum, pts in raw_points.items():
        own_centroid = centroids[dnum]
        for p, name in pts:
            own_dist = own_centroid.distance(p)
            # Check if 3x closer to another district's centroid
            closer_to = None
            for other_dnum, other_c in centroids.items():
                if other_dnum == dnum:
                    continue
                if other_c.distance(p) < own_dist * 0.3:
                    closer_to = other_dnum
                    break
            if closer_to and own_dist > 0.5:  # >~55km from own centroid
                removed += 1
                print(f"  Outlier: '{name}' from d{dnum} (closer to d{closer_to})")
            else:
                filtered[dnum].append(p)

    print(f"  Outliers removed: {removed}")
    return filtered


def generate_voronoi_boundaries(dist_points, va_boundary):
    """Generate Voronoi district boundary polygons."""
    # Deduplicate nearby points
    unique_points = []
    unique_districts = []
    seen = set()
    for d in sorted(dist_points.keys()):
        for p in dist_points[d]:
            key = (round(p.x, 4), round(p.y, 4))
            if key not in seen:
                seen.add(key)
                unique_points.append(p)
                unique_districts.append(d)

    print(f"  Unique points: {len(unique_points)}")

    # Voronoi
    mp = MultiPoint(unique_points)
    voronoi_result = voronoi_diagram(mp, envelope=va_boundary.buffer(0.5))
    print(f"  Voronoi cells: {len(voronoi_result.geoms)}")

    # Assign cells to districts
    cell_districts = {}
    for cell in voronoi_result.geoms:
        best_d = None
        for p, d in zip(unique_points, unique_districts):
            if cell.contains(p):
                best_d = d
                break
        if not best_d:
            centroid = cell.representative_point()
            min_dist = float("inf")
            for p, d in zip(unique_points, unique_districts):
                dist = centroid.distance(p)
                if dist < min_dist:
                    min_dist = dist
                    best_d = d
        if best_d:
            cell_districts.setdefault(best_d, []).append(cell)

    # Merge and clip
    district_polygons = {}
    for d in sorted(cell_districts.keys()):
        merged = unary_union(cell_districts[d])
        clipped = make_valid(merged.intersection(va_boundary))
        if not clipped.is_empty:
            district_polygons[d] = clipped

    print(f"  District polygons: {len(district_polygons)}")
    return district_polygons


def update_fips_mapping(meetings, va_counties, dist_points):
    """Update FIPS-to-district mapping based on meeting locations."""
    meeting_to_county = defaultdict(lambda: defaultdict(int))

    for m in meetings:
        dnum = parse_district_number(m.get("district"))
        lat, lng = m.get("latitude"), m.get("longitude")
        if not (lat and lng and dnum and dnum not in EXCLUDE_DISTRICTS):
            continue
        point = Point(float(lng), float(lat))
        for fips, poly in va_counties.items():
            if poly.contains(point):
                meeting_to_county[fips][dnum] += 1
                break

    # Primary: district with most meetings per county
    new_fips = {}
    new_shared = {}
    for fips in sorted(meeting_to_county.keys()):
        dists = meeting_to_county[fips]
        sorted_dists = sorted(dists.items(), key=lambda x: -x[1])
        new_fips[fips] = sorted_dists[0][0]
        if len(sorted_dists) > 1:
            total = sum(c for _, c in sorted_dists)
            significant = [d for d, c in sorted_dists if c >= max(3, total * 0.1)]
            if len(significant) > 1:
                new_shared[fips] = significant

    # Convert shared to district->fips format
    district_shared = {}
    for fips, dists in new_shared.items():
        for d in dists:
            d_str = str(d)
            if d_str not in district_shared:
                district_shared[d_str] = []
            if fips not in district_shared[d_str]:
                district_shared[d_str].append(fips)

    # Remove primary from shared
    for d_str, fips_list in list(district_shared.items()):
        filtered = [f for f in fips_list if str(new_fips.get(f, "")) != d_str]
        if filtered:
            district_shared[d_str] = filtered
        else:
            del district_shared[d_str]

    return new_fips, district_shared


def write_boundaries(district_polygons, outpath):
    """Write district boundaries as GeoJSON."""
    features = []
    for d in sorted(district_polygons.keys()):
        features.append(
            {
                "type": "Feature",
                "properties": {"district": d},
                "geometry": mapping(district_polygons[d]),
            }
        )
    geojson = {"type": "FeatureCollection", "features": features}
    with open(outpath, "w") as f:
        json.dump(geojson, f, separators=(",", ":"))
    size = os.path.getsize(outpath)
    print(f"  Wrote {len(features)} polygons to {outpath} ({size:,} bytes)")


def update_default_data(new_fips, district_shared):
    """Update default-data.json with new FIPS mapping and shared counties."""
    with open(DEFAULT_DATA) as f:
        data = json.load(f)

    # Merge: keep existing FIPS entries for counties without meetings
    for fips in data["fipsToDistrict"]:
        if fips not in new_fips:
            new_fips[fips] = data["fipsToDistrict"][fips]

    data["fipsToDistrict"] = new_fips
    data["sharedCountyDistricts"] = district_shared

    with open(DEFAULT_DATA, "w") as f:
        json.dump(data, f, indent=2)
    print(f"  Updated {DEFAULT_DATA}")
    print(f"    FIPS entries: {len(new_fips)}")
    print(f"    Shared counties: {len(district_shared)}")


def main():
    force_fetch = "--fetch" in sys.argv

    print("=" * 60)
    print("VA AA Districts — Boundary Generator")
    print("=" * 60)

    meetings = fetch_meetings(force=force_fetch)
    print(f"  Total meetings: {len(meetings)}")

    va_boundary, va_counties = load_virginia_boundary()

    print("\nGrouping meetings by district...")
    raw_points = group_meetings_by_district(meetings, va_boundary)
    print(f"  Districts: {len(raw_points)}")
    total_pts = sum(len(pts) for pts in raw_points.values())
    print(f"  Meeting points: {total_pts}")

    print("\nFiltering outliers...")
    dist_points = filter_outliers(raw_points)

    print("\nGenerating Voronoi boundaries...")
    district_polygons = generate_voronoi_boundaries(dist_points, va_boundary)

    print("\nWriting boundary GeoJSON...")
    write_boundaries(district_polygons, BOUNDARIES_OUT)

    print("\nUpdating FIPS mapping...")
    new_fips, district_shared = update_fips_mapping(meetings, va_counties, dist_points)
    update_default_data(new_fips, district_shared)

    print("\nDone!")


if __name__ == "__main__":
    main()
