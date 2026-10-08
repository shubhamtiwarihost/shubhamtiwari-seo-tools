# Redirects

Send visitors (and search engines) from an old address to a new one — for example after renaming a page or moving content. **DumpSEO → Redirects**, administrators only.

## Adding a redirect

1. **DumpSEO → Redirects → Add redirect**.
2. **Old address** (title field): the path that should stop working, e.g. `/old-page`. You can paste a full address from this site; only the path is kept.
3. **New address**: a path on this site such as `/new-page/`, or a full address starting with `https://` (another site is fine).
4. **Type**, then **Publish** to switch it on (**Save Draft** keeps it off).

| Type | Use it when |
|---|---|
| 301 Moved permanently | The page has moved for good. Search engines transfer the old address to the new one. The usual choice. |
| 302 Found / 307 Temporary | The move is temporary (a sale page, maintenance). 307 keeps the request method; for normal page visits both behave the same. |
| 410 Gone | The page was removed on purpose and has no replacement. Visitors see your theme's "not found" page; search engines drop the address faster than with a plain 404. |

## How matching works

- Matching ignores letter case, a trailing slash and the query string: `/Old-Page/?ref=x` matches a redirect from `/old-page`.
- The query string is passed on to the new address, unless the new address has its own.
- Only normal page visits (GET/HEAD) are redirected; form submissions are not.
- Responses carry an `X-Redirect-By: DumpSEO` header, which helps when checking with browser tools.

## Protection against mistakes

A redirect is saved **inactive**, with the reason shown, when:

- the old address is the homepage, the dashboard (`/wp-admin`), the login page, the REST API (`/wp-json`), `xmlrpc.php`, or files under `/wp-content` or `/wp-includes` — so nobody can be locked out;
- the old address is on another site;
- the new address is not a valid path or http(s) address;
- another active redirect already uses the same old address;
- it would create a loop (A → B → A, directly or through other redirects), or a chain of more than 10 redirects.

## Not included in the free version

Regular-expression (pattern) redirects and a log of 404 errors are not part of DumpSEO Free.

## Performance

Active redirects are kept in one small autoloaded option, so checking a visit costs no database queries, and a redirected visit is answered before WordPress loads any content. Sites with more than 500 redirects use one extra query per visit (cached if the site has a persistent object cache).
