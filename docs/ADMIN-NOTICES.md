# Admin notices and dashboard UI

WordPress.org guideline 11: plugins must not hijack the admin dashboard; notices must be limited in scope and used with moderation. This file lists every notice-like element the plugin prints, where, to whom and why. Audit date: 2026-10-01 (version 1.0.1); unchanged in 1.0.2 (rename only).

## Rules

1. Nothing is printed on screens the plugin does not own, with one exception: the "requirements not met" message on the Plugins screen.
2. No upgrade prompts, review requests, promotions, banners, pointers, welcome screens or activation redirects.
3. Every message reports a state the reader can act on, and disappears when the state does.
4. Nothing is shown to users who cannot act on it.

## Inventory

| # | Element | Code | Screen | Who sees it | Trigger | Repeats? |
|---|---|---|---|---|---|---|
| 1 | "Plugin is inactive: requires PHP 7.4 and WordPress 6.4" (error) | `Requirements::render_notice()`, hooked on `admin_notices` and `network_admin_notices` in `dumpseo.php` | **Plugins** and **Network Admin → Plugins** only | `activate_plugins` | PHP or WordPress below the minimum, so the plugin did not boot | While the condition lasts, on that screen only. WordPress itself normally refuses activation from the `Requires PHP` / `Requires at least` headers, so this is a fallback (for example after a PHP downgrade). |
| 2 | "The redirect was saved as a draft and is not active" (error, with reasons) | `Redirects\AdminScreen::render_errors()` on `admin_notices` | Redirect list and redirect edit screens only (`dumpseo_redirect` post type) | `manage_options`, and only the user who made the save | A redirect failed validation on save | Once. Stored for 60 seconds per user and deleted when shown. |
| 3 | Settings validation errors | `settings_errors( Settings::OPTION )` in `SettingsPage::render_page()` | Plugin settings screen | `manage_options` | A submitted value was rejected | Once, after that save (WordPress Settings API). |
| 4 | "Search engines are asked not to index this site" (warning, inline) | `SettingsPage::render_page()` | Plugin settings screen | `manage_options` | Settings → Reading → "Discourage search engines" is on | While the condition lasts, inside the page. |
| 5 | "Another SEO plugin already prints social tags" (info, inline) | `Social\SocialModule::render_notice()` on `dumpseo_settings_section_social` | Plugin settings screen, Social section | `manage_options` | Another SEO plugin is active | While the condition lasts, inside the section. |
| 6 | "Another SEO plugin already prints structured data" (info, inline) | `Schema\SchemaModule::render_notice()` on `dumpseo_settings_section_schema` | Plugin settings screen, Structured data section | `manage_options` | Another SEO plugin is active | While the condition lasts, inside the section. |

Items 3–6 are printed inside the plugin's own page markup (`class="notice … inline"`), not through the global notice hooks, so they cannot appear anywhere else.

No notice is dismissible, because none persists beyond the condition or the single display; there is no dismissal state to store.

## Other admin UI outside the plugin's screens

Not notices, listed for completeness:

| Element | Screen | Notes |
|---|---|---|
| "Settings" link in the plugin's own row | Plugins | `manage_options` only. |
| "Alt text" column and "Missing alt text" filter | Media Library (list view) | Read-only. |
| SEO fields | Post editor (sidebar / Classic Editor box), term edit screens | Only for users who can edit that post or term. |

The plugin adds no dashboard widget, admin bar item, admin pointer, or anything on the Dashboard, Updates, WooCommerce or Network Admin screens (other than item 1 on Network Admin → Plugins).

## What changed in 1.0.1

- Item 1 was printed on **every** admin screen. It is now limited to the Plugins screens.
- Item 2 was already limited to the redirect screens; it now also checks the capability explicitly.

## Tests

- `tests/Unit/RequirementsTest.php` — notice prints on `plugins` / `plugins-network` only; not on dashboard, posts, pages, updates, WooCommerce, network dashboard; not without the capability.
- `tests/Integration/AdminNoticesTest.php` — on real WordPress (single site and multisite): a running plugin hooks nothing on `admin_notices`, `all_admin_notices`, `network_admin_notices` or `user_admin_notices` except the redirect errors callback; that callback prints nothing on 15 foreign screens (Dashboard, Posts, Pages, Media, Plugins, Updates, Users, Settings, WooCommerce, Network Admin) and keeps the message for the redirect screen; it prints once, escaped, only to the saving administrator.
