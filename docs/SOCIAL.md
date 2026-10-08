# Social sharing (Open Graph and X Cards)

DumpSEO adds the tags that decide how a link looks when it is shared on Facebook, LinkedIn, WhatsApp, Slack, Discord, X and similar apps.

## Where each value comes from

| Tag | Source, first match wins |
|---|---|
| Title (`og:title`, `twitter:title`) | Social title on the post/term → SEO title → post/term name |
| Description | Social description on the post/term → meta description |
| Image | Social image URL on the post/term → featured image → *Default sharing image* setting |
| URL (`og:url`) | Canonical URL (omitted on noindex pages) |
| `og:type` | `article` for posts and custom post types; `website` for pages, homepage, archives |

- Custom social titles and descriptions may use `%%variables%%` (see [TEMPLATES.md](TEMPLATES.md)).
- For media-library images DumpSEO also prints width, height, type and the image's alt text. For other image URLs these are left out rather than guessed.
- X card type: `summary_large_image` when the image is at least 300 × 157 px (or its size is unknown); otherwise `summary`.
- Articles get `article:published_time` and `article:modified_time`.
- Password-protected posts never share their featured image or text; the default image is used.
- No social tags on search results and 404 pages.

## Settings (DumpSEO → Social sharing)

| Setting | Default |
|---|---|
| Add Open Graph tags | on |
| Add X (Twitter) Card tags | on |
| Default sharing image URL | empty |
| X username (`twitter:site`) | empty |

Per-term overrides are on the category/tag edit screen. Per-post overrides are stored now (`_dumpseo_social_*`, available over REST) and get editor controls in the block-editor phase.

## Avoiding duplicate tags

If another SEO plugin that prints social tags is active (Yoast SEO, Rank Math, All in One SEO, SEOPress, The SEO Framework, Slim SEO), DumpSEO prints none and explains why on its own settings screen. While DumpSEO prints Open Graph tags it asks Jetpack (through Jetpack's `jetpack_enable_open_graph` filter) not to print its own.

Developer filters: `dumpseo_social_output_enabled`, `dumpseo_social_conflict`, `dumpseo_social_tags`, `dumpseo_social_image`, `dumpseo_og_is_article`.

## Cost (measured, Phase 7)

Single post with a featured image and the homepage: 0 extra database queries, about +0.14 MB peak memory.
