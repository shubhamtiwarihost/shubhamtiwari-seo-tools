# Canonical URLs and search engine indexing

## Canonical URL

DumpSEO prints one `<link rel="canonical">` per page and removes WordPress's own (which only covers single posts), so there is never a duplicate.

| Page | Canonical |
|---|---|
| Post, page, custom post type | Its permalink (page 2+ of a `<!--nextpage-->` post includes the page number) |
| Homepage, archives (category, tag, taxonomy, author, date, post type) | The archive URL; page 2+ is canonical to itself, e.g. `/category/news/page/2/` |
| Search results, 404 | None |
| Any page marked noindex | None (a canonical on a noindex page sends mixed signals) |

- The canonical is built from WordPress's permalink functions, never copied from the address bar, so tracking parameters like `?utm_source=` never appear in it.
- **Custom canonical:** a post, page or term can point to another URL (for content duplicated elsewhere). It must be a full `https://` or `http://` address; anything else is rejected.
- Developers: `dumpseo_canonical` filter (return `''` to print none).

## Search engine indexing (robots meta)

DumpSEO adds directives to WordPress's single robots tag (core `wp_robots`); it never prints a second one.

**Defaults: nothing is hidden.** Search results and 404 pages are always `noindex`.

**Per page type** (*DumpSEO → Search appearance*): "hide from search engines (noindex)" for each post type, taxonomy, author archives and date archives. The homepage has no such switch on purpose.

**Per post / term:**

| Setting | Effect |
|---|---|
| Default | Follow the page-type setting |
| Show in search results (index) | Overrides a page-type noindex |
| Hide from search results (noindex) | Hides this item only |
| nofollow / noarchive / nosnippet / noimageindex | Added on top |

**WordPress's "Discourage search engines from indexing this site"** (Settings → Reading) always wins: DumpSEO never removes core's noindex. The DumpSEO settings screen shows a warning while it is on.

Stored as `_dumpseo_robots`, a comma-separated list limited to `index, noindex, nofollow, noarchive, nosnippet, noimageindex`; anything else is discarded, and `noindex` wins over `index`.
