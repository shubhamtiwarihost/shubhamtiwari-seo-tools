# Changelog

All notable changes are documented here. Format: [Keep a Changelog](https://keepachangelog.com/); versions follow [SemVer](https://semver.org/).

## [1.0.2] — 2026-10-08
Submission under the slug assigned by the WordPress.org Plugins Team. Not yet approved or published; no earlier version was distributed.

### Changed
- **Renamed** from ShubhamTiwari SEO Tools to **DumpSEO**. Slug and text domain `dumpseo`; main file `dumpseo.php`; translation template `languages/dumpseo.pot`; PHP namespace `DumpSEO\`; prefix for options, meta keys, hooks, functions, REST routes, the block, script handles and CSS classes `dumpseo` (constants `DUMPSEO_`); admin menu label "DumpSEO". Every identifier named in the notes below is listed under its new name. No migration from the old names: no release with them was ever distributed.
- No functional changes.

## [1.0.1] — 2026-10-01
Corrected submission after the WordPress.org pre-review. Not yet approved or published; 1.0.0 was never distributed.

### Changed
- **Renamed** from SEOEarth to **ShubhamTiwari SEO Tools**. Slug and text domain `shubhamtiwari-seo-tools`; main file `shubhamtiwari-seo-tools.php`; PHP namespace `ShubhamTiwariSeoTools\`; prefix for options, meta keys, hooks, functions, REST routes, the block, script handles and CSS classes `stseo` (constants `STSEO_`). Every identifier named in the 1.0.0 notes below is listed under its new name. No migration from the old names: no release with them was ever distributed.
- Admin menu label is "SEO Tools".
- Admin notices (directory guideline 11, see docs/ADMIN-NOTICES.md): the "requirements not met" message is printed on the Plugins screens only instead of every admin screen; the redirect validation message also checks the capability. Scope is covered by `tests/Integration/AdminNoticesTest.php`.
- Repository moved to https://github.com/shubhamtiwarihost/shubhamtiwari-seo-tools.

## [1.0.0] — 2026-09-30 (submitted as "SEOEarth", not approved)
### Fixed
- Editor sidebar: analysis failed on new posts before a title was typed (the editor reports a numeric slug).
- Plugin Check: removed a redundant `suppress_filters` and a `post__not_in` query.

### Added
- Source repository published at https://github.com/shubhamtiwarihost/shubhamtiwari-seo-tools and linked from readme.txt.
- Official PHP support 7.4, 8.0, 8.1, 8.2 and 8.3 (minimum unchanged); CI unit matrix covers all five, and `bin/test-integration-php.sh` runs the integration suite on any of them (CI runs it on PHP 7.4). readme.txt FAQ lists the supported versions.
- WordPress.org preparation: original JavaScript source (`assets-src/`) and build definition (`package.json`, `babel.config.js`) shipped in the ZIP with build instructions in readme.txt (guideline 4); five screenshots of DumpSEO' own UI in `.wordpress-org/` (not in the ZIP); name/trademark research notes (docs/NAME-AND-TRADEMARK.md, not legal advice). Plugin URI header removed until the directory page exists; Contributors set to the owner's WordPress.org username (shubhamtiwarihost).
- Release readiness: complete readme.txt (description, FAQ, privacy), translation template `languages/dumpseo.pot`, version check covers composer.json, build config files excluded from the ZIP.
- Service container with extension hook `dumpseo_container`.
- Request `Context` service.
- Versioned data migrations with resume-on-failure and a concurrency lock; `dumpseo_installed` / `dumpseo_upgraded` actions.
- Multisite-aware uninstall; multisite integration test run in CI.
- Settings framework: typed field schema (`dumpseo_settings_fields` filter), sanitizer, settings screen (DumpSEO menu, administrators only), "Settings" link on the Plugins screen.
- Initial settings: title separator, site represents organization/person, name, logo, default sharing image, X username, opt-in data removal.
- SEO titles and meta descriptions for posts, pages, custom post types, terms, archives, search and 404, with `%%variable%%` templates (see docs/TEMPLATES.md).
- Per-post SEO title/description meta, available to the block editor through the REST API with per-post permission checks.
- SEO title/description fields on category, tag and custom taxonomy edit screens.
- Search appearance settings: one title and description template per page type.
- Canonical URLs on all indexable pages (replaces core's singular-only tag; no duplicates, no tracking parameters, self-referencing pagination) with per-post/per-term override.
- Robots meta through core's `wp_robots`: search and 404 noindex, per-page-type noindex switches (off by default), per-post/per-term index/noindex, nofollow, noarchive, nosnippet, noimageindex.
- Settings screen warns when WordPress is set to discourage search engines.
- XML sitemap improvements on top of WordPress core sitemaps: noindex, canonicalised-elsewhere and password-protected content left out; noindex post types/taxonomies removed (explicit "index" items kept); author sitemap follows author-archive indexing; lastmod on WordPress 6.4; image entries (featured + same-site content images). Settings: sitemap, images, authors.
- Open Graph and X Card tags: title/description/image fallbacks, image dimensions/type/alt for media-library images, article times, size-aware X card type, per-post/per-term overrides (term screen fields), site default image and X username, separate on/off switches. Steps aside when another SEO plugin prints social tags; turns off Jetpack's duplicate Open Graph tags.
- WooCommerce (loaded only when WooCommerce is active): cart, checkout and account pages noindex and out of the sitemap; products get `og:type` product with price, currency and availability, no Article structured data (WooCommerce's Product data is kept) and WebPage type ItemPage; breadcrumbs Home › Shop › product categories › product; WooCommerce's duplicate BreadcrumbList/WebSite blocks are turned off while DumpSEO prints its graph.
- New filters: `dumpseo_robots_directives`, `dumpseo_sitemap_excluded_posts`, `dumpseo_schema_webpage_type`.
- Redirects (basic): DumpSEO → Redirects, administrators only. 301, 302, 307 and 410; case-insensitive path matching with query strings passed on; validation on save (path on this site, never the homepage/dashboard/login/REST API; valid target; no duplicate; no loops or chains over 10 steps) — invalid redirects are saved inactive with the reason shown. Frontend matching runs before the main query from an autoloaded index (0 extra queries), for GET/HEAD requests only. Removed on uninstall when data removal is opted in.
- Image SEO: "Alt text" column and "Missing alt text" filter in the Media Library list view; the DumpSEO settings screen reports how many images lack alt text and links to that list. Read-only: DumpSEO never changes media. Media library picker for the default sharing image and the logo.
- Breadcrumbs (opt-in): "Breadcrumbs" block (with color, typography and spacing support), `[dumpseo_breadcrumbs]` shortcode, and `dumpseo_breadcrumbs()` / `dumpseo_get_breadcrumbs()` template functions. Accessible markup (labelled `<nav>`, ordered list, `aria-current`, separators hidden from screen readers); same trail as the structured data. Settings: home label, separator.
- Block editor sidebar (DumpSEO, from the toolbar or the ⋮ menu): search result preview with the title and description as they will be printed, focus keyphrase, SEO title and description with length hints, live SEO and readability results (debounced, unsaved content), social sharing fields with media library picker, search visibility and canonical URL. Built to run on WordPress 6.4+ (classic JSX runtime).
- Classic Editor metabox with the same fields, preview and analysis.
- Supported post types (public, with UI, not media; `dumpseo_editor_post_types` filter) get "custom-fields" support so SEO meta is available over REST.
- Term SEO fields now keep backslashes when saved.
- Readability analysis: sentence length, paragraph length, subheading distribution (any language); passive-voice indicators, transition words and Flesch reading ease (English). Returned by the analysis endpoint as a separate `readability` report; rules extensible via `dumpseo_readability_rules`, transition words via `dumpseo_transition_words`, content language via `dumpseo_content_locale`.
- SEO analysis: 16 original checks (keyphrase set reminder, focus keyphrase in title, description, slug, first paragraph and subheadings; keyphrase use per 100 words; keyphrase already used elsewhere; title, description and text length; internal and outbound links; image alt text; h1 in content; noindex notice). Each result has status, severity, message, recommendation and metadata; no numeric score. `POST /dumpseo/v1/analysis` analyses a post with optional unsaved editor values (requires `edit_post`, saves nothing). Focus keyphrase stored per post (`_dumpseo_focus_keyphrase`, REST meta). Rules extensible via `dumpseo_analysis_rules`.
- Structured data: one schema.org JSON-LD `@graph` per page — Organization or Person (from Site identity settings), WebSite with site search, WebPage/CollectionPage/ProfilePage, featured ImageObject, BreadcrumbList, BlogPosting/Article with author Person. Extensible piece registry (`dumpseo_schema_pieces`); references to removed pieces are dropped. On/off setting; steps aside when another SEO plugin prints structured data. Password-protected posts expose no text or image; noindex, search and 404 pages carry only site-level nodes.

### Changed
- Uninstall deletes settings and the data version only when "Remove all DumpSEO data" is ticked.
- `dumpseo_db_version` is now autoloaded (it is read on every request).
- Uninstall (with opt-in) also removes per-post and per-term SEO fields.

### Fixed
- Text settings no longer lose `%xx` sequences: `sanitize_text_field()` treated `%%description%%` and `%%date%%` as URL-encoded bytes.

## [0.1.0] — development scaffold (not released)
### Added
- Plugin bootstrap, PSR-4 autoloader, module registry (`dumpseo_modules` filter), PHP/WordPress requirement check.
- Tooling: PHPCS (WPCS 3 + PHPCompatibilityWP), PHPStan level 6, PHPUnit + Brain Monkey, wp-env, @wordpress/scripts, GitHub Actions CI.
