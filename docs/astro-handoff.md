# Astro hand-off: contract changes, seed gaps, blockers

What the Astro side needs to know about the WordPress build. It lists every place WordPress differs from [wordpress-v1-requirements.md](wordpress-v1-requirements.md) (the "spec"), the decisions both sides have to make, and what blocks progress. Keep it current: update it at the end of every milestone, and tell the Astro-side session when a row changes.

**Last updated:** 2026-10-11, after M3. All five §4.9 queries run as written and return the expected shapes. Measured against a local install seeded from `docs/seed/sample-content.json`.

Status: **Open** (needs a decision or work) · **Planned** (WordPress will fix it in the named milestone; no Astro change if that's accepted) · **Done** (fixed in WordPress; matches the spec) · **Astro** (needs an Astro-side change) · **Info** (no action; just be aware).

## Astro-side actions (summary)

What the Astro side has to change, in one list. The detail is in the tables below.

1. **Read `excerptText` instead of `excerpt`** for post cards and the video `description` (C5). The spec §4.9 queries are already updated.
2. **Read `captionText` instead of `caption`** for the article `leadCaption` (C6).
3. **Alias the TOC anchor:** `toc { id: anchor label }` (C7).
4. **Alias the lead image URL in the `Article` query:** `featuredImage { node { leadUrl: sourceUrl(size: GN_LEAD) … } }` (C14). As originally written, the query was invalid GraphQL and could never have run.
5. Dates now carry `+06:00` (C4). If Astro added its own offset handling as a workaround, remove it.
6. Copy the 6 stand-in `youtubeId`s from this repo's `docs/seed/sample-content.json` into the Astro seed source, so a re-export keeps them (S1).
7. **Connect WordPress (B6):** the first slice is in. Actions 1–4 are already applied in `src/lib/wordpress.ts`. Remaining: `gnSeo` in `BaseLayout`, the options-page promo and chips, `srcset`, info pages. Later: the preview route (B8) and `shop-router` (B12).

---

## 1. GraphQL contract differences (spec §4.9)

WPGraphQL for ACF 3.0 (the current release) exposes some SCF field types differently from the shapes in §4.9. Every field does reach GraphQL (the §3 day-one check passes 25/25), but some come back in a different shape.

| # | Field | Spec §4.9 expects | WordPress returns today | Proposed resolution | Status |
|---|---|---|---|---|---|
| C1 | `articleFields.cardTag` | `cardTag { slug name }` | Now exactly as the spec: a single `Tag`, or `null` | `ghoroa-core` registers it (`includes/graphql/article-fields.php`); the SCF version is hidden from GraphQL | Done (D1) |
| C2 | Homepage `hero`, `videoPicks`, `popularPicks` | `hero { ... on Post { slug } }`, `videoPicks { ... on Video { slug } }` | Now exactly as the spec: `hero` is a `Post` (null unless published); the picks are `[Video]` / `[Post]` in editor order, published items only | `includes/graphql/homepage-options.php` | Done (D1) |
| C3 | `homepageOptions` nesting | `homepageOptions { hero … promo … }` | Now exactly as the spec; there's no `homepageFields` level any more | Same file | Done (D1) |
| C4 | Dates (`date`, `modified`, `articleFields.contentUpdated`) | ISO 8601 with `+06:00` (§4.1) | Now `2026-10-02T08:00:00+06:00` for posts, videos, pages and media. `contentUpdated` is fixed too: it was Dhaka time labelled `+00:00`, an upstream WPGraphQL for ACF bug. `dateGmt`/`modifiedGmt` are unchanged (UTC, no `Z`) | `includes/graphql/dates.php`, `includes/graphql/article-fields.php` | Done (D2) |
| C5 | `excerpt` (posts, and videos where it's the `description`) | plain `string` | `excerpt` is still HTML (WPGraphQL's default, kept for other clients). **New field `excerptText`** returns plain text, with entities decoded | `includes/graphql/plain-text.php`. Spec §4.1, §4.5, §4.6 and §4.9 updated | Done (D3); **Astro: use `excerptText`** |
| C6 | Featured image `caption` (`leadCaption`) | plain `string` | `caption` is still HTML. **New field `captionText`** on `MediaItem` returns plain text | Same file; spec §4.5 and §4.9 updated | Done (D3); **Astro: use `captionText`** |
| C7 | `articleFields.toc` | Astro type: `toc?: { id; label }[]` | `toc { anchor label }` (as written in §4.9) | Astro aliases it in the query: `toc { id: anchor label }` | Astro (small) |
| C8 | `readingMinutes`, `gnSeo`, image size enums `GN_CARD` … `GN_OG` | present | All present. `gnSeo` is on Post, Page, Video, Category and Tag. Its `canonical` falls back to `GN_PUBLIC_URL` + the §6 path, and its other fields are `null` until an editor fills them in Rank Math | `includes/graphql/reading-minutes.php`, `includes/graphql/seo.php` | Done (M3) |
| C9 | `srcSet` and image files | built from the §4.1 crops, WebP | The §4.1 crops exist and are **WebP**. The original upload keeps its format, so `sourceUrl` **without** a size is still `.jpg`. Always pass a `size:` | `includes/images.php` | Done (M3) |
| C14 | `Article` query, lead image | `featuredImage { node { sourceUrl(size: GN_LEAD) … } }` next to `...Summary` | Invalid GraphQL as written: `Summary` already selects `sourceUrl(size: GN_CARD)` on the same field, and GraphQL forbids the same field twice with different arguments unless one is aliased | Spec §4.9 fixed: `leadUrl: sourceUrl(size: GN_LEAD)` | **Astro: use the alias** |
| C15 | Video thumbnail fallback (§4.6) | "fall back to the YouTube thumbnail URL in the GraphQL resolver" | Same result through the same fields. The thumbnail is downloaded into the media library when the video is saved, so it gets the crops and WebP and comes from our uploads. An editor's own image is never replaced. Some videos only have YouTube's small letterboxed `hqdefault` (480×360), so that card has black bars; upload a custom image for those | `includes/video-thumbnails.php`; spec §4.6 updated | Info |
| C10 | `featuredImage.node.sourceUrl` host | `https://domain.com/shop/wp-content/uploads/…` | locally `http://localhost:8888/wp-content/uploads/…` (no `/shop`) | Expected locally (spec §3.1a). Astro must use the URL as given and never hard-code a host or `/shop` | Info |
| C11 | `uri` | Astro URL (§6) | already relative and Astro-shaped: `/aloevera-pata-poche-jacche/` | Absolute links and canonicals come with M5 (§6) | Info |
| C12 | `author.node.description` | `bio?: string` | `null` when an author has no bio (2 of the 3 seed authors) | Treat `null` as "no bio" | Info |
| C13 | `articleFields.featured` | not in the Astro types | exposed as `Boolean` (candidate list for the hero picker, §4.5) | Ignore it, or use it later for an editor-side hero picker | Info |

## 2. Seed data gaps

`sample-content.json` is exported from the Astro repo (`npm run export-seed`), so fixes to it belong there.

| # | Gap | Effect | Fix | Status |
|---|---|---|---|---|
| S1 | No video in the seed file had a `youtubeId` (§11 requires at least one real ID) | **Fixed in this repo's copy on 2026-10-11:** all 6 videos have IDs of real, public YouTube videos (thumbnails return 200). They are **stand-ins**, not GhoroaNiramoy videos | **Astro side:** copy the same 6 IDs into the Astro repo's seed source, otherwise the next `npm run export-seed` overwrites them. Replace them with the channel's real videos before launch | Done here; Astro copy pending |
| S2 | No images in the seed (no `image` on any post or video) | WordPress generates its own placeholders: one shared 1600×1000 image for 23 posts, and a lead image for the reference article. Astro's sample pages may look different from the WordPress-fed build | Optional: add `image: { src, alt }` to the seed so both sides use the same pictures | Info |
| S3 | Reference article: no alt text for the lead image | WordPress uses `leadCaption` as the alt text | Optional: add `image.alt` to the seed | Info |
| S4 | Reference article body has a placeholder link: `<a href="#">সাহায্য করতে পারে</a>` | A dead link once the article is public. The link-rewrite filter (§6, M5) leaves `#` alone | Point it at a real article, or remove the link before launch | Open (before launch) |
| S5 | Reference article placeholder facts (nutrition table, references) | Already flagged in §11 | Replace before anything goes public | Open (before launch) |
| S6 | Only the reference article has a `dek` | Following §11, WordPress sets `dek` = `excerpt` for the other 23 | None | Info |
| S7 | `readingMinutes` | The seed value is imported as `readingMinutesOverride` for all 24 posts (D5), so WordPress returns exactly the seed's numbers. The reference article is 7 (its calculated value would be 3) | None | Resolved (D5) |
| S9 | Seed posts in রান্নাঘরের মসলা with health topics (D4 follow-up) | `daruchini-rokte-shorkora` (blood sugar) and `lobongo-datbethay` (toothache) read like health articles, but now default to `isHealth: false`, so they need no caution callout | If they should be health articles, add `"isHealth": true` to those posts in the Astro seed and re-export | Open (content call) |
| S8 | `toc[].id` in the seed | Imported into the WordPress field `anchor` (see C7) | None | Info |

## 3. Decisions needed

| # | Decision | Options | Recommendation | Needed by |
|---|---|---|---|---|
| D1 | Contract shapes C1–C3 | (a) WordPress returns the §4.9 shapes; (b) Astro adopts the WPGraphQL for ACF connection shapes | **Decided 2026-10-11: (a).** Implemented; the contract is unchanged | Done |
| D2 | Date format (C4) | (a) WordPress returns `date` and `contentUpdated` with `+06:00`; (b) Astro reads `dateGmt` and formats it in Asia/Dhaka | **Decided 2026-10-11: (a).** Implemented for `date`, `modified` and `contentUpdated` | Done |
| D3 | Plain-text `excerpt` and `caption` (C5, C6) | (a) WordPress adds plain-text fields; (b) WordPress makes the public `excerpt` plain text; (c) Astro strips the tags | **Decided 2026-10-11: (a).** New fields `excerptText` and `captionText`; the Astro queries switch to them | Done (Astro update pending) |
| D5 | Reading speed for `readingMinutes` (S7) | (a) keep 180 words a minute; (b) a lower Bengali rate | **Decided 2026-10-12: (a).** Editors set `readingMinutesOverride` when the calculated time doesn't fit. The importer treats the seed's `readingMinutes` as that editorial value, so every seeded post, the reference article included (7), shows the seed's number | Done |
| D4 | `isHealth` default for রান্নাঘরের মসলা | on or off | **Decided 2026-10-12: off.** The 5 seed posts in this category are no longer health articles: no caution callout, no `healthClaimsReviewed`. Editors can switch it on per article | Done |

## 4. Blockers and prerequisites

| # | Item | Blocks | Owner | Status |
|---|---|---|---|---|
| B1 | **Free space on drive F:** (about 30 MB left). `npm install` fails with `ENOSPC`, so wp-env runs from the npx cache on C:. Uploads and the database live on C: (`~/.wp-env`), so seeding works | Nothing right now. Needed if any milestone adds npm build tooling (e.g. a custom block that needs a JS build in M4), and for a local `node_modules` | You | Open (workaround in place) |
| B2 | Docker DNS is intermittent during image builds (`auth.docker.io` / `dl-cdn.alpinelinux.org` "transient error") | Only the first `wp-env start` and rebuilds; a retry fixed it | You (Docker or network) | Watch |
| B3 | wp-env breaks on a Windows username with a space ("KHAN GADGET") | — | — | Resolved: `tools/wp-env.cjs`; run wp-env through the npm scripts |
| B4 | Decisions D1–D3 | M3 | Both sides | Resolved 2026-10-11 |
| B5 | A real `youtubeId` in the seed (S1) | §11 acceptance | Astro side or content owner | Resolved locally 2026-10-11 (stand-in IDs); real channel IDs before launch |
| B6 | Astro task "Connect WordPress" (astro-todo §1) | The end-to-end contract test (`WP_GRAPHQL_URL=http://localhost:8888/graphql`) | Astro side | **First slice done 2026-10-11** (`src/lib/wordpress.ts`): every page type renders WordPress data, and the reference body is identical to the Astro file. Still open on the Astro side: `gnSeo` in `BaseLayout`, header promo and search chips, `srcset`, info pages, `gn_build` auth |
| B7 | A real medical reviewer: name and registration number (§8) | Production roles. Locally M5 uses a test user | Content owner | Before launch |
| B8 | Preview route `/preview/` on the Astro site plus a shared HMAC secret (§10.2) | End-to-end preview (M6). WordPress can generate links and tokens without it | Astro side | Should, before M6 is verified |
| B9 | Workers Builds deploy hook URL (§10.1) | Production webhook only; locally the hook just logs | You (Cloudflare) | M7 |
| B10 | Real domain registered and moved to Cloudflare (§13 open decision) | `GN_PUBLIC_URL`, canonicals, cookies and the Worker route in production | You | M7 |
| B11 | Hostinger KVM 1 VPS, Cloudflare account, R2 bucket, server panel decision (§3.1–3.4, §13) | M7 | You | M7 |
| B12 | `shop-router` Worker (§3.1) lives in the Astro repo or an `infra/` repo | Production routing of `/shop/*` | Astro or infra side | M7 |
| B13 | Astro publishes `tokens.css` and `ghoroa.css` at an unhashed, long-cached path (§12) | The `/shop` theme (Should, before the shop opens). Not needed for M4: the editor loads the copies in `design-system/` | Astro side | Later |
| B14 | WordPress's sitemap URL for Astro's `robots.txt` (§9) | Nothing in v1. It will be `/shop/sitemap_index.xml` (Rank Math); it returns 404 until shop products exist, because every content type is off | WordPress side tells the Astro side | Later |

## 5. Changelog

- **2026-10-12 (D4, D5):**
  - **D4:** রান্নাঘরের মসলা is no longer a health category by default.
  - **D5:** the reading rate stays at 180 words a minute, and the seed's reading times are imported as editor overrides.
  - **Opened:** S9.

- **2026-10-11 (M3):**
  - **Added:** `readingMinutes` and `gnSeo`; the §4.1 crops (`GN_CARD` … `GN_OG`) as WebP; the YouTube thumbnail fallback; the Rank Math settings from §9.
  - **Fixed in spec §4.9:** the `Article` query now uses a `leadUrl` alias (C14).
  - **Opened:** decision D5 (reading speed).
  - **Checks:** the day-one check now covers 40 items.

- **2026-10-11 (seed):** all 6 seed videos have stand-in `youtubeId`s and are imported. §11's "at least one real `youtubeId`" is met locally.

- **2026-10-11 (D1–D3):** `cardTag`, `homepageOptions`, `hero`, `videoPicks` and `popularPicks` now match §4.9 exactly. `date`, `modified` and `contentUpdated` carry `+06:00`. New plain-text fields `excerptText` and `captionText`; spec §4.1, §4.5, §4.6 and §4.9 updated to use them. The day-one check (`wp gn graphql-check`) now covers all of this: 28 checks.

- **2026-10-10 (M2):** seed importer `wp gn seed`. Found C5, C6, C10–C13 and S1–S8. Starter content ("Hello world!", "Sample Page") is removed by `wp gn setup` so it never reaches Astro.
- **2026-10-10 (M1):** content model and fields. Day-one check passes. Found C1–C4 and C7–C9.
