# What DumpSEO stores

Kept up to date with every phase. Used for the privacy section of readme.txt and for uninstall behaviour.

## Options (per site; `wp_options`, or `wp_N_options` on multisite)

| Option | Autoload | Contents | Created | Deleted on uninstall |
|---|---|---|---|---|
| `dumpseo_settings` | yes | Site-wide settings (see table below) | First time settings are saved | Only if "Remove all DumpSEO data" is ticked |
| `dumpseo_db_version` | yes | Data version string, e.g. `0.1.0` | First request after activation | Only if "Remove all DumpSEO data" is ticked (kept otherwise so a reinstall upgrades correctly) |
| `dumpseo_redirect_index` | yes (up to 500 redirects) | Active redirects: source path => post ID, target, type. Rebuilt from the redirect posts on every change | Install, and whenever a redirect changes | Only if "Remove all DumpSEO data" is ticked |
| `dumpseo_migration_lock` | no | Unix timestamp; exists only while an upgrade runs | During upgrades | Always |

### `dumpseo_settings` keys

| Key | Type | Default |
|---|---|---|
| `separator` | one of `hyphen`, `ndash`, `mdash`, `pipe`, `middot`, `bullet`, `raquo` | `ndash` |
| `site_represents` | `organization` or `person` | `organization` |
| `organization_name` | plain text | empty (site title is used) |
| `organization_logo` | http(s) URL (media library picker on the settings screen) | empty |
| `default_social_image` | http(s) URL | empty |
| `twitter_site` | X/Twitter handle without `@` | empty |
| `sitemap_enabled` / `sitemap_images` / `sitemap_users` | boolean | `true` |
| `social_og_enabled` / `social_twitter_enabled` | boolean | `true` |
| `schema_enabled` | boolean | `true` |
| `breadcrumbs_home` | plain text | empty ("Home") |
| `breadcrumbs_separator` | plain text | `›` |
| `remove_data_on_uninstall` | boolean | `false` |

## Redirect posts

Redirects are posts of type `dumpseo_redirect` (title = old path, `_dumpseo_redirect_target` and `_dumpseo_redirect_type` meta; published = active, draft = inactive). Only administrators can see or change them; they are not public and not available over the REST API. Deleted on uninstall only if "Remove all DumpSEO data" is ticked.

## Personal data
None. DumpSEO does not store information about visitors or users, sets no cookies, and makes no outbound HTTP requests.

Template settings (`title_*` / `desc_*` keys) and page-type indexing switches (`noindex_*` keys, default off) are also stored in `dumpseo_settings`; see [TEMPLATES.md](TEMPLATES.md) and [INDEXING.md](INDEXING.md).

## Post meta / term meta

| Key | Stored on | Contents | Who can change it | Deleted on uninstall |
|---|---|---|---|---|
| `_dumpseo_title` | posts (any type), terms | Custom SEO title, single-line text, may contain `%%variables%%` | Posts: users who can `edit_post` that post. Terms: users who can `edit_term` | Only if "Remove all DumpSEO data" is ticked |
| `_dumpseo_description` | posts (any type), terms | Custom meta description | same | same |
| `_dumpseo_canonical` | posts (any type), terms | Custom canonical URL, absolute http(s) only | same | same |
| `_dumpseo_robots` | posts (any type), terms | Comma-separated robots tokens from a fixed allowlist | same | same |
| `_dumpseo_social_title` | posts (any type), terms | Social sharing title; may contain `%%variables%%` | same | same |
| `_dumpseo_social_description` | posts (any type), terms | Social sharing description | same | same |
| `_dumpseo_social_image` | posts (any type), terms | Social sharing image URL, absolute http(s) only | same | same |
| `_dumpseo_focus_keyphrase` | posts (any type), terms | Focus keyphrase for the SEO analysis, plain text | same | same |

Keys start with `_`, so they are hidden from the Custom Fields box. Because core only exposes registered post meta over REST for post types that support "custom-fields", DumpSEO adds that support to the post types it edits (this adds no data). They are exposed in the REST API (`meta` field) for the block editor, subject to the capability checks above; the REST API does not show them for posts the requester cannot read.
