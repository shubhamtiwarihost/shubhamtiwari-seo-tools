# Titles, descriptions and template variables

## Where a title or description comes from

For every frontend page DumpSEO decides, in order:

1. **Custom value** saved on the post, page or term (SEO title / meta description fields). May contain variables.
2. **Template** from *DumpSEO → Search appearance* for that kind of page.
3. If the result is empty: the **title** falls back to WordPress's own title; **no description** tag is printed.

| Page | Custom value from | Template setting |
|---|---|---|
| Single post / page / custom post type | that post | `<Type>: title` / `<Type>: meta description` |
| Front page, latest posts | — | Homepage |
| Front page, static page | that page | Homepage |
| Posts page (static "blog" page) | that page | Pages |
| Category / tag / custom taxonomy archive | that term | `<Taxonomy>` |
| Post type archive | — | `<Type> archive` |
| Author archive | — | Author archives |
| Date archive | — | Date archives |
| Search results | — | Search results (title only) |
| 404 | — | Page not found (title only) |

## Variables

| Variable | Value |
|---|---|
| `%%title%%` | Post title, term name, author name, post type name, date label or search phrase — whatever the page is about |
| `%%site_name%%` | Site title (Settings → General) |
| `%%sitedesc%%` | Site tagline |
| `%%separator%%` | Separator chosen in DumpSEO settings |
| `%%excerpt%%` | Manual excerpt, otherwise the start of the content, plain text, max 155 characters (cut at a word). Empty for password-protected posts. |
| `%%description%%` | Term description, author biography or post type description |
| `%%category%%` | First category of the post (alphabetical); the term name on category archives |
| `%%author%%` | Post author, or the author of an author archive |
| `%%date%%` | Post publish date (site date format), or the date archive label |
| `%%page%%` | "Page 2 of 5" on page 2 and later; empty on page 1 |
| `%%searchphrase%%` | Search terms |
| `%%pt_singular%%`, `%%pt_plural%%` | Post type name |
| `%%currentyear%%` | Current year |

Developers can add variables with the `dumpseo_template_variables` filter (values: string or closure returning string).

## Rendering rules

- Variables are replaced in one pass. Text coming *from* a variable is never scanned again, so a post titled `%%site_name%%` is shown literally.
- Unknown variables are removed.
- Values are converted to plain text (HTML removed, entities decoded); output is escaped for the `<title>` element or the `content` attribute.
- When a variable is empty, neighbouring separators collapse: `%%title%% %%separator%% %%page%% %%separator%% %%site_name%%` renders "Boots – Acme" on page 1 and "Boots – Page 2 of 3 – Acme" on page 2. Separator characters that are part of a real title are left alone.
- Titles are never truncated (search engines decide what to display). Only the automatic `%%excerpt%%` is shortened.

## Output

- `<title>`: through core's `pre_get_document_title` (themes with `title-tag` support — all block themes) and `wp_title` (older themes). Feed titles are not changed.
- `<meta name="description">`: printed early in `wp_head`.
- Disable both with `add_filter( 'dumpseo_head_output_enabled', '__return_false' );`.
