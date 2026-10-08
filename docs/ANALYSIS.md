# SEO analysis

DumpSEO checks a post against common on-page SEO practices and explains each finding in plain words. The checks are editorial guidance: they do not measure or promise rankings, so there is no overall score, only a list of findings.

Each finding has:

| Field | Meaning |
|---|---|
| `status` | `pass`, `warning`, `error` or `info` (info = worth knowing, not a problem) |
| `severity` | `high`, `medium` or `low`: how much the check matters |
| `message` | What was found |
| `recommendation` | What to do (empty on a pass) |
| `metadata` | Measured values, e.g. `length`, `count`, `density` |

## Checks

Checks marked * need a focus keyphrase. Matching ignores case, treats hyphens as spaces and curly apostrophes as straight ones, and matches whole words only. Word forms (plural, past tense) are not yet recognised.

| ID | Checks | Pass | Warning / error |
|---|---|---|---|
| `keyphrase_set` | A focus keyphrase is set | — | info when missing |
| `keyphrase_in_title`* | Keyphrase in the SEO title | In the first half | warning in the second half; error when absent |
| `keyphrase_in_description`* | Keyphrase in the meta description | Present | warning |
| `keyphrase_in_slug`* | Every keyphrase word in the URL slug | Present | warning |
| `keyphrase_in_intro`* | Keyphrase in the first paragraph | Present | warning |
| `keyphrase_density`* | Uses per 100 words (texts of 100+ words) | 0.5–3 | warning below 0.5 or 3–4.5; error above 4.5 |
| `keyphrase_in_subheadings`* | Keyphrase in an h2–h6 (when there are any) | At least one | warning |
| `keyphrase_unique`* | Other published posts with the same keyphrase | None | warning, lists up to 5 IDs |
| `title_length` | SEO title characters | 30–60 | warning outside; error when empty |
| `description_length` | Meta description characters | 120–160 | warning outside or when empty |
| `content_length` | Words in the text | 300+ | warning under 300, error under 150; pages get info (short pages are often intentional) |
| `internal_links` | Links to this site | 1+ | warning |
| `outbound_links` | Links to other sites | 1+ | info |
| `image_alt` | Images with alt text (when there are images) | All | warning with count |
| `content_h1` | h1 headings inside the text | — | warning (the theme already shows the title as h1) |
| `indexable` | Page is noindex | — | info |

Title and description are checked exactly as they will be printed: custom values or page-type templates with `%%variables%%` rendered. Character counts approximate what search engines show; they actually truncate by pixel width.

The text is analysed as stored in the editor. Content produced at display time (shortcodes, dynamic blocks) is not included.

## REST API

`POST /wp-json/dumpseo/v1/analysis`. Requires a logged-in user who can edit the post. Nothing is saved.

| Parameter | Required | Meaning |
|---|---|---|
| `post_id` | yes | Post to analyse |
| `keyphrase`, `title`, `excerpt`, `content`, `slug`, `seo_title`, `seo_description` | no | Unsaved editor values; saved values are used for anything left out |

Response: `{ "seo": report, "readability": report, "preview": { "title": "…", "description": "…" } }` (preview = title and description exactly as they will be printed), where each report is `{ "status": "error|warning|pass", "counts": { "error": n, "warning": n, "info": n, "pass": n }, "results": [ … ] }`. Results are ordered worst first, then by severity. `status` is the worst non-info status. Readability checks: [READABILITY.md](READABILITY.md).

The focus keyphrase is saved as post meta `_dumpseo_focus_keyphrase` (REST `meta` field, same permissions as other SEO fields).

## Extending

```php
add_filter( 'dumpseo_analysis_rules', function ( $rules ) {
	$rules['my_rule'] = new My_Rule(); // implements DumpSEO\Analysis\Rule
	unset( $rules['outbound_links'] );
	return $rules;
} );
```

Rules receive an `DumpSEO\Analysis\Input` and must not query the database.
