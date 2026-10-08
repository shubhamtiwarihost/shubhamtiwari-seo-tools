=== DumpSEO ===
Contributors: shubhamtiwarihost
Tags: seo, xml sitemap, schema, open graph, breadcrumbs
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Titles, meta descriptions, sitemaps, social cards, structured data, redirects and content analysis. Private by design: no tracking, no external calls.

== Description ==

DumpSEO helps search engines and social networks understand your site, and helps you write content people can find. It is built on WordPress's own features (the core sitemap, the robots API, the block editor) and stays out of the way on the pages your visitors load.

= Search appearance =

* SEO titles and meta descriptions for posts, pages, custom post types, categories, tags, archives, search and 404 pages.
* Templates per content type with variables such as `%%title%%`, `%%site_name%%`, `%%category%%` and `%%excerpt%%`.
* Canonical URLs on every indexable page, including correct pagination.
* Search engine visibility per page and per content type (noindex, nofollow, noarchive, nosnippet, noimageindex).

= Sitemap =

* Improves the XML sitemap built into WordPress: hidden pages, password-protected posts and pages that point elsewhere are left out, and images and last-modified dates are added.

= Social sharing =

* Open Graph and X (Twitter) Card tags with title, description and image fallbacks, image sizes and alt text, and per-post overrides.

= Structured data =

* One connected schema.org graph per page: Organization or Person, WebSite, WebPage, BlogPosting/Article with author, featured image and BreadcrumbList.

= Content analysis in the editor =

* A sidebar in the block editor (and a box in the Classic Editor) with a search result preview.
* 16 SEO checks (most around a focus keyphrase), and 6 readability checks including Flesch reading ease (English).
* Every finding says what was found and what to do about it. There is no score: the checks are writing guidance, not a ranking promise.

= More =

* Breadcrumbs as a block, shortcode or theme function, matching the structured data.
* Redirects (301, 302, 307, 410) with loop protection, for administrators.
* Images without alternative text, listed in the media library.
* WooCommerce: product sharing tags, shop breadcrumbs, and cart/checkout pages kept out of search.

= Privacy and performance =

* No telemetry, no tracking, no calls to external services, no cookies.
* Measured, not claimed: on normal pages DumpSEO adds no extra database queries.
* Accessible: results are always stated in words, never by color alone.

If another SEO plugin is active, DumpSEO stops printing social tags and structured data and says so, so nothing is duplicated.

= Source code and build tools =

The development repository, with the full source code and history, is https://github.com/shubhamtiwarihost/shubhamtiwari-seo-tools

All PHP, CSS and the Classic Editor/settings JavaScript are shipped as written. The block editor sidebar and the Breadcrumbs block are compiled; their original, human-readable source is included in the plugin in `assets-src/`, and the compiled files are in `build/`.

To rebuild `build/` from `assets-src/`: install Node.js 20 or newer, then run `npm install` and `npm run build` in the plugin folder. The build uses the official WordPress tooling, `@wordpress/scripts` (see `package.json` for the exact commands and versions, and `babel.config.js`, which compiles JSX so the scripts also run on WordPress 6.4).

== Installation ==

1. Install DumpSEO from the Plugins screen (search for "DumpSEO"), or upload the `dumpseo` folder to `/wp-content/plugins/`.
2. Activate the plugin.
3. Go to **DumpSEO** in the admin menu to check the site identity (organization or person, logo), title separator and defaults. Everything works with the defaults.
4. Edit any post and open the **DumpSEO** sidebar from the toolbar to see the preview and analysis.

== Frequently Asked Questions ==

= Does DumpSEO replace the WordPress sitemap? =

No. It improves the sitemap WordPress already provides at `/wp-sitemap.xml`, so it stays compatible with everything that expects it.

= Will DumpSEO conflict with another SEO plugin? =

Running two SEO plugins is not recommended. If Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework or Slim SEO is active, DumpSEO does not print social tags or structured data, and shows a notice on its settings screen.

= Does the analysis guarantee better rankings? =

No. The checks reflect common, documented writing and on-page practices. Nobody can guarantee rankings, which is why DumpSEO shows findings rather than a score.

= Which PHP versions are supported? =

PHP 7.4, 8.0, 8.1, 8.2 and 8.3, with WordPress 6.4 or newer. Newer PHP versions will be listed once they have been tested.

= Which languages does the readability analysis support? =

Sentence length, paragraph length and subheading checks work for every language. Passive voice, transition words and reading ease are English-only for now.

= How do I show breadcrumbs? =

Add the **Breadcrumbs** block to a template or post, use the `[dumpseo_breadcrumbs]` shortcode, or call `dumpseo_breadcrumbs()` in a theme template.

= What happens to my data if I delete the plugin? =

By default your settings and SEO fields are kept, so reinstalling restores them. To remove everything, tick "Remove all DumpSEO data when the plugin is deleted" under DumpSEO → Advanced before deleting. Your posts and pages are never deleted.

== Screenshots ==

1. The DumpSEO sidebar in the block editor: search result preview, focus keyphrase, SEO title and meta description with length hints.
2. SEO analysis results in the sidebar. Every finding says what was found and what to do, in words.
3. DumpSEO settings: site identity, title separator and search appearance templates.
4. Redirects: old and new addresses, redirect type and status.
5. The media library lists images without alternative text.

== Privacy ==

DumpSEO does not collect, store or send any personal data about visitors or users. It sets no cookies, loads nothing from third-party servers and makes no outbound requests. It stores only settings and the SEO fields you enter for your content (titles, descriptions, keyphrases, social fields, robots settings, redirects).

== Changelog ==

= 1.0.2 =
* First public release: search appearance, canonical and robots, sitemap improvements, social tags, structured data, SEO and readability analysis, block editor sidebar and Classic Editor box, breadcrumbs, image alt text report, redirects and WooCommerce support.

== Upgrade Notice ==

= 1.0.2 =
First public release.
