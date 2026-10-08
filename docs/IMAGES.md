# Image SEO

## Finding images without alternative text

Alternative text ("alt text") describes an image for people using screen readers and helps image search understand it.

- **DumpSEO → Images** shows how many images in your media library have no alt text, with a link to them.
- **Media → Library** in *list view* has an **Alt text** column (the text, or ✕ Missing) and a filter **Missing alt text** next to the date filter.

To fix one, open the image and fill in *Alternative Text*. Purely decorative images (lines, background shapes) can stay empty on purpose.

An image counts as missing alt text when the field was never filled in or is empty. The count covers the media library; images inside posts are checked by the [SEO analysis](ANALYSIS.md) (`image_alt`) while you edit.

**DumpSEO never changes your media.** It only reads alt text and reports.

## Default sharing image and logo

*DumpSEO → Social sharing → Default sharing image URL* is used when a page has no featured image (see [SOCIAL.md](SOCIAL.md)). *Site identity → Logo URL* is used in [structured data](SCHEMA.md). Both have a **Choose from media library** button (for users allowed to upload files); you can also paste any https:// image address. The choice is saved when you click *Save Changes*.
