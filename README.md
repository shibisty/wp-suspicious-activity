# Sharing Activity Detector by Shibisty

**Contributors:** alexandershibisty
**Tags:** security, audit log, session tracking, activity monitor, multi-device
**Requires at least:** 5.0
**Tested up to:** 7.1
**Requires PHP:** 7.4
**Stable tag:** 5.0.0
**License:** MIT
**License URI:** https://opensource.org/licenses/MIT

Tracks user sessions/requests, flags suspicious multi-device activity, and gives admins an interactive timeline, a full request log, and settings.

## Description

Sharing Activity Detector by Shibisty logs requests (page views, REST/AJAX calls, admin-area hits, and optional heartbeat pings) into a dedicated database table, then groups them into sessions per user/device. From that it computes, for each user:

* **Range** — the sum of all active time intervals (how long the user was actually on the site).
* **Total per device** — the sum of each device's active time, computed separately.
* **Parallel time** — `Total − Range`. A value greater than zero means two or more devices were active for the same user at the same time, which is the plugin's sharing-activity-detector signal (e.g. a shared account being used from two locations at once).

Session boundaries are computed with a simple gap rule: if two consecutive requests from the same user/device are ≤ 5 minutes apart, they belong to the same session and the time between them is counted; a gap of more than 5 minutes is treated as the user having left, and that gap is *not* counted toward active time.

### Admin screens

All screens live under **Suspicious Activity** in the admin menu (`manage_options` capability required):

* **Activity** (`sharing-activity-detector`) — list of users with a sharing-activity-detector timeline, sortable/filterable.
* **Request Logs** (`wp-sad-request-logs`) — full paginated log of every recorded request, with filters and sortable columns.
* **Settings** (`wp-sad-settings`) — logging rules, heartbeat, and interface language.
* **Log View** (`wp-sad-log-view`, hidden) — detail view of a single log entry, linked from the Request Logs list.
* **Activity View** (`wp-sad-activity-view`, hidden) — detail view of a single user's activity/timeline, linked from the Activity list.

Filter selections on the Activity and Request Logs screens are remembered in the browser (`localStorage`) and restored on your next visit; the Reset button always clears them and returns to the default view. Wherever a user is identified from a session, their name links directly to their WordPress profile/edit-user page.

### Settings

Found under **Suspicious Activity → Settings**:

* **Audience (who gets logged)** — All users (including guests), Registered users only, or Admins only.
* **Logging scope (what gets logged)** — all pages/API/admin area, only pages and heartbeat, only the site excluding admin area and heartbeat, only admin area and heartbeat, or stop logging entirely.
* **Logging window (when to log)** — Always, or only during a specific time-of-day window (`from`–`to`). Windows that cross midnight (e.g. `22:00`–`06:00`) are supported.
* **Heartbeat** — enable/disable, plus a configurable ping interval (15–600 seconds). When enabled, a small script pings the plugin's own REST endpoint (`/wp-json/wp-sad/v1/heartbeat`) at that interval for as long as the browser tab is open and visible, so active time is counted accurately even between page loads, without relying on `admin-ajax.php`.
* **Interface language** — the language the plugin's own admin screens are displayed in, independent of the site's overall language. The dropdown lists both languages already installed as WordPress core language packs and the languages this plugin ships its own translations for, so a translation works immediately without needing a matching WordPress language pack installed.

### Localization

The plugin is fully translatable (`sharing-activity-detector` text domain, `/languages` directory) and ships complete, hand-written translations for 30 languages out of the box: English, Ukrainian (source language), Russian, German, French, Spanish, Italian, Portuguese (Brazil), Portuguese (Portugal), Polish, Dutch, Romanian, Czech, Hungarian, Bulgarian, Greek, Turkish, Swedish, Finnish, Lithuanian, Croatian, Serbian, Georgian, Azerbaijani, Kazakh, Belarusian, Arabic, Hebrew, Hindi, Chinese (Simplified), and Japanese.

Pick a language on the Settings screen — it takes effect immediately and doesn't require installing a WordPress core language pack for that locale, since the plugin loads its own `.mo` file directly from its `/languages` folder.

### Architecture

The plugin is split into logic and presentation layers rather than one monolithic file:

```
sharing-activity-detector/
├── sharing-activity-detector.php     # Bootstrap: constants, requires, activation
├── includes/
│   ├── class-plugin.php           # Orchestrator: hooks, textdomain, wiring
│   ├── class-db.php                # Table name/schema, install + upgrade (dbDelta), checked on every init
│   ├── class-settings.php          # Settings storage, sanitization, bundled languages
│   ├── class-request-logger.php    # Classifies + gates + records each request
│   ├── class-heartbeat.php         # REST heartbeat route + front-end pinger
│   ├── class-session-analyzer.php  # Pure session/timeline/risk calculation logic
│   ├── class-view-helpers.php / class-query-helpers.php
│   ├── class-admin-menu.php        # Menu registration, asset enqueueing
│   └── admin/                      # Page controllers (Activity, Request Logs, Log View, Activity View, Settings)
├── views/                          # Plain PHP templates — no business logic or queries
├── assets/                         # css/, js/ (timeline UI, filter persistence, heartbeat pinger)
└── languages/                      # .pot template + .po/.mo per locale
```

Request records carry a `request_type` (`page`, `api`, `admin`, or `heartbeat`), set at write time, which the session analyzer uses to decide what counts as a genuine presence signal versus administrative/system noise.

## Installation

1. Copy the `sharing-activity-detector` folder into `wp-content/plugins/`, or install it as a zip from the Plugins → Add New screen.
2. Activate **Sharing Activity Detector by Shibisty** from the WordPress admin Plugins screen.
3. The plugin creates its own table (`{$wpdb->prefix}request_logs`) automatically on activation, and also re-checks the table/schema on every `init` — so the table is repaired automatically if it's ever missing, without needing to reactivate the plugin.
4. Visit **Suspicious Activity → Settings** to choose who gets logged, what gets logged, and whether heartbeat is enabled.

## Frequently Asked Questions

### Does this plugin delete any data when deactivated or uninstalled?

No. The plugin does not delete its data on deactivation, and uninstalling/deleting the plugin does not currently remove the `request_logs` table or its data either. Back it up or drop it manually if you need a clean removal.

### Can I add a language that isn't bundled?

Yes. Drop a `sharing-activity-detector-{locale}.mo` (and, ideally, `.po`) file into `/languages`, using `sharing-activity-detector.pot` as the reference for all translatable strings, and add the locale to `WP_SAD_Settings::BUNDLED_LANGUAGES` in `includes/class-settings.php` so it appears in the Settings dropdown.

### Can heartbeat data be added retroactively for past traffic?

No. Heartbeat and `request_type`-aware session data are only accurate for traffic recorded after the relevant setting was enabled; there's no way to reconstruct heartbeat signal for historical requests.

## Changelog

### 5.0.0

* Rewrote the plugin as an MVC-style architecture (includes/admin/views/assets) instead of a single monolithic file.
* Fixed a session-time calculation bug where the signal-type filter was inverted, and where `admin-ajax.php` requests were being skipped entirely.
* Added settings for logging audience, scope, time window, and heartbeat.
* Added Request Logs, Log View, Activity View, and Settings admin pages.
* Added a user-type filter (All / Registered / Admins) and `localStorage`-based filter persistence.
* Added full translations for 30 languages.
