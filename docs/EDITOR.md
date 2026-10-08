# Editing SEO settings for a post

## Block editor

Open **DumpSEO** from the magnifying-glass icon in the editor toolbar, or from the ⋮ menu → *DumpSEO*.

| Panel | What it does |
|---|---|
| Search appearance | Preview of the search result (title and description exactly as they will be printed, with templates and `%%variables%%` filled in), focus keyphrase, SEO title and meta description with length hints. A notice appears when the page is hidden from search engines. |
| SEO analysis | The [SEO checks](ANALYSIS.md) for the current, unsaved text. Updates about a second after you stop typing. |
| Readability | The [readability checks](READABILITY.md). |
| Social sharing | Title, description and image used when the page is shared (image from a URL or the media library). |
| Advanced | Show or hide in search results, nofollow / noarchive / nosnippet / noimageindex, canonical URL. |

Everything is saved when you save or update the post, like any other post setting. Running the analysis never saves anything.

## Classic Editor

When a post is edited with the Classic Editor, the same settings appear in a **DumpSEO** box below the content. The analysis runs when the page opens, about 1.5 seconds after you change the keyphrase, SEO title, description or post title, and whenever you click **Check SEO and readability** (use this after editing the content).

## Which post types

Posts, pages and every public custom post type with an admin screen, except media. Developers can change the list with the `dumpseo_editor_post_types` filter.

## Permissions

Only users who can edit a post see and change its SEO settings, and only for that post. Analysis requests are checked the same way.

## Accessibility

Every result says what it is in words (Problem, Improvement, Note, Good); the symbols and colors only repeat that. Fields have visible labels and descriptions linked with `aria-describedby`.
