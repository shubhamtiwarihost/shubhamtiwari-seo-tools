# Breadcrumbs

Breadcrumbs show visitors where a page sits on the site: **Home › Guides › Boots › Choosing boots**. DumpSEO prints them only where you put them.

## Adding breadcrumbs

| Where | How |
|---|---|
| Block themes (site editor) or any post/page | Insert the **Breadcrumbs** block. Put it in a template (for example Single Posts) to show it on every post. Color, font size and spacing can be set in the block settings. |
| Classic themes | Add to a template file, e.g. `single.php`: `<?php if ( function_exists( 'dumpseo_breadcrumbs' ) ) { dumpseo_breadcrumbs(); } ?>` |
| Widgets, page builders, anywhere shortcodes work | `[dumpseo_breadcrumbs]` |

`dumpseo_get_breadcrumbs()` returns the HTML instead of printing it.

In the editor, the block shows a sample trail. The real trail is built for each page when it is viewed.

## What the trail contains

| Page | Trail |
|---|---|
| Post | Home › first category (and its parent categories) › post |
| Page | Home › parent pages › page |
| Other post types | Home › post type archive (if it has one) › item |
| Category, tag, custom taxonomy | Home › parent terms › term |
| Author, date, post type archive | Home › archive |
| Search results | Home › Search results for “…” |
| 404 | Home › Page not found |
| Homepage | Nothing is printed |

The same trail is used for the `BreadcrumbList` in [structured data](SCHEMA.md), so what visitors see and what search engines read always match.

## Settings (DumpSEO → Breadcrumbs)

| Setting | Default |
|---|---|
| Label for the homepage | "Home" |
| Separator | › |

## Markup and accessibility

```html
<nav class="dumpseo-breadcrumbs" aria-label="Breadcrumbs">
  <ol class="dumpseo-breadcrumbs__list">
    <li class="dumpseo-breadcrumbs__item"><a href="…">Home</a><span class="dumpseo-breadcrumbs__separator" aria-hidden="true">›</span></li>
    <li class="dumpseo-breadcrumbs__item"><span aria-current="page">Choosing boots</span></li>
  </ol>
</nav>
```

The separator is hidden from screen readers; the current page is marked with `aria-current="page"` and is not a link. DumpSEO adds only layout CSS (one line, only on pages that show breadcrumbs); colors and fonts come from your theme.

Developers can change the trail with the `dumpseo_breadcrumb_trail` filter (it affects both the visible breadcrumbs and the structured data).
