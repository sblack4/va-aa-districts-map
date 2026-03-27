<?php
/**
 * Plugin Name: Virginia AA Districts Map
 * Plugin URI:  https://github.com/sblack4/vac
 * Description: Interactive map of Virginia Area AA districts. Use the shortcode [va_aa_map] to embed the map on any page or post. Edit district data and county-to-district mappings from the WordPress admin.
 * Version:     2.0.0
 * Author:      Virginia AA
 * License:     GPL v2 or later
 * Text Domain: va-aa-districts-map
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'VAAA_VERSION',    '2.0.0' );
define( 'VAAA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VAAA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/* ──────────────────────────────────────────────
 * 0. Load text domain for translations
 * ────────────────────────────────────────────── */
add_action( 'init', 'vaaa_load_textdomain' );
function vaaa_load_textdomain() {
    load_plugin_textdomain( 'va-aa-districts-map', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}

/* ──────────────────────────────────────────────
 * 1. Activation: seed default data into options
 * ────────────────────────────────────────────── */
register_activation_hook( __FILE__, 'vaaa_activate' );
function vaaa_activate() {
    // Only seed if no data exists yet
    if ( false === get_option( 'vaaa_districts' ) ) {
        $json = file_get_contents( VAAA_PLUGIN_DIR . 'includes/default-data.json' );
        $data = json_decode( $json, true );
        update_option( 'vaaa_districts',     $data['districts'],     false );
        update_option( 'vaaa_fips_map',      $data['fipsToDistrict'], false );
        update_option( 'vaaa_shared_counties', $data['sharedCountyDistricts'], false );
    }
}

/* ──────────────────────────────────────────────
 * 2. Public shortcode: [va_aa_map]
 * ────────────────────────────────────────────── */
add_shortcode( 'va_aa_map', 'vaaa_render_shortcode' );
function vaaa_render_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'height' => '650px',
    ), $atts, 'va_aa_map' );

    // Enqueue front-end assets
    wp_enqueue_style(
        'vaaa-map-style',
        VAAA_PLUGIN_URL . 'assets/css/va-aa-map.css',
        array(),
        VAAA_VERSION
    );
    wp_enqueue_script( 'vaaa-d3',       'https://d3js.org/d3.v7.min.js',            array(), '7', true );
    wp_enqueue_script( 'vaaa-topojson', 'https://d3js.org/topojson.v3.min.js',       array(), '3', true );
    wp_enqueue_script(
        'vaaa-districts-data',
        VAAA_PLUGIN_URL . 'assets/js/districts-data.js',
        array(),
        VAAA_VERSION,
        true
    );
    wp_enqueue_script(
        'vaaa-map-app',
        VAAA_PLUGIN_URL . 'assets/js/va-aa-map.js',
        array( 'vaaa-d3', 'vaaa-topojson', 'vaaa-districts-data' ),
        VAAA_VERSION,
        true
    );

    // Pass dynamic data from WP options to JS
    $districts = get_option( 'vaaa_districts',       array() );
    $fips_map  = get_option( 'vaaa_fips_map',        array() );
    $shared    = get_option( 'vaaa_shared_counties',  array() );

    wp_localize_script( 'vaaa-map-app', 'VAAA_DATA', array(
        'districts'     => $districts,
        'fipsToDistrict' => $fips_map,
        'sharedCountyDistricts' => $shared,
        'boundariesUrl' => VAAA_PLUGIN_URL . 'assets/js/district-boundaries.json',
    ) );

    $height = esc_attr( $atts['height'] );

    ob_start();
    ?>
    <div class="vaaa-map-wrap alignwide" style="min-height:<?php echo $height; ?>;">
      <div class="vaaa-map-layout">
        <div class="vaaa-map-container">
          <div class="vaaa-map-controls">
            <button class="vaaa-map-btn" data-vaaa-zoom="in" aria-label="Zoom in">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <button class="vaaa-map-btn" data-vaaa-zoom="out" aria-label="Zoom out">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
            </button>
            <button class="vaaa-map-btn" data-vaaa-zoom="reset" aria-label="Reset view">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 9-9 5 5 0 0 0-3 1"/><polyline points="1 4 4 4 4 7"/></svg>
            </button>
          </div>
          <div class="vaaa-svg-container"></div>
          <div class="vaaa-tooltip"></div>
        </div>
        <aside class="vaaa-info-panel">
          <div class="vaaa-panel-default">
            <div class="vaaa-panel-header">
              <h2>Virginia Area Districts</h2>
              <p>Click a district on the map to view details.</p>
            </div>
            <div class="vaaa-district-list-section">
              <div class="vaaa-search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" class="vaaa-search-input" placeholder="Search districts..." aria-label="Search districts">
              </div>
              <ul class="vaaa-district-list" role="list"></ul>
            </div>
          </div>
          <div class="vaaa-panel-detail vaaa-hidden">
            <button class="vaaa-back-btn">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
              All Districts
            </button>
            <div class="vaaa-detail-content">
              <?php foreach ( $districts as $dist ): $d_id = intval($dist['id']); ?>
              <div class="vaaa-district-detail" data-district-id="<?php echo $d_id; ?>" style="display:none;">
                <div class="vaaa-detail-header">
                  <div class="vaaa-detail-num"><?php echo $d_id; ?></div>
                  <h2 class="vaaa-detail-name"><?php echo esc_html( $dist['name'] ); ?></h2>
                  <?php if ( ! empty( $dist['inactive'] ) ): ?>
                    <div class="vaaa-badge-inactive"><?php esc_html_e( 'Inactive', 'va-aa-districts-map' ); ?></div>
                  <?php endif; ?>
                </div>

                <?php if ( ! empty( $dist['inactive'] ) ): ?>
                  <p class="vaaa-no-info"><?php esc_html_e( 'This district is currently inactive.', 'va-aa-districts-map' ); ?></p>
                <?php else:
                  $has_info = false;
                  if ( ! empty( $dist['meeting'] ) ): $has_info = true; ?>
                    <div class="vaaa-section">
                      <div class="vaaa-section-title"><?php esc_html_e( 'Meeting Schedule', 'va-aa-districts-map' ); ?></div>
                      <div class="vaaa-row">
                        <div class="vaaa-row-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
                        <div><?php echo esc_html( $dist['meeting'] ); ?></div>
                      </div>
                    </div>
                  <?php endif;
                  if ( ! empty( $dist['location'] ) ): $has_info = true; ?>
                    <div class="vaaa-section">
                      <div class="vaaa-section-title"><?php esc_html_e( 'Location', 'va-aa-districts-map' ); ?></div>
                      <div class="vaaa-row">
                        <div class="vaaa-row-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg></div>
                        <div><?php echo nl2br( esc_html( $dist['location'] ) ); ?></div>
                      </div>
                    </div>
                  <?php endif;
                  if ( ! empty( $dist['zoom'] ) ): $has_info = true;
                    $zoom_str = $dist['zoom'];
                    $zoom_url = '';
                    $zoom_details = $zoom_str;
                    if ( strpos( $zoom_str, 'http' ) === 0 ) {
                      $parts = preg_split( '/\s/', $zoom_str, 2 );
                      $zoom_url = $parts[0];
                      $zoom_details = isset( $parts[1] ) ? ltrim( $parts[1], '/ ' ) : '';
                    }
                  ?>
                    <div class="vaaa-section">
                      <div class="vaaa-section-title"><?php esc_html_e( 'Virtual Meeting', 'va-aa-districts-map' ); ?></div>
                      <div class="vaaa-row">
                        <div class="vaaa-row-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 10l4.553-2.276A1 1 0 0 1 21 8.618v6.764a1 1 0 0 1-1.447.894L15 14V10z"/><rect x="1" y="6" width="14" height="12" rx="2" ry="2"/></svg></div>
                        <div>
                          <?php if ( $zoom_url ): ?>
                            <a href="<?php echo esc_url( $zoom_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Join Virtual Meeting', 'va-aa-districts-map' ); ?></a>
                            <?php if ( $zoom_details ): ?><br><span style="font-size:0.8em;color:#6b6960;"><?php echo esc_html( $zoom_details ); ?></span><?php endif; ?>
                          <?php else: ?>
                            <?php echo esc_html( $zoom_str ); ?>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endif;
                  if ( ! empty( $dist['email'] ) ): $has_info = true; ?>
                    <div class="vaaa-section">
                      <div class="vaaa-section-title"><?php esc_html_e( 'Contact', 'va-aa-districts-map' ); ?></div>
                      <div class="vaaa-row">
                        <div class="vaaa-row-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                        <div><a href="mailto:<?php echo esc_attr( $dist['email'] ); ?>"><?php echo esc_html( $dist['email'] ); ?></a></div>
                      </div>
                    </div>
                  <?php endif;
                  if ( ! empty( $dist['altEmail'] ) ): $has_info = true; ?>
                    <div class="vaaa-section">
                      <div class="vaaa-section-title"><?php esc_html_e( 'Alt Contact', 'va-aa-districts-map' ); ?></div>
                      <div class="vaaa-row">
                        <div class="vaaa-row-icon"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg></div>
                        <div><a href="mailto:<?php echo esc_attr( $dist['altEmail'] ); ?>"><?php echo esc_html( $dist['altEmail'] ); ?></a></div>
                      </div>
                    </div>
                  <?php endif;
                  if ( ! $has_info ): ?>
                    <p class="vaaa-no-info"><?php printf( esc_html__( 'Detailed information is not currently available. Visit %s for updates.', 'va-aa-districts-map' ), '<a href="https://aavirginia.org/member-services/virginia-area-districts/" target="_blank" rel="noopener noreferrer">aavirginia.org</a>' ); ?></p>
                  <?php endif;
                endif; ?>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </aside>
      </div>
    </div>
    <?php
    return ob_get_clean();
}

/* ──────────────────────────────────────────────
 * 3. Admin page for editing data
 * ────────────────────────────────────────────── */
add_action( 'admin_menu', 'vaaa_admin_menu' );
function vaaa_admin_menu() {
    add_menu_page(
        'VA AA Districts',
        'AA Districts Map',
        'manage_options',
        'vaaa-districts',
        'vaaa_admin_page',
        'dashicons-location-alt',
        80
    );
}

/* Enqueue admin assets */
add_action( 'admin_enqueue_scripts', 'vaaa_admin_assets' );
function vaaa_admin_assets( $hook ) {
    if ( $hook !== 'toplevel_page_vaaa-districts' ) return;
    wp_enqueue_style( 'vaaa-admin-style', VAAA_PLUGIN_URL . 'assets/css/va-aa-admin.css', array(), VAAA_VERSION );
    wp_enqueue_script( 'vaaa-admin-js', VAAA_PLUGIN_URL . 'assets/js/va-aa-admin.js', array(), VAAA_VERSION, true );
    wp_localize_script( 'vaaa-admin-js', 'VAAA_ADMIN', array(
        'ajax_url'   => admin_url( 'admin-ajax.php' ),
        'sync_nonce' => wp_create_nonce( 'vaaa_sync_nonce' ),
    ) );
}

function vaaa_admin_page() {
    $regions = array(
        'nova' => 'Northern Virginia', 'central' => 'Central', 'tidewater' => 'Tidewater',
        'sw' => 'Southwest', 'valley' => 'Shenandoah Valley', 'east' => 'Eastern',
        'south' => 'South', 'statewide' => 'Statewide',
    );

    // ── Handle district form save ──
    if ( isset( $_POST['vaaa_districts_nonce'] ) && wp_verify_nonce( $_POST['vaaa_districts_nonce'], 'vaaa_save_districts' ) ) {
        $posted = isset( $_POST['vaaa_dist'] ) ? $_POST['vaaa_dist'] : array();
        $updated = array();
        $warnings = array();
        foreach ( $posted as $id => $fields ) {
            $d = array(
                'id'       => intval( $id ),
                'name'     => sanitize_text_field( $fields['name'] ?? '' ),
                'meeting'  => sanitize_text_field( $fields['meeting'] ?? '' ) ?: null,
                'location' => sanitize_textarea_field( $fields['location'] ?? '' ) ?: null,
                'email'    => sanitize_email( $fields['email'] ?? '' ) ?: null,
                'altEmail' => sanitize_email( $fields['altEmail'] ?? '' ) ?: null,
                'zoom'     => sanitize_text_field( $fields['zoom'] ?? '' ) ?: null,
                'region'   => sanitize_text_field( $fields['region'] ?? 'central' ),
            );
            if ( ! empty( $fields['inactive'] ) ) $d['inactive'] = true;
            if ( empty( $d['name'] ) ) $d['name'] = 'District ' . $d['id'];
            if ( ! $d['inactive'] && ! $d['email'] ) {
                $warnings[] = $d['id'];
            }
            $updated[] = $d;
        }
        usort( $updated, function( $a, $b ) { return $a['id'] - $b['id']; } );
        update_option( 'vaaa_districts', $updated, false );
        echo '<div class="notice notice-success"><p>Saved ' . count( $updated ) . ' districts.</p></div>';
        if ( $warnings ) {
            echo '<div class="notice notice-warning"><p>Districts missing contact email: ' . implode( ', ', $warnings ) . '</p></div>';
        }
    }

    // ── Handle JSON import save ──
    if ( isset( $_POST['vaaa_save_nonce'] ) && wp_verify_nonce( $_POST['vaaa_save_nonce'], 'vaaa_save_data' ) ) {
        if ( ! empty( $_POST['vaaa_json_data'] ) ) {
            $raw  = wp_unslash( $_POST['vaaa_json_data'] );
            $data = json_decode( $raw, true );
            if ( $data && isset( $data['districts'] ) && isset( $data['fipsToDistrict'] ) ) {
                update_option( 'vaaa_districts',       $data['districts'],              false );
                update_option( 'vaaa_fips_map',        $data['fipsToDistrict'],         false );
                update_option( 'vaaa_shared_counties', $data['sharedCountyDistricts'] ?? array(), false );
                echo '<div class="notice notice-success"><p>Data imported successfully.</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>Invalid JSON. Must contain "districts" and "fipsToDistrict" keys.</p></div>';
            }
        }
    }

    // ── Handle reset ──
    if ( isset( $_POST['vaaa_reset_nonce'] ) && wp_verify_nonce( $_POST['vaaa_reset_nonce'], 'vaaa_reset_data' ) ) {
        $json = file_get_contents( VAAA_PLUGIN_DIR . 'includes/default-data.json' );
        $data = json_decode( $json, true );
        update_option( 'vaaa_districts',       $data['districts'],              false );
        update_option( 'vaaa_fips_map',        $data['fipsToDistrict'],         false );
        update_option( 'vaaa_shared_counties', $data['sharedCountyDistricts'], false );
        echo '<div class="notice notice-success"><p>Data reset to defaults.</p></div>';
    }

    $districts = get_option( 'vaaa_districts', array() );
    $fips_map  = get_option( 'vaaa_fips_map', array() );
    $shared    = get_option( 'vaaa_shared_counties', array() );
    $current_json = json_encode( array(
        'districts' => $districts, 'fipsToDistrict' => $fips_map, 'sharedCountyDistricts' => $shared,
    ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
    $active_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'districts';
    ?>
    <div class="wrap vaaa-admin-wrap">
      <h1><span class="dashicons dashicons-location-alt"></span> Virginia AA Districts Map</h1>
      <p>Shortcode: <code>[va_aa_map]</code> &nbsp;|&nbsp; Custom height: <code>[va_aa_map height="800px"]</code></p>

      <nav class="nav-tab-wrapper vaaa-tabs">
        <a href="?page=vaaa-districts&tab=districts" class="nav-tab <?php echo $active_tab === 'districts' ? 'nav-tab-active' : ''; ?>">Districts</a>
        <a href="?page=vaaa-districts&tab=preview" class="nav-tab <?php echo $active_tab === 'preview' ? 'nav-tab-active' : ''; ?>">Map Preview</a>
        <a href="?page=vaaa-districts&tab=advanced" class="nav-tab <?php echo $active_tab === 'advanced' ? 'nav-tab-active' : ''; ?>">Advanced</a>
      </nav>

      <?php if ( $active_tab === 'districts' ): ?>
      <!-- ═══════ DISTRICTS TAB ═══════ -->
      <form method="post" id="vaaa-districts-form">
        <?php wp_nonce_field( 'vaaa_save_districts', 'vaaa_districts_nonce' ); ?>
        <div class="vaaa-districts-toolbar">
          <span class="vaaa-district-count"><?php echo count( $districts ); ?> districts</span>
          <input type="text" id="vaaa-filter" placeholder="Filter districts..." class="vaaa-filter-input">
          <input type="submit" class="button button-primary" value="Save All Districts">
        </div>
        <div class="vaaa-accordion" id="vaaa-accordion">
          <?php foreach ( $districts as $dist ):
            $d_id = intval( $dist['id'] );
            $is_inactive = ! empty( $dist['inactive'] );
            $missing = array();
            if ( ! $is_inactive ) {
                if ( empty( $dist['email'] ) ) $missing[] = 'email';
                if ( empty( $dist['meeting'] ) && empty( $dist['location'] ) ) $missing[] = 'meeting info';
            }
          ?>
          <div class="vaaa-card<?php echo $is_inactive ? ' vaaa-card-inactive' : ''; ?><?php echo $missing ? ' vaaa-card-warn' : ''; ?>" data-district="<?php echo $d_id; ?>" data-name="<?php echo esc_attr( strtolower( $dist['name'] ?? '' ) ); ?>">
            <div class="vaaa-card-header" tabindex="0" role="button" aria-expanded="false">
              <span class="vaaa-card-num"><?php echo $d_id; ?></span>
              <span class="vaaa-card-name"><?php echo esc_html( $dist['name'] ?? '' ); ?></span>
              <?php if ( $is_inactive ): ?><span class="vaaa-card-badge vaaa-badge-off">Inactive</span><?php endif; ?>
              <?php if ( $missing ): ?><span class="vaaa-card-badge vaaa-badge-warn">Missing <?php echo implode( ', ', $missing ); ?></span><?php endif; ?>
              <span class="vaaa-card-toggle dashicons dashicons-arrow-down-alt2"></span>
            </div>
            <div class="vaaa-card-body" style="display:none;">
              <table class="form-table vaaa-field-table">
                <tr>
                  <th><label>Name</label></th>
                  <td><input type="text" name="vaaa_dist[<?php echo $d_id; ?>][name]" value="<?php echo esc_attr( $dist['name'] ?? '' ); ?>" class="regular-text" required></td>
                </tr>
                <tr>
                  <th><label>Meeting Schedule</label></th>
                  <td><input type="text" name="vaaa_dist[<?php echo $d_id; ?>][meeting]" value="<?php echo esc_attr( $dist['meeting'] ?? '' ); ?>" class="regular-text" placeholder="e.g. 2nd Monday at 7:00 PM"></td>
                </tr>
                <tr>
                  <th><label>Location</label></th>
                  <td><textarea name="vaaa_dist[<?php echo $d_id; ?>][location]" rows="3" class="large-text" placeholder="Church Name&#10;123 Main St&#10;City, VA 12345"><?php echo esc_textarea( $dist['location'] ?? '' ); ?></textarea></td>
                </tr>
                <tr>
                  <th><label>Email</label></th>
                  <td><input type="email" name="vaaa_dist[<?php echo $d_id; ?>][email]" value="<?php echo esc_attr( $dist['email'] ?? '' ); ?>" class="regular-text" placeholder="dcm<?php echo $d_id; ?>@aavirginia.org"></td>
                </tr>
                <tr>
                  <th><label>Alt Email</label></th>
                  <td><input type="email" name="vaaa_dist[<?php echo $d_id; ?>][altEmail]" value="<?php echo esc_attr( $dist['altEmail'] ?? '' ); ?>" class="regular-text"></td>
                </tr>
                <tr>
                  <th><label>Virtual Meeting</label></th>
                  <td><input type="text" name="vaaa_dist[<?php echo $d_id; ?>][zoom]" value="<?php echo esc_attr( $dist['zoom'] ?? '' ); ?>" class="large-text" placeholder="Zoom URL or meeting ID"></td>
                </tr>
                <tr>
                  <th><label>Region</label></th>
                  <td>
                    <select name="vaaa_dist[<?php echo $d_id; ?>][region]">
                      <?php foreach ( $regions as $key => $label ): ?>
                        <option value="<?php echo $key; ?>" <?php selected( $dist['region'] ?? '', $key ); ?>><?php echo esc_html( $label ); ?></option>
                      <?php endforeach; ?>
                    </select>
                  </td>
                </tr>
                <tr>
                  <th><label>Inactive</label></th>
                  <td><label><input type="checkbox" name="vaaa_dist[<?php echo $d_id; ?>][inactive]" value="1" <?php checked( $is_inactive ); ?>> Mark as inactive</label></td>
                </tr>
              </table>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <p class="submit"><input type="submit" class="button button-primary button-hero" value="Save All Districts"></p>
      </form>

      <?php elseif ( $active_tab === 'preview' ): ?>
      <!-- ═══════ MAP PREVIEW TAB ═══════ -->
      <div class="vaaa-preview-wrap">
        <p>This is a read-only preview of how the map currently looks on the front end.</p>
        <iframe src="<?php echo esc_url( home_url( '/?vaaa_preview=1' ) ); ?>" style="width:100%;height:700px;border:1px solid #ccc;border-radius:4px;background:#fff;"></iframe>
      </div>

      <?php else: ?>
      <!-- ═══════ ADVANCED TAB ═══════ -->
      <div class="vaaa-advanced-wrap">

        <h2>Sync from Website</h2>
        <p>Check how many meetings each district has on <a href="https://aavirginia.org" target="_blank">aavirginia.org</a>.</p>
        <button type="button" class="button" id="vaaa-sync-btn">Fetch Meeting Counts</button>
        <div id="vaaa-sync-results" style="margin-top:1em;"></div>

        <hr>

        <h2>Import / Export JSON</h2>
        <p>For bulk edits or county reassignment, use the <a href="<?php echo esc_url( VAAA_PLUGIN_URL . 'admin-tool.html' ); ?>" target="_blank">Visual Admin Tool</a> to edit, export JSON, and paste it below.</p>
        <form method="post">
          <?php wp_nonce_field( 'vaaa_save_data', 'vaaa_save_nonce' ); ?>
          <textarea name="vaaa_json_data" rows="15" style="width:100%;font-family:monospace;font-size:12px;"><?php echo esc_textarea( $current_json ); ?></textarea>
          <p><input type="submit" class="button" value="Import JSON"></p>
        </form>

        <hr>

        <h2>Reset to Defaults</h2>
        <p>Restore the original district data that shipped with the plugin.</p>
        <form method="post" onsubmit="return confirm('Reset ALL district data to defaults? This cannot be undone.');">
          <?php wp_nonce_field( 'vaaa_reset_data', 'vaaa_reset_nonce' ); ?>
          <input type="submit" class="button" value="Reset to Default Data" style="color:#a00;">
        </form>
      </div>
      <?php endif; ?>
    </div>
    <?php
}

/* ── Map preview endpoint (lightweight, no theme chrome) ── */
add_action( 'template_redirect', 'vaaa_preview_endpoint' );
function vaaa_preview_endpoint() {
    if ( ! isset( $_GET['vaaa_preview'] ) ) return;
    // Output a minimal page with just the map shortcode
    ?><!DOCTYPE html>
    <html><head>
      <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
      <?php wp_head(); ?>
      <style>body{margin:0;padding:1rem;font-family:-apple-system,sans-serif;}</style>
    </head><body>
      <?php echo do_shortcode( '[va_aa_map height="640px"]' ); ?>
      <?php wp_footer(); ?>
    </body></html><?php
    exit;
}

/* ── AJAX: Sync meetings from aavirginia.org ── */
add_action( 'wp_ajax_vaaa_sync_meetings', 'vaaa_sync_meetings' );
function vaaa_sync_meetings() {
    check_ajax_referer( 'vaaa_sync_nonce' );
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( 'Forbidden' );

    $response = wp_remote_get( 'https://aavirginia.org/wp-admin/admin-ajax.php?action=meetings', array( 'timeout' => 30 ) );
    if ( is_wp_error( $response ) ) {
        wp_send_json_error( 'Failed to fetch: ' . $response->get_error_message() );
    }
    $meetings = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $meetings ) ) {
        wp_send_json_error( 'Invalid response from aavirginia.org' );
    }

    // Group by district
    $by_district = array();
    $untagged = 0;
    foreach ( $meetings as $m ) {
        $dname = $m['district'] ?? '';
        if ( ! $dname ) { $untagged++; continue; }
        preg_match( '/District\s+(\d+)/', $dname, $match );
        if ( ! $match ) { $untagged++; continue; }
        $dnum = intval( $match[1] );
        if ( ! isset( $by_district[ $dnum ] ) ) {
            $by_district[ $dnum ] = array( 'name' => $dname, 'count' => 0, 'cities' => array() );
        }
        $by_district[ $dnum ]['count']++;
        $addr = $m['formatted_address'] ?? '';
        $parts = explode( ',', $addr );
        if ( count( $parts ) >= 3 ) {
            $city = trim( $parts[ count( $parts ) - 3 ] );
            $by_district[ $dnum ]['cities'][ $city ] = ( $by_district[ $dnum ]['cities'][ $city ] ?? 0 ) + 1;
        }
    }
    ksort( $by_district );

    // Compare with current plugin data
    $current = get_option( 'vaaa_districts', array() );
    $current_ids = array_map( function( $d ) { return $d['id']; }, $current );

    $result = array();
    foreach ( $by_district as $dnum => $info ) {
        arsort( $info['cities'] );
        $top_cities = array_slice( array_keys( $info['cities'] ), 0, 3 );
        $result[] = array(
            'district'   => $dnum,
            'name'       => preg_replace( '/^District\s+\d+\s*-?\s*/', '', $info['name'] ),
            'meetings'   => $info['count'],
            'cities'     => implode( ', ', $top_cities ),
            'in_plugin'  => in_array( $dnum, $current_ids ),
        );
    }

    wp_send_json_success( array(
        'total_meetings' => count( $meetings ),
        'untagged'       => $untagged,
        'districts'      => $result,
    ) );
}

/* ──────────────────────────────────────────────
 * 4. Generate dynamic districts-data.js
 *    (Serves WP option data as JS variables)
 * ────────────────────────────────────────────── */
add_action( 'wp_ajax_vaaa_districts_data',        'vaaa_serve_districts_js' );
add_action( 'wp_ajax_nopriv_vaaa_districts_data', 'vaaa_serve_districts_js' );
function vaaa_serve_districts_js() {
    header( 'Content-Type: application/javascript; charset=utf-8' );
    header( 'Cache-Control: public, max-age=300' );

    $districts = get_option( 'vaaa_districts',        array() );
    $fips_map  = get_option( 'vaaa_fips_map',         array() );
    $shared    = get_option( 'vaaa_shared_counties',   array() );

    echo 'var VA_DISTRICTS = '   . json_encode( $districts, JSON_UNESCAPED_SLASHES ) . ";\n";
    echo 'var FIPS_TO_DISTRICT = ' . json_encode( $fips_map, JSON_UNESCAPED_SLASHES ) . ";\n";
    echo 'var SHARED_COUNTY_DISTRICTS = ' . json_encode( $shared, JSON_UNESCAPED_SLASHES ) . ";\n";
    wp_die();
}

// Override the static JS file URL with the dynamic AJAX endpoint
add_filter( 'script_loader_src', 'vaaa_dynamic_data_url', 10, 2 );
function vaaa_dynamic_data_url( $src, $handle ) {
    if ( $handle === 'vaaa-districts-data' ) {
        return admin_url( 'admin-ajax.php?action=vaaa_districts_data' );
    }
    return $src;
}

/* ──────────────────────────────────────────────
 * 5. GitHub-based auto-updater
 *    Checks GitHub releases for new versions.
 *    To release: create a GitHub release tagged vX.Y.Z
 *    and attach va-aa-districts-map.zip as a release asset.
 * ────────────────────────────────────────────── */
define( 'VAAA_GITHUB_REPO', 'sblack4/vac' );

add_filter( 'pre_set_site_transient_update_plugins', 'vaaa_check_github_update' );
function vaaa_check_github_update( $transient ) {
    if ( empty( $transient->checked ) ) return $transient;

    $plugin_slug = plugin_basename( __FILE__ );
    $current_version = VAAA_VERSION;

    // Check GitHub API (cached for 12 hours by the transient)
    $response = wp_remote_get(
        'https://api.github.com/repos/' . VAAA_GITHUB_REPO . '/releases/latest',
        array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github.v3+json' ) )
    );
    if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
        return $transient;
    }

    $release = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! $release || empty( $release['tag_name'] ) ) return $transient;

    $remote_version = ltrim( $release['tag_name'], 'v' );
    if ( ! version_compare( $remote_version, $current_version, '>' ) ) return $transient;

    // Find the ZIP asset
    $zip_url = '';
    foreach ( $release['assets'] ?? array() as $asset ) {
        if ( substr( $asset['name'], -4 ) === '.zip' ) {
            $zip_url = $asset['browser_download_url'];
            break;
        }
    }
    if ( ! $zip_url ) return $transient;

    $transient->response[ $plugin_slug ] = (object) array(
        'slug'        => 'va-aa-districts-map',
        'plugin'      => $plugin_slug,
        'new_version' => $remote_version,
        'url'         => $release['html_url'],
        'package'     => $zip_url,
    );

    return $transient;
}

// Show release notes in the update details popup
add_filter( 'plugins_api', 'vaaa_plugin_info', 20, 3 );
function vaaa_plugin_info( $result, $action, $args ) {
    if ( $action !== 'plugin_information' || ( $args->slug ?? '' ) !== 'va-aa-districts-map' ) {
        return $result;
    }

    $response = wp_remote_get(
        'https://api.github.com/repos/' . VAAA_GITHUB_REPO . '/releases/latest',
        array( 'timeout' => 10, 'headers' => array( 'Accept' => 'application/vnd.github.v3+json' ) )
    );
    if ( is_wp_error( $response ) ) return $result;

    $release = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! $release ) return $result;

    return (object) array(
        'name'          => 'Virginia AA Districts Map',
        'slug'          => 'va-aa-districts-map',
        'version'       => ltrim( $release['tag_name'], 'v' ),
        'author'        => '<a href="https://aavirginia.org">Virginia AA</a>',
        'homepage'      => 'https://github.com/' . VAAA_GITHUB_REPO,
        'sections'      => array(
            'description'  => 'Interactive map of Virginia Area AA districts.',
            'changelog'    => nl2br( esc_html( $release['body'] ?? '' ) ),
        ),
        'download_link' => '',
    );
}
