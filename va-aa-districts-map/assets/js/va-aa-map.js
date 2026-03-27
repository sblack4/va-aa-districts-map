/**
 * Virginia AA Districts — Interactive Map (WordPress version)
 * All selectors scoped to .vaaa-map-wrap to avoid theme collisions.
 * Uses Voronoi-based district boundaries derived from meeting locations.
 */
(function () {
  'use strict';

  // ── Use data passed from WP via wp_localize_script, fallback to globals ──
  var districts, fipsMap, sharedMap, boundariesUrl;
  if (typeof VAAA_DATA !== 'undefined') {
    districts = VAAA_DATA.districts || [];
    fipsMap   = VAAA_DATA.fipsToDistrict || {};
    sharedMap = VAAA_DATA.sharedCountyDistricts || {};
    boundariesUrl = VAAA_DATA.boundariesUrl || '';
    Object.keys(fipsMap).forEach(function(k) { fipsMap[k] = +fipsMap[k]; });
    Object.keys(sharedMap).forEach(function(k) {
      if (!Array.isArray(sharedMap[k])) sharedMap[k] = [];
    });
  } else {
    districts = (typeof VA_DISTRICTS !== 'undefined') ? VA_DISTRICTS : [];
    fipsMap   = (typeof FIPS_TO_DISTRICT !== 'undefined') ? FIPS_TO_DISTRICT : {};
    sharedMap = (typeof SHARED_COUNTY_DISTRICTS !== 'undefined') ? SHARED_COUNTY_DISTRICTS : {};
    boundariesUrl = '';
  }

  // ── Find container ──
  var wrap = document.querySelector('.vaaa-map-wrap');
  if (!wrap) return;

  var svgContainer  = wrap.querySelector('.vaaa-svg-container');
  var tooltip       = wrap.querySelector('.vaaa-tooltip');
  var panelDefault  = wrap.querySelector('.vaaa-panel-default');
  var panelDetail   = wrap.querySelector('.vaaa-panel-detail');
  var detailContent = wrap.querySelector('.vaaa-detail-content');
  var districtList  = wrap.querySelector('.vaaa-district-list');
  var searchInput   = wrap.querySelector('.vaaa-search-input');
  var backBtn       = wrap.querySelector('.vaaa-back-btn');

  // ── State ──
  var activeDistrictId = null;
  var mapSvg, mapG, zoom, counties, path;
  var districtBoundaries = null; // GeoJSON feature collection

  // ── Color palette ──
  var PALETTE = [
    "#C8DAE8","#D4E4CC","#DDD4C8","#C8D8DD","#D8CCE4",
    "#E4D4CC","#CCE4D8","#D8D8CC","#C8D4E4","#DCC8D8",
    "#C8E4D4","#E4DCC8","#D4C8E4","#CCE4E4","#E4C8D4",
    "#D4DCC8","#C8E4E4","#DCD4CC","#CCd4E4","#E4CCCC"
  ];
  function distColor(id) { return id ? PALETTE[id % PALETTE.length] : '#e8e6e0'; }
  function hoverColor() { return '#b3d1e5'; }
  function activeColor() { return '#1a5276'; }

  // ── Helpers ──
  function getCountyFipsForDistrict(distId) {
    var direct = [];
    for (var fips in fipsMap) {
      if (fipsMap[fips] === distId) direct.push(fips);
    }
    var shared = (sharedMap[distId]) || [];
    var merged = direct.concat(shared);
    return merged.filter(function(v, i) { return merged.indexOf(v) === i; });
  }

  // ── Build district list ──
  function buildDistrictList() {
    var sorted = districts.slice().sort(function(a,b) { return a.id - b.id; });
    sorted.forEach(function(dist) {
      var li = document.createElement('li');
      li.className = 'vaaa-district-list-item' + (dist.inactive ? ' vaaa-inactive' : '');
      li.dataset.district = dist.id;
      li.innerHTML =
        '<span class="vaaa-dist-num">' + dist.id + '</span>' +
        '<span class="vaaa-dist-name">' + dist.name + '</span>' +
        (dist.inactive ? '<span class="vaaa-dist-status">Inactive</span>' : '');
      li.addEventListener('click', function() { selectDistrict(dist.id); });
      districtList.appendChild(li);
    });
  }

  // ── Search ──
  function initSearch() {
    searchInput.addEventListener('input', function(e) {
      var q = e.target.value.toLowerCase().trim();
      districtList.querySelectorAll('.vaaa-district-list-item').forEach(function(item) {
        var name = item.querySelector('.vaaa-dist-name').textContent.toLowerCase();
        var num = item.querySelector('.vaaa-dist-num').textContent;
        item.style.display = (!q || name.indexOf(q) > -1 || num.indexOf(q) > -1) ? '' : 'none';
      });
    });
  }

  // ── Map init ──
  async function initMap() {
    var width = svgContainer.clientWidth || 900;
    var height = svgContainer.clientHeight || 500;

    // Load county data and district boundaries in parallel
    var loadPromises = [
      d3.json('https://cdn.jsdelivr.net/npm/us-atlas@3/counties-10m.json')
    ];
    if (boundariesUrl) {
      loadPromises.push(d3.json(boundariesUrl));
    }
    var results = await Promise.all(loadPromises);
    var us = results[0];
    districtBoundaries = results[1] || null;

    var vaCounties = {
      type: "GeometryCollection",
      geometries: us.objects.counties.geometries.filter(function(d) { return d.id.startsWith('51'); })
    };
    var vaState = {
      type: "GeometryCollection",
      geometries: us.objects.states.geometries.filter(function(d) { return d.id === '51'; })
    };

    counties = topojson.feature(us, vaCounties);
    var stateBorder = topojson.feature(us, vaState);

    var projection = d3.geoMercator().fitSize([width - 40, height - 40], counties);
    projection.translate([projection.translate()[0] + 20, projection.translate()[1] + 20]);
    path = d3.geoPath().projection(projection);

    mapSvg = d3.select(svgContainer).append('svg')
      .attr('viewBox', '0 0 ' + width + ' ' + height)
      .attr('preserveAspectRatio', 'xMidYMid meet')
      .style('width', '100%').style('height', '100%');

    zoom = d3.zoom().scaleExtent([1, 8]).on('zoom', function(event) {
      mapG.attr('transform', event.transform);
      var k = event.transform.k;
      // Counter-scale labels to stay constant screen size
      var fontSize = (11 / k) + 'px';
      var strokeW = (3 / k) + 'px';
      mapG.selectAll('.vaaa-label')
        .style('font-size', fontSize)
        .style('stroke-width', strokeW);
      updateLabelVisibility(k);
    });
    mapSvg.call(zoom);
    mapG = mapSvg.append('g');

    // Layer 1: Neutral county base (shape only, no per-county coloring)
    mapG.selectAll('.vaaa-county')
      .data(counties.features).enter().append('path')
      .attr('class', 'vaaa-county')
      .attr('d', path)
      .attr('data-fips', function(d) { return d.id; })
      .attr('data-district', function(d) { return fipsMap[d.id] || 0; })
      .style('fill', '#f5f4f0')
      .style('stroke', 'none')
      .style('cursor', 'pointer')
      .on('mouseenter', onCountyHover).on('mousemove', onMove)
      .on('mouseleave', onCountyLeave).on('click', onCountyClick);

    // Layer 2: District boundaries (Voronoi-derived, primary visual)
    if (districtBoundaries && districtBoundaries.features) {
      mapG.selectAll('.vaaa-district-boundary')
        .data(districtBoundaries.features).enter().append('path')
        .attr('class', 'vaaa-district-boundary')
        .attr('d', path)
        .attr('data-district', function(d) { return d.properties.district; })
        .style('fill', function(d) { return distColor(d.properties.district); })
        .style('fill-opacity', 0.45)
        .style('stroke', '#fff').style('stroke-width', 1)
        .style('cursor', 'pointer')
        .on('mouseenter', onBoundaryHover).on('mousemove', onMove)
        .on('mouseleave', onBoundaryLeave).on('click', onBoundaryClick);
    }

    // Layer 3: State border
    mapG.append('path').datum(stateBorder.features[0])
      .attr('d', path)
      .style('fill', 'none').style('stroke', '#555').style('stroke-width', 1.5)
      .style('pointer-events', 'none');

    addLabels();

    // Zoom controls
    wrap.querySelector('[data-vaaa-zoom="in"]').addEventListener('click', function() {
      mapSvg.transition().duration(300).call(zoom.scaleBy, 1.5);
    });
    wrap.querySelector('[data-vaaa-zoom="out"]').addEventListener('click', function() {
      mapSvg.transition().duration(300).call(zoom.scaleBy, 0.67);
    });
    wrap.querySelector('[data-vaaa-zoom="reset"]').addEventListener('click', function() {
      mapSvg.transition().duration(300).call(zoom.transform, d3.zoomIdentity);
    });
  }

  // ── Label data: stored for collision detection ──
  var labelData = []; // {x, y, dId, area, el}

  function addLabels() {
    labelData = [];
    var labelSource = [];

    if (districtBoundaries && districtBoundaries.features) {
      districtBoundaries.features.forEach(function(f) {
        var dId = f.properties.district;
        var c = path.centroid(f);
        var bounds = path.bounds(f);
        var area = (bounds[1][0] - bounds[0][0]) * (bounds[1][1] - bounds[0][1]);
        if (c && !isNaN(c[0])) {
          labelSource.push({ x: c[0], y: c[1], dId: dId, area: area });
        }
      });
    } else {
      // Fallback to county-based centroids
      var centroids = {};
      var areas = {};
      counties.features.forEach(function(f) {
        var dId = fipsMap[f.id];
        if (!dId) return;
        if (!centroids[dId]) { centroids[dId] = []; areas[dId] = 0; }
        var c = path.centroid(f);
        var bounds = path.bounds(f);
        areas[dId] += (bounds[1][0] - bounds[0][0]) * (bounds[1][1] - bounds[0][1]);
        if (c && !isNaN(c[0])) centroids[dId].push(c);
      });
      Object.keys(centroids).forEach(function(dId) {
        var pts = centroids[dId];
        if (!pts.length) return;
        var x = pts.reduce(function(s,c){return s+c[0];},0) / pts.length;
        var y = pts.reduce(function(s,c){return s+c[1];},0) / pts.length;
        labelSource.push({ x: x, y: y, dId: +dId, area: areas[dId] });
      });
    }

    labelSource.forEach(function(d) {
      var el = mapG.append('text').attr('class', 'vaaa-label')
        .attr('x', d.x).attr('y', d.y).attr('dy', '0.35em').text(d.dId)
        .style('font-size', '11px').style('font-weight', '700')
        .style('font-family', '-apple-system, BlinkMacSystemFont, sans-serif')
        .style('fill', '#1a1814')
        .style('stroke', '#fff').style('stroke-width', '3px')
        .style('paint-order', 'stroke').style('stroke-linejoin', 'round')
        .style('text-anchor', 'middle')
        .style('pointer-events', 'none').style('user-select', 'none');
      labelData.push({ x: d.x, y: d.y, dId: d.dId, area: d.area, el: el });
    });

    updateLabelVisibility(1);
  }

  function updateLabelVisibility(zoomScale) {
    // At each zoom level, check which labels overlap and hide colliders.
    // Larger districts (by projected area) get priority.
    var PAD = 7; // px padding around each label (accounts for white halo)
    var sorted = labelData.slice().sort(function(a, b) { return b.area - a.area; });
    var placed = []; // bounding boxes of visible labels

    sorted.forEach(function(d) {
      // Approximate label bbox: ~6px per character width, 11px height, centered
      var text = String(d.dId);
      // Labels are counter-scaled, so bbox in map coords scales inversely with zoom
      var halfW = (text.length * 4 + PAD) / zoomScale;
      var halfH = (6.5 + PAD) / zoomScale;
      var box = { x1: d.x - halfW, y1: d.y - halfH, x2: d.x + halfW, y2: d.y + halfH };

      var overlaps = placed.some(function(p) {
        return !(box.x1 > p.x2 || box.x2 < p.x1 || box.y1 > p.y2 || box.y2 < p.y1);
      });

      if (overlaps) {
        d.el.style('display', 'none');
      } else {
        d.el.style('display', null);
        placed.push(box);
      }
    });
  }

  // ── Tooltip helpers ──
  function showTooltip(distId) {
    var dist = districts.find(function(dd) { return dd.id === distId; });
    if (!dist) return;
    tooltip.innerHTML = '<span style="color:#1a5276;font-weight:700;margin-right:4px;">' + distId + '</span>' + dist.name;
    tooltip.classList.add('vaaa-visible');
  }

  // ── County events (fallback + base layer) ──
  function onCountyHover(event, d) {
    var distId = fipsMap[d.id];
    if (!distId) return;
    showTooltip(distId);
    if (distId !== activeDistrictId) highlightDistrict(distId, true);
  }
  function onCountyLeave(event, d) {
    tooltip.classList.remove('vaaa-visible');
    var distId = fipsMap[d.id];
    if (distId && distId !== activeDistrictId) highlightDistrict(distId, false);
  }
  function onCountyClick(event, d) {
    var distId = fipsMap[d.id];
    if (distId) selectDistrict(distId);
  }

  // ── Boundary events (Voronoi overlay) ──
  function onBoundaryHover(event, d) {
    var distId = d.properties.district;
    if (!distId) return;
    showTooltip(distId);
    if (distId !== activeDistrictId) highlightDistrict(distId, true);
  }
  function onBoundaryLeave(event, d) {
    tooltip.classList.remove('vaaa-visible');
    var distId = d.properties.district;
    if (distId && distId !== activeDistrictId) highlightDistrict(distId, false);
  }
  function onBoundaryClick(event, d) {
    var distId = d.properties.district;
    if (distId) selectDistrict(distId);
  }

  function onMove(event) {
    var rect = svgContainer.getBoundingClientRect();
    tooltip.style.left = (event.clientX - rect.left + 12) + 'px';
    tooltip.style.top = (event.clientY - rect.top - 10) + 'px';
  }

  function highlightDistrict(distId, isHover) {
    // Highlight boundary polygons
    mapG.selectAll('.vaaa-district-boundary').each(function() {
      var el = d3.select(this);
      var dId = +el.attr('data-district');
      if (dId === distId && dId !== activeDistrictId) {
        el.style('fill', isHover ? hoverColor() : distColor(dId));
        el.style('fill-opacity', isHover ? 0.65 : 0.45);
        el.style('stroke-width', isHover ? 1.5 : 1);
      }
    });
    // County base stays neutral — no highlight needed
  }

  // ── Selection ──
  function selectDistrict(distId) {
    clearActive();
    activeDistrictId = distId;

    // Highlight boundary polygons
    mapG.selectAll('.vaaa-district-boundary').each(function() {
      var el = d3.select(this);
      if (+el.attr('data-district') === distId) {
        el.style('fill', activeColor())
          .style('fill-opacity', 0.75)
          .style('stroke', '#fff')
          .style('stroke-width', 1.5)
          .classed('vaaa-active', true);
      }
    });

    // County base stays neutral

    districtList.querySelectorAll('.vaaa-district-list-item').forEach(function(el) {
      el.classList.toggle('vaaa-list-active', +el.dataset.district === distId);
    });

    showDetail(distId);
    zoomTo(distId);
  }

  function zoomTo(distId) {
    // Prefer boundary polygon for zoom extent
    var targetBounds = null;
    if (districtBoundaries) {
      var feat = districtBoundaries.features.find(function(f) { return f.properties.district === distId; });
      if (feat) {
        targetBounds = path.bounds(feat);
      }
    }
    if (!targetBounds) {
      var fipsList = getCountyFipsForDistrict(distId);
      var features = counties.features.filter(function(f) { return fipsList.indexOf(f.id) > -1; });
      if (!features.length) return;
      targetBounds = path.bounds({ type: "FeatureCollection", features: features });
    }

    var dx = targetBounds[1][0] - targetBounds[0][0];
    var dy = targetBounds[1][1] - targetBounds[0][1];
    var x = (targetBounds[0][0] + targetBounds[1][0]) / 2;
    var y = (targetBounds[0][1] + targetBounds[1][1]) / 2;
    var w = svgContainer.clientWidth || 900, h = svgContainer.clientHeight || 500;
    var scale = Math.min(8, 0.7 / Math.max(dx / w, dy / h));
    var translate = [w / 2 - scale * x, h / 2 - scale * y];
    mapSvg.transition().duration(500).call(
      zoom.transform, d3.zoomIdentity.translate(translate[0], translate[1]).scale(scale)
    );
  }

  function clearActive() {
    if (!activeDistrictId) return;
    // Reset boundary polygons
    mapG.selectAll('.vaaa-district-boundary.vaaa-active').each(function() {
      var el = d3.select(this);
      var dId = +el.attr('data-district');
      el.style('fill', distColor(dId))
        .style('fill-opacity', 0.45)
        .style('stroke', '#fff')
        .style('stroke-width', 1)
        .classed('vaaa-active', false);
    });
    // County base stays neutral, no reset needed
  }

  function deselectDistrict() {
    clearActive();
    activeDistrictId = null;
    districtList.querySelectorAll('.vaaa-district-list-item').forEach(function(el) {
      el.classList.remove('vaaa-list-active');
    });
    mapSvg.transition().duration(500).call(zoom.transform, d3.zoomIdentity);
    panelDefault.classList.remove('vaaa-hidden');
    panelDetail.classList.add('vaaa-hidden');
  }

  // ── Detail panel (server-rendered, JS just shows/hides) ──
  function showDetail(distId) {
    detailContent.querySelectorAll('.vaaa-district-detail').forEach(function(el) {
      el.style.display = 'none';
    });
    var target = detailContent.querySelector('[data-district-id="' + distId + '"]');
    if (target) target.style.display = '';
    panelDefault.classList.add('vaaa-hidden');
    panelDetail.classList.remove('vaaa-hidden');
  }

  // ── Back button ──
  backBtn.addEventListener('click', deselectDistrict);

  // ── Init ──
  buildDistrictList();
  initSearch();
  initMap();
})();
