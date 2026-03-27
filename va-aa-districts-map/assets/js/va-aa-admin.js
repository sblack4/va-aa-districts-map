/**
 * VA AA Districts Map — Admin JS
 * Handles accordion, filtering, unsaved changes warning, and sync.
 */
(function () {
  'use strict';

  // ── Accordion toggle ──
  document.querySelectorAll('.vaaa-card-header').forEach(function (header) {
    header.addEventListener('click', function () {
      var body = this.nextElementSibling;
      var expanded = this.getAttribute('aria-expanded') === 'true';
      this.setAttribute('aria-expanded', !expanded);
      body.style.display = expanded ? 'none' : '';
    });
    header.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this.click(); }
    });
  });

  // ── Filter districts ──
  var filterInput = document.getElementById('vaaa-filter');
  if (filterInput) {
    filterInput.addEventListener('input', function () {
      var q = this.value.toLowerCase().trim();
      document.querySelectorAll('.vaaa-card').forEach(function (card) {
        var name = card.getAttribute('data-name') || '';
        var num = card.getAttribute('data-district') || '';
        card.style.display = (!q || name.indexOf(q) > -1 || num.indexOf(q) > -1) ? '' : 'none';
      });
    });
  }

  // ── Unsaved changes warning ──
  var form = document.getElementById('vaaa-districts-form');
  if (form) {
    var dirty = false;
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) {
      if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });
  }

  // ── Sync from website ──
  var syncBtn = document.getElementById('vaaa-sync-btn');
  var syncResults = document.getElementById('vaaa-sync-results');
  if (syncBtn && syncResults) {
    syncBtn.addEventListener('click', function () {
      syncBtn.disabled = true;
      syncBtn.textContent = 'Fetching...';
      syncResults.innerHTML = '<p>Loading meetings from aavirginia.org...</p>';

      var xhr = new XMLHttpRequest();
      xhr.open('POST', VAAA_ADMIN.ajax_url);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      xhr.onload = function () {
        syncBtn.disabled = false;
        syncBtn.textContent = 'Fetch Meeting Counts';
        try {
          var resp = JSON.parse(xhr.responseText);
          if (!resp.success) {
            syncResults.innerHTML = '<p style="color:#a00;">Error: ' + (resp.data || 'Unknown error') + '</p>';
            return;
          }
          var d = resp.data;
          var html = '<p><strong>' + d.total_meetings + '</strong> total meetings found';
          if (d.untagged) html += ' (' + d.untagged + ' without a district tag)';
          html += '.</p>';
          html += '<table><thead><tr><th>#</th><th>Name</th><th>Meetings</th><th>Top Cities</th><th>In Plugin</th></tr></thead><tbody>';
          d.districts.forEach(function (row) {
            html += '<tr>';
            html += '<td>' + row.district + '</td>';
            html += '<td>' + row.name + '</td>';
            html += '<td>' + row.meetings + '</td>';
            html += '<td>' + row.cities + '</td>';
            html += '<td class="' + (row.in_plugin ? 'vaaa-sync-ok' : 'vaaa-sync-missing') + '">' + (row.in_plugin ? 'Yes' : 'Missing') + '</td>';
            html += '</tr>';
          });
          html += '</tbody></table>';
          syncResults.innerHTML = html;
        } catch (e) {
          syncResults.innerHTML = '<p style="color:#a00;">Failed to parse response.</p>';
        }
      };
      xhr.onerror = function () {
        syncBtn.disabled = false;
        syncBtn.textContent = 'Fetch Meeting Counts';
        syncResults.innerHTML = '<p style="color:#a00;">Network error.</p>';
      };
      xhr.send('action=vaaa_sync_meetings&_ajax_nonce=' + VAAA_ADMIN.sync_nonce);
    });
  }
})();
