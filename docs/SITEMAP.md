# XML sitemap

DumpSEO builds on the sitemap included in WordPress (5.5+) instead of replacing it.

- Index: `/wp-sitemap.xml` (linked from `robots.txt` automatically).
- Pages: `/wp-sitemap-posts-post-1.xml`, `/wp-sitemap-taxonomies-category-1.xml`, `/wp-sitemap-users-1.xml`, … (2,000 URLs per page, set by WordPress).

## What DumpSEO changes

| | WordPress alone | With DumpSEO |
|---|---|---|
| Post types / taxonomies set to noindex | listed | left out (unless some items are explicitly set to "Show in search results") |
| Posts / terms set to noindex | listed | left out |
| Posts / terms whose custom canonical points to another URL | listed | left out |
| Password-protected posts | listed | left out |
| Author sitemap | listed | removed when author archives are noindex or "Include author archives" is off |
| `<lastmod>` | WordPress 6.5+ | also on 6.4 |
| Images | — | featured image + up to 10 images from this site in the content (`<image:image>`) |

Always decided by WordPress: when the site is set to "Discourage search engines" there is no sitemap at all; DumpSEO never turns it back on.

## Settings (DumpSEO → XML sitemap)

| Setting | Default |
|---|---|
| Enable the XML sitemap | on |
| Include images | on |
| Include author archives | on |

## Images

- Featured image first, then `<img>` tags in the content, in order, duplicates removed, maximum 10.
- Only images on this site's own host (home URL or uploads URL) are listed; root-relative `src="/wp-content/…"` is made absolute. Add a CDN host with the `dumpseo_sitemap_image_hosts` filter.
- Change the list per post with `dumpseo_sitemap_images`.

## Performance (measured, Phase 6)

2,000-post site, 200 posts with featured images, median of 7 requests, Docker on a laptop:

| Request | WordPress alone | With DumpSEO |
|---|---|---|
| Sitemap page (2,000 URLs) | 11 queries, 71 ms | 18 queries, 119 ms |
| Sitemap index | 21 queries, 19 ms | 33 queries, 25 ms |

Extra queries are constant (they do not grow with the number of posts — covered by an automated test). Featured images for a whole page are loaded in two batched queries.
