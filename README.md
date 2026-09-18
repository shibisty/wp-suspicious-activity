# WP Suspicious Activity Detector

A WordPress plugin that tracks user sessions and requests, flags suspicious multi-device activity, and gives admins an interactive timeline, a full request log, and a settings page to control what gets logged and when.

Current version: **5.0.0**

[![Patreon](https://c5.patreon.com/external/logo/become_a_patron_button.png)](https://www.patreon.com/cw/shibisty)

## What it does

The plugin logs requests (page views, REST/AJAX calls, admin-area hits, and optional heartbeat pings) into a dedicated database table, then groups them into sessions per user/device. From that it computes, for each user:

- **Range** — the sum of all active time intervals (how long the user was actually on the site).
- **Total per device** — the sum of each device's active time, computed separately.
- **Parallel time** — `Total − Range`. A value greater than zero means two or more devices were active for the same user at the same time, which is the plugin's suspicious-activity signal (e.g. a shared account being used from two locations at once).

Session boundaries are computed with a simple gap rule: if two consecutive requests from the same user/device are ≤ 5 minutes apart, they belong to the same session and the time between them is counted; a gap of more than 5 minutes is treated as the user having left, and that gap is *not* counted toward active time.

## Requirements

- WordPress 5.0+
- PHP 7.4+
- MySQL/MariaDB with `dbDelta()` support (standard on any normal WordPress install)

## Installation

1. Copy the `wp-suspicious-activity` folder into `wp-content/plugins/`.
2. Activate **WP Suspicious Activity Detector** from the WordPress admin **Plugins** screen.
3. The plugin creates its own table (`{$wpdb->prefix}request_logs`) automatically on activation, and also re-checks the table/schema on every `init` — so the table is repaired automatically if it's ever missing, without needing to reactivate the plugin.

## Admin screens

All screens live under **Suspicious Activity** in the admin menu (`manage_options` capability required):

| Screen | Slug | Purpose |
|---|---|---|
| Activity | `wp-suspicious-activity` | List of users with a suspicious-activity timeline, sortable/filterable. |
| Request Logs | `wp-sad-request-logs` | Full paginated log of every recorded request, with filters and sortable columns. |
| Settings | `wp-sad-settings` | Logging rules, heartbeat, and interface language (see below). |
| Log View | `wp-sad-log-view` (hidden) | Detail view of a single log entry, linked from the Request Logs list. |
| Activity View | `wp-sad-activity-view` (hidden) | Detail view of a single user's activity/timeline, linked from the Activity list. |

Filter selections on the Activity and Request Logs screens are remembered in the browser (`localStorage`) and restored on your next visit; the **Reset** button always clears them and returns to the default view.

Wherever a user is identified from a session, their name links directly to their WordPress profile/edit-user page.

## Settings

Found under **Suspicious Activity → Settings**:

- **Audience (who gets logged)** — All users (including guests), Registered users only, or Admins only.
- **Logging scope (what gets logged)**
  - All pages, API and admin area (maximum visibility)
  - Only pages and heartbeat
  - Only the site, excluding admin area and heartbeat
  - Only admin area and heartbeat
  - Stop logging entirely
- **Logging window (when to log)** — Always, or only during a specific time-of-day window (`from`–`to`). Windows that cross midnight (e.g. `22:00`–`06:00`) are supported.
- **Heartbeat** — enable/disable, plus a configurable ping interval (15–600 seconds). When enabled, a small script pings the plugin's own REST endpoint (`/wp-json/wp-sad/v1/heartbeat`) at that interval for as long as the browser tab is open and visible, so active time is counted accurately even between page loads — without relying on `admin-ajax.php`.
- **Interface language** — the language the plugin's own admin screens are displayed in, independent of the site's overall language. The dropdown lists both languages already installed as WordPress core language packs and the languages this plugin ships its own translations for (see below), so a translation works immediately without needing a matching WordPress language pack installed.

## Localization

The plugin is fully translatable (`wp-suspicious-activity` text domain, `/languages` directory) and ships **complete, hand-written translations for 30 languages** out of the box — not machine-placeholder files:

English, Ukrainian (source language), Russian, German, French, Spanish, Italian, Portuguese (Brazil), Portuguese (Portugal), Polish, Dutch, Romanian, Czech, Hungarian, Bulgarian, Greek, Turkish, Swedish, Finnish, Lithuanian, Croatian, Serbian, Georgian, Azerbaijani, Kazakh, Belarusian, Arabic, Hebrew, Hindi, Chinese (Simplified), and Japanese.

Pick a language on the Settings screen — it takes effect immediately and doesn't require installing a WordPress core language pack for that locale, since the plugin loads its own `.mo` file directly from its `/languages` folder.

To add a new language, drop a `wp-suspicious-activity-{locale}.mo` (and, ideally, `.po`) file into `/languages`, using `wp-suspicious-activity.pot` as the reference for all translatable strings, and add the locale to `WP_SAD_Settings::BUNDLED_LANGUAGES` in `includes/class-settings.php` so it appears in the dropdown.

## Architecture

The plugin is split into logic and presentation layers rather than one monolithic file:

```
wp-suspicious-activity/
├── wp-suspicious-activity.php     # Bootstrap: constants, requires, activation
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

## Data retention

The plugin does not delete its data on deactivation. Uninstalling/deleting the plugin does not currently remove the `request_logs` table or its data either — back it up or drop it manually if you need a clean removal.

## License

No license file is currently included; treat this as private/internal to the site it was built for unless a license is added.

[![Patreon](https://c5.patreon.com/external/logo/become_a_patron_button.png)](https://www.patreon.com/cw/shibisty)

If this project helps you, consider supporting its development on Patreon ❤️