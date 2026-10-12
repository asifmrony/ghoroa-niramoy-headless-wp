# GhoroaNiramoy — WordPress v1 requirements

Requirements for the WordPress + WooCommerce install that backs the GhoroaNiramoy (ঘরোয়া নিরাময়) site. Work from this document in the WordPress workspace. The Astro front end already exists and is built against the contract in section 4. If something in WordPress can't match that contract exactly, flag it so both sides can be changed together.

Priority labels: **Must** (needed to launch v1), **Should** (needed soon after launch, or before the shop opens), **Later** (a revenue phase after v1; listed so v1 doesn't block it).

---

## 1. Context

- **What the site is.** A Bengali blog about natural home remedies, medicinal plants, kitchen spices and rooftop gardening. Readers browse a static **Astro** site at `domain.com`.
- **What WordPress does.** WordPress lives at `domain.com/shop` and has two jobs:
  1. **Headless CMS (v1).** Editors write articles and videos here. At build time Astro fetches the content through **WPGraphQL** and renders every reader-facing page itself.
  2. **Shop (later phases).** WooCommerce for affiliate products, digital downloads, consultations and physical goods. This is not part of v1, but nothing in v1 may block it.
- **Routing.** A CDN or proxy sends `/shop/*` to WordPress and every other path to Astro. WordPress must therefore never be the public renderer of articles, categories, tags, authors or videos. Those URLs belong to Astro (section 6).
- **Language and tone.** All content is Bengali (চলিত ভাষা), addressed to the reader as আপনি. Health copy never promises a cure: write "সাহায্য করতে পারে" (may help), never "সারিয়ে দেয়" (cures).
- **Source of truth for look and markup.** The design-system package (`design-system/` in the Astro repo). It holds tokens, `ghoroa.css` and the reference previews. Article body HTML must use its `.gn-prose` vocabulary (section 5).

## 2. v1 scope at a glance

| # | Area | Priority |
|---|---|---|
| 1 | Install at `https://domain.com/shop`, behind the proxy, hardened | Must |
| 2 | Content model: posts, `video` post type, the four categories, tags, authors, media credit | Must |
| 3 | Custom fields for articles, videos, media and a homepage options page | Must |
| 4 | WPGraphQL schema matching the Astro contract (section 4) | Must |
| 5 | Editor blocks that output the `.gn-prose` markup exactly (section 5) | Must |
| 6 | Link rewriting to Astro URLs, plus redirects away from WordPress-rendered front-end pages | Must |
| 7 | Editorial safeguards: health-claims review, caution callout, Latin slugs, reserved slugs | Must |
| 8 | Publish webhook that triggers an Astro rebuild | Must |
| 9 | SEO plugin fields exposed in GraphQL; WordPress sitemaps limited to shop content | Must |
| 10 | Roles and capabilities, including a medical-reviewer role; 2FA for admins | Must |
| 11 | Preview button to an Astro preview route with a signed token | Should |
| 12 | Info pages (about, privacy, disclaimer, contact, newsletter) managed as WP Pages | Should |
| 13 | Minimal `/shop` theme sharing the site's CSS, header and footer | Should (before shop opens) |
| 14 | WooCommerce data model (product `type`), Store API cart count, payments, courier | Later |

---

## 3. Install, hosting and security — Must

**Install**
- WordPress Address and Site Address are both `https://domain.com/shop`, so `wp-admin`, `wp-json`, `graphql` and uploads all live under `/shop`.
- Timezone `Asia/Dhaka`, site language বাংলা (`bn_BD`).
- Permalinks `/%postname%/`. Public URLs are rewritten anyway (section 6), but GraphQL `uri` values must be predictable.

### 3.1 Hosting and routing (decided)

```
Registrar (not Hostinger) ── nameservers ──► Cloudflare (DNS, SSL, WAF, cache)
                                               │
 domain.com/*  ───────────────────────────────►├─► Cloudflare Worker "ghoroa-niramoy": Astro site (static assets + /api forms)
 domain.com/shop/*  ──► Worker "shop-router" ──┴─► wp-origin.domain.com ──► Hostinger KVM 1 VPS (WordPress)
```

| Piece | Choice |
|---|---|
| Domain | Registered at a separate registrar (not Hostinger), nameservers pointed to Cloudflare |
| DNS, SSL, WAF | Cloudflare free plan. SSL mode **Full (strict)**; Always Use HTTPS; HSTS once stable |
| Astro site | **Cloudflare Workers with static assets** (Astro 7's Cloudflare adapter deploys to Workers, not Pages). Attached to `domain.com` as a Custom Domain; `www` 301s to the apex. Static pages are free and unmetered; only `/api/*` (contact and newsletter forms) runs Worker code |
| `/shop` routing | Cloudflare Worker `shop-router` on the **route** `domain.com/shop*`, which takes precedence over the site Worker's Custom Domain for those paths |
| WordPress origin | **Hostinger KVM 1 VPS** (1 vCPU, 4 GB RAM, 50 GB NVMe), at the data centre nearest Bangladesh. The same VPS also hosts a separate Node.js app (see 3.3) |
| Origin hostname | `wp-origin.domain.com`: an A record to the VPS IP, **proxied** (orange cloud). Never linked or published |

**Worker `shop-router`** (lives in the Astro repo or a small `infra/` repo; it's version-controlled either way)
- For `domain.com/shop` and `domain.com/shop/*`, forward the request to `https://wp-origin.domain.com` with the same path, query, method, body and headers. Stream the response back without buffering it.
- Add these headers on the way to the origin:
  - `X-Forwarded-Host: domain.com`
  - `X-Forwarded-Proto: https`
  - `X-GN-Origin-Key: <secret>`, a Worker secret that the origin also knows
- Pass `Set-Cookie` through unchanged. Rewrite any `Location` header pointing at `wp-origin.domain.com` back to `domain.com`.
- Don't let the route catch `domain.com/shopping…`: match `/shop` exactly or `/shop/` as a prefix, and pass everything else through to the site Worker.
- **Media is served through this Worker too** (decision: images are linked straight from `/shop/wp-content/uploads/…`; see §4.1).
  - For `/shop/wp-content/uploads/*`, fetch with `cf: { cacheEverything: true, cacheTtl: 2592000 }` (30 days), so repeat image requests come from Cloudflare's cache instead of the VPS.
  - Uploads keep unique file names, so replacing an image produces a new URL and long caching is safe.
- **Request budget:** every `/shop/*` request runs the Worker, **including cached images**, against the free 100,000 requests a day. A homepage view loads about 25 images, so the budget covers roughly 4,000 image-heavy page views a day.
  - Set a Cloudflare usage notification at about 70% of the daily limit.
  - When traffic approaches it, pick one: Workers Paid ($5/month, 10 million requests), or move uploads to a `media.domain.com` hostname that skips the Worker. The second changes image URLs, so a full rebuild is needed.

**Origin (Nginx on the VPS)**
- Accept 80/443 **only from Cloudflare's published IP ranges**, enforced by the host firewall (ufw and Hostinger's firewall manager).
- Use a Cloudflare Origin CA certificate on `wp-origin.domain.com`.
- Return `403` for any request without the correct `X-GN-Origin-Key`. This makes the origin reachable only through the Worker, even from other Cloudflare traffic.
- Real visitor IP: set `set_real_ip_from` to Cloudflare's ranges and `real_ip_header CF-Connecting-IP`, so login rate limiting and logs see real IPs.

**`wp-config.php`**
```php
// Requests arrive via the shop-router Worker; trust its forwarded host/proto (origin already requires X-GN-Origin-Key).
if (($_SERVER['HTTP_X_FORWARDED_HOST'] ?? '') === 'domain.com') { $_SERVER['HTTP_HOST'] = 'domain.com'; }
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') { $_SERVER['HTTPS'] = 'on'; }
define('WP_HOME', 'https://domain.com/shop');
define('WP_SITEURL', 'https://domain.com/shop');
define('FORCE_SSL_ADMIN', true);
define('COOKIE_DOMAIN', 'domain.com');
define('DISALLOW_FILE_EDIT', true);
define('DISABLE_WP_CRON', true); // system cron runs wp-cron every minute
```

**Cloudflare rules (free plan)**
- **Cache rule:** bypass the cache for everything under `domain.com/shop/*` in v1, **except** `/shop/wp-content/uploads/*`, which is cached as described in the Worker section. Allow anonymous caching of product pages later, with the cookie exclusions under "Caching rules" below.
- **WAF:** keep the free managed rules on. Add one rate-limiting rule on `/shop/wp-login.php` and one block rule for `/shop/xmlrpc.php`.
- **Bot Fight Mode: leave it off.** On the free plan it can't be skipped per path, and it would challenge the Astro build's GraphQL requests and the shop's payment callbacks.

**How the Astro build reaches WordPress**
- Builds run on Cloudflare Workers Builds, which has no fixed IPs, so the build can't be IP-allowlisted.
- Instead, the build calls the public `https://domain.com/shop/graphql` through the Worker. Published content is public anyway.
- Drafts and private fields require the `gn_build` Application Password (section 8).

### 3.1a Local development
- Build and test everything locally first. Production deployment (VPS, panel, Worker routing) comes last.
- Local stack: **`@wordpress/env`** (wp-env; official, free, needs Docker Desktop). It's scriptable, so Claude Code can drive it, including WP-CLI through `npx wp-env run cli wp …`.
- Locally WordPress runs at the root (`http://localhost:8888`), not under `/shop`. So `ghoroa-core` must **never hard-code domains or the `/shop` prefix**:
  - Public (Astro) site URL: constant `GN_PUBLIC_URL` (local `http://localhost:4321`, production `https://domain.com`), used by the link rewriting in §6.
  - Build hook URL: constant `GN_BUILD_HOOK_URL` (local: unset, so the webhook only logs).
- The Astro repo can build against local WordPress with `WP_GRAPHQL_URL=http://localhost:8888/graphql`.

### 3.2 Server baseline (the VPS is unmanaged: we own its security)
- Ubuntu 24.04 LTS. Automatic security updates (`unattended-upgrades`), `fail2ban`, SSH with **keys only** (no password, no root login) through a sudo user.
- Nginx, PHP-FPM 8.3 with a pool running as the WordPress site user, and MariaDB/MySQL bound to `127.0.0.1`. Optional: Redis object cache, which 4 GB of RAM allows.
- A server panel is optional. CloudPanel is free and runs WordPress and Node sites side by side. Whatever the WP workspace chooses, document every setup step so the server can be rebuilt (see the exit plan).
- **Email — Should: set up when convenient, required before the shop opens.**
  - v1 works without email. Only staff use WordPress: admins reset passwords in Users → Edit (or `wp user update`), create accounts directly, and editors check the Pending list.
  - When it is set up, send through a free SMTP tier (e.g. Brevo) via an SMTP plugin. Add SPF, DKIM and DMARC records in Cloudflare DNS. Don't send from the VPS: it has no mail server, and outgoing port 25 is often blocked.
  - Its main v1 value: WordPress's "technical issue" recovery-mode emails and update-failure notices. Until email exists, the uptime monitor below is the alarm.
  - Mandatory before WooCommerce goes live (order, account and download emails).
- **Monitoring:**
  - A free uptime check on `https://domain.com/shop/wp-login.php` and on a GraphQL health query.
  - Alerts at 80% disk and RAM use.

### 3.3 Sharing the VPS with the Node.js app
- A separate Linux user, its own Nginx server block on its own hostname (also proxied through Cloudflare), and a separate database and database user. No shared directories.
- Cap the Node app's memory (pm2 `max_memory_restart` or a Docker memory limit, about 1 GB). Size PHP-FPM `pm.max_children` so WordPress and MySQL keep at least 2 GB.
- Neither app runs as root. A compromise of one must not give write access to the other.

### 3.4 Backups and the exit plan
- **Daily** off-site backup of the database plus `wp-content/uploads` to Cloudflare R2 (S3-compatible; the free tier covers about 10 GB). Keep 14 daily and 8 weekly copies. Use `wp-cli` + rclone on cron, or a backup plugin that supports S3.
- Hostinger's weekly VPS snapshot is a second layer, not the primary backup.
- **Test a restore** on a throwaway server before launch, then once a quarter.
- **Exit plan** (for a host change at renewal):
  1. Rebuild the server from the documented setup.
  2. Restore the latest backup.
  3. Point the `wp-origin` A record at the new IP.

  Nothing else changes: the site Worker, `shop-router`, DNS and the domain all stay put.

**Hardening**
- `DISALLOW_FILE_EDIT`, XML-RPC disabled, login rate limiting, a WAF at the edge, and 2FA for every administrator and editor. **2FA uses authenticator-app (TOTP) codes only**: not email, which may not exist in v1, and is weaker anyway.
- Comments, pingbacks and trackbacks disabled site-wide. Remove the comment UI from the admin.
- Keep plugins few and current. Daily off-site backups of the database and uploads, with a tested restore.
- WP-Cron driven by a real system cron, so scheduled posts publish on time and fire the webhook.

**Caching rules for the proxy, documented for the hosting setup**
- Never cache: `/shop/cart*`, `/shop/checkout*`, `/shop/my-account*`, `/shop/wp-admin*`, `/shop/wp-json*`, `/shop/graphql*`, `/shop/wp-login.php`, any response that sets cookies, or any request carrying `woocommerce_items_in_cart`, `wp_woocommerce_session_*` or `wordpress_logged_in_*` cookies.
- Exempt payment-gateway callback URLs from caching, bot challenges and rate limits (needed later).

**Plugin set (decided; everything free)**

Project rule: use free plugins unless a paid one is genuinely needed.
- **WPGraphQL** and **WPGraphQL for ACF**.
- **Secure Custom Fields (SCF)**, the free WordPress.org plugin that includes the former ACF Pro features: repeater, options pages, ACF Blocks. It replaces ACF Pro.
  - Never activate ACF and SCF together.
  - SCF keeps ACF's API, but WPGraphQL for ACF documents support for ACF, not SCF by name. **Day-one check:** register one field of each type used here (text, true/false, date-time, post object, relationship, taxonomy, repeater, group, options page) and confirm each appears in GraphQL. If any fails, expose that field with `register_graphql_field` in `ghoroa-core` instead.
- **Rank Math SEO (free).** It is used for the editor-facing SEO fields, and later for WooCommerce product SEO and the `/shop` product sitemap. For how its data reaches GraphQL, see section 9.
- A free 2FA plugin (e.g. Two Factor).
- One small **custom plugin, `ghoroa-core`**, for everything else in this document: post type, fields registered in code, blocks, link filters, redirects, validation, webhook, GraphQL fields. Keep logic in code, versioned, rather than in the database.

---

## 4. Content model and the GraphQL contract — Must

The Astro side consumes the shapes below; its TypeScript types are reproduced so both workspaces agree. GraphQL names are proposals. If you change one, record the final name here.

### 4.1 Global rules
- **Slugs are Latin.** Every post, video, tag and user slug is lowercase ASCII (`a-z 0-9 -`), transliterated from Bengali. Example: `thankuni-patar-upokarita`. Reject non-ASCII slugs on save (section 7).
- **Dates** come back as ISO 8601 with the `+06:00` offset. This applies to `date`, `modified` and `articleFields.contentUpdated`, e.g. `2026-10-02T08:00:00+06:00`. WPGraphQL's `dateGmt` and `modifiedGmt` are unchanged (UTC, no suffix).
- **Plain text:** WPGraphQL returns `excerpt` and `caption` as HTML (`<p>…</p>`), and their `RAW` format is hidden from anonymous clients. Use `excerptText` (posts, videos) and `captionText` (media), which `ghoroa-core` adds as plain text.
- **Images** expose `sourceUrl`, `altText` (required, Bengali), `mediaDetails { width height }` and a `srcSet`. Register these crops:

  | Name | Size | Used for |
  |---|---|---|
  | `gn-card` | 800 × 500 | 16:10 cards, video thumbs |
  | `gn-card-sm` | 400 × 250 | small cards |
  | `gn-hero` | 1200 × 900 | 4:3 homepage hero |
  | `gn-lead` | 1600 × 800 | 2:1 article lead |
  | `gn-thumb` | 176 × 176 | 1:1 side-list thumbs |
  | `gn-og` | 1200 × 630 | social share image |

  - Serve WebP: set WordPress's image output format to WebP for JPEG and PNG uploads, or use a free conversion plugin. Compress on upload. Astro does no image processing.
  - **Image URLs:** GraphQL `sourceUrl`/`srcSet` and the article HTML use `https://domain.com/shop/wp-content/uploads/…`. Astro links them directly (decided; §13).

### 4.2 Categories (core `category`) — exactly four
| Name | Slug (fixed) |
|---|---|
| ঘরোয়া টোটকা | `gharoa-totka` |
| ভেষজ উদ্ভিদ | `veshoj-udbhid` |
| রান্নাঘরের মসলা | `rannaghorer-mosla` |
| ছাদবাগান | `chhadbagan` |

- Create these four, with their one-sentence `description` (shown under the archive heading). Remove "Uncategorized" or rename it to one of the four.
- Every post has **exactly one** of these categories. Block publishing with zero or more than one.
- Only administrators may create, rename, re-slug or delete categories. Editors must not be able to add a fifth.
- **Display order is fixed** by brand rule; there is no reorder option in WordPress. (The original architecture note lists "category order" on the homepage options page. It's dropped because it conflicts with the fixed-order rule.)

### 4.3 Tags (core `post_tag`)
- `name` (Bengali) and `slug` (Latin). Example: `হজম` → `hojom`. Archive at `domain.com/tag/<slug>/`.
- গাছপালা (`gachpala`) is a normal tag that can also be used as a card label (`cardTag` below).

### 4.4 Authors (core users)
- Astro uses: `slug` (user nicename, Latin), `name` (display name, Bengali) and `description` (1–2 sentence bio for the author card). Archive at `domain.com/author/<slug>/`.
- Gravatar is not used; the site shows an initial avatar.
- Do not expose email addresses or other private user fields in GraphQL.

### 4.5 Articles (core `post`)

Astro type:
```ts
interface ArticleSummary {             // cards, lists, archives
  slug: string; title: string; excerpt: string;
  category: CategorySlug;              // one of the four
  cardTag?: { slug: string; name: string };
  tags?: { slug: string; name: string }[];
  author: { slug: string; name: string; bio?: string };
  date: string;                        // ISO 8601
  readingMinutes: number;
  image?: { src: string; alt: string; width?: number; height?: number };
}
interface Article extends ArticleSummary {   // single article page
  dek: string;
  updated?: string;                    // shown as "Updated 3 Oct" only when set
  medicallyReviewed: boolean;
  isHealth: boolean;
  leadCaption?: string; leadCredit?: string;
  contentHtml: string;                 // section 5 vocabulary
  toc?: { id: string; label: string }[];
  tags: { slug: string; name: string }[];
}
```

Field mapping:

| Astro field | WordPress source | Rules |
|---|---|---|
| `slug` | post slug | Latin; not reserved (section 7) |
| `title` | post title | Bengali numerals inside Bengali headlines (১০টি, not 10টি); about 45 characters or fewer for hero candidates |
| `excerpt` | post excerpt, read as GraphQL `excerptText` (plain text) | **Required.** 1–2 sentences on what the reader will learn; used on cards |
| `dek` | ACF `articleFields.dek` | **Required.** 1–2 sentences under the headline (may differ from the excerpt) |
| `category` | primary category slug | Exactly one of the four |
| `cardTag` | ACF `articleFields.cardTag` (single tag) | Optional card-label override |
| `tags` | post tags | |
| `author` | post author | |
| `date` | post date | |
| `updated` | ACF `articleFields.contentUpdated` (date-time) | Set by hand for substantive updates only. **Not** `modified`, which changes on every save |
| `readingMinutes` | computed GraphQL field `readingMinutes` | Word count of rendered content ÷ 180 words per minute, rounded up, minimum 1 (rate confirmed 2026-10-12). ACF `articleFields.readingMinutesOverride` wins when set; editors are expected to set it when the calculated time doesn't fit the piece |
| `image`, `leadCaption` | featured image, and its caption (GraphQL `captionText`, plain text) | Featured image **required** to publish; alt text required |
| `leadCredit` | ACF on attachment, `mediaFields.credit` | e.g. "Photo: GhoroaNiramoy" |
| `isHealth` | ACF `articleFields.isHealth` (true/false) | Default **on** for ঘরোয়া টোটকা and ভেষজ উদ্ভিদ, **off** for রান্নাঘরের মসলা and ছাদবাগান (রান্নাঘরের মসলা decided 2026-10-12); editable per article |
| `medicallyReviewed` | ACF `articleFields.medicallyReviewed` | Only the `medical_reviewer` role may set it (section 8). Also store `articleFields.medicalReviewer` (user) and the review date; not shown in v1 but kept for the record |
| — | ACF `articleFields.healthClaimsReviewed` (true/false) | Editors and admins only. Required before a health article can publish (section 7). Not exposed publicly |
| — | ACF `articleFields.featured` (true/false) | From the architecture plan; candidate list for the hero picker |
| `toc` | ACF repeater `articleFields.toc` (`anchor`, `label`) | Optional short TOC labels; otherwise Astro uses the H2s |
| `contentHtml` | `content` (rendered) | Must follow section 5 exactly |

### 4.6 Videos (custom post type `video`, "Ghoroa Originals")

Astro type:
```ts
interface Video {
  slug: string; title: string;
  duration: string;      // "mm:ss" or "h:mm:ss"
  date: string;          // ISO 8601
  youtubeId?: string;
  description?: string;
  image?: { src: string; alt: string; width?: number; height?: number };
}
```

- Register `video` as public in GraphQL (`graphql_single_name: video`, `graphql_plural_name: videos`). Supports title, excerpt (used as `description`, read as GraphQL `excerptText`), thumbnail and author.
- Not rendered by WordPress on the front end; it lives at `domain.com/video/<slug>/`.
- ACF `videoFields`:
  - `youtubeId`: 11 characters, validated. A pasted YouTube URL is reduced to its ID.
  - `duration`: validated against `^\d{1,2}(:\d{2}){1,2}$`.
  - `relatedProducts`: relationship to WooCommerce products. **Later**; add the field now so it exists.
- Featured image is optional. If it's empty, fall back to the YouTube thumbnail.
  - **As built (M3):** when a video is saved, `ghoroa-core` downloads the thumbnail into the media library and sets it as the featured image. It tries `maxresdefault`, then `sddefault`, then `hqdefault`. The resolver isn't used, so `featuredImage` keeps its shape and gets the §4.1 crops, WebP and our uploads URL.
  - An image an editor sets is never replaced. A downloaded thumbnail follows the YouTube ID: it's swapped when the ID changes and removed when the ID is cleared.

### 4.7 Homepage options (ACF options page `homepageOptions`)

| Field | Type | Astro use |
|---|---|---|
| `hero` | post object (required, published) | HeroFeature |
| `videoPicks` | relationship to `video`, max 8, ordered | Ghoroa Originals band (empty means newest 6) |
| `popularPicks` | relationship to posts, max 4, ordered | "জনপ্রিয়" rail on article pages, until analytics data replaces it |
| `promo` | group: `label` ("নতুন:"), `text`, `url` | Header promo link |
| `popularSearches` | repeater of `term`, max 6 | Chips in the search overlay |

The rest of the homepage is automatic on the Astro side, so WordPress needs no fields for it:
- "সর্বশেষ" shows the newest 5 posts.
- "নতুন লেখা" shows the newest 5 posts excluding the hero.
- Each category block shows the newest 3 in that category that aren't already on the page.

### 4.8 Info pages (core `page`) — Should
- Pages with slugs `about`, `contact`, `privacy`, `medical-disclaimer` and `newsletter`, so editors can maintain them. Astro fetches them by slug and renders them inside its own layout.
- `contact` and `newsletter` supply only the intro text. The forms themselves are Astro components that post to the site Worker (contact → Cloudflare Email Routing, newsletter → Kit), so WordPress handles no form data.
- These slugs are reserved for posts (section 7).
- ACF `pageFields.draft` (true/false): when on, Astro noindexes the page and shows "পাতাটি তৈরি হচ্ছে".
- Body uses the same block allowlist as articles.

### 4.9 Reference queries (the Astro build will run these)

`cardTag` and `homepageOptions` (including `hero`, `videoPicks` and `popularPicks`) are registered by `ghoroa-core` in exactly the shapes below. WPGraphQL for ACF would otherwise return connections (`{ nodes { … } }`) and nest the options under the field group. `excerptText`, `captionText` and the date offset are covered in §4.1. Decisions D1–D3 in [astro-handoff.md](astro-handoff.md) explain why.

```graphql
fragment Summary on Post {
  slug title excerptText date readingMinutes
  categories(first: 1) { nodes { slug name } }
  tags { nodes { slug name } }
  author { node { slug name description } }
  featuredImage { node { sourceUrl(size: GN_CARD) altText srcSet mediaDetails { width height } } }
  articleFields { cardTag { slug name } }
}

query Posts($after: String) {               # paginated, 100 per page
  posts(first: 100, after: $after, where: { status: PUBLISH, orderby: { field: DATE, order: DESC } }) {
    pageInfo { hasNextPage endCursor }
    nodes { ...Summary }
  }
}

query Article($slug: ID!) {
  post(id: $slug, idType: SLUG) {
    ...Summary
    content
    featuredImage { node { leadUrl: sourceUrl(size: GN_LEAD) captionText altText mediaFields { credit } } }   # alias required: Summary already selects sourceUrl(size: GN_CARD)
    articleFields {
      dek isHealth medicallyReviewed contentUpdated
      toc { anchor label }
    }
    gnSeo { title description canonical noindex ogTitle ogDescription ogImage }   # section 9
  }
}

query Videos { videos(first: 100) { nodes { slug title date excerptText
  videoFields { youtubeId duration }
  featuredImage { node { sourceUrl(size: GN_CARD) altText } } } } }

query Homepage { homepageOptions { hero { ... on Post { slug } }
  videoPicks { ... on Video { slug } } popularPicks { ... on Post { slug } }
  promo { label text url } popularSearches { term } } }

query Taxonomies {
  categories { nodes { slug name description } }
  tags(first: 500) { nodes { slug name count } }
  users(where: { hasPublishedPosts: [POST] }) { nodes { slug name description } }
}
```

**Acceptance:** each query runs against a seeded install (section 11) and returns every field non-null where the table says required.

---

## 5. Article body markup and editor blocks — Must

Astro drops `content` straight into `<article class="gn-prose">`. The design CSS styles **exact element and class combinations**, so the blocks below must output this HTML byte-for-byte in structure. The class names, nesting and element types matter; whitespace doesn't.

**Approach (decided).** Build the custom blocks as **ACF Blocks via Secure Custom Fields** (free): `block.json` plus a PHP `render_template`. Fall back to a native block with server-side `render_callback` only for a block that ACF Blocks can't produce. Either way the saved and rendered output must not add wrapper `<div>`s, `wp-block-*` classes on these custom blocks, inline styles or WordPress block CSS.

**Block allowlist** (`allowed_block_types_all` for posts and pages): core paragraph, heading (H2 and H3 only), list, quote (mapped to the pull quote), image, table (mapped to the table block below), plus the custom blocks here. Disable columns, buttons, cover, gallery, embeds, HTML, separators, spacers, colours, font sizes and alignment options.

### 5.1 Section heading (core heading, H2)
```html
<h2 id="benefits">থানকুনি পাতার ১০টি উপকারিতা</h2>
```
- `id` is required, Latin, and unique within the post. Auto-generate it from the order (`s1`, `s2`, …) or from an editor-supplied Latin anchor.
- The table of contents and the scroll-spy depend on this `id`.

### 5.2 Numbered item `gn/numbered-heading`
```html
<h3 id="b1"><span class="gn-num">১</span><span>হজমে সাহায্য করতে পারে</span></h3>
```
- The number auto-increments within the post (resetting after each H2 is fine). It is stored as an integer and **rendered in Bengali numerals** (১ ২ ৩ … ১০).
- `id` is auto-generated (`b1`, `b2`, …).
- A paragraph must follow it. Never put an ad between a heading and its paragraph.

### 5.3 Key takeaways `gn/takeaways`
```html
<div class="gn-takeaways"><h4>Key takeaways</h4><ul>
  <li>…</li><li>…</li><li>…</li>
</ul></div>
```
- Exactly 3 items. The heading text "Key takeaways" is fixed (Latin, by design).
- Placed first in the article; at most one per post.

### 5.4 Pull quote (core quote → render override)
```html
<blockquote>“ঘরের কাছের গাছটাই অনেক সময় সবচেয়ে কাজের বন্ধু।”<cite>— ঘরোয়া নিরাময়ের রান্নাঘর থেকে</cite></blockquote>
```
- No inner `<p>`, no classes on the blockquote. The citation is optional.

### 5.5 Table `gn/table`
```html
<figure class="gn-table-wrap"><table>
  <thead><tr><th>উপাদান</th><th>কী কাজে লাগে</th><th>পরিমাণ (প্রতি ১০০ গ্রাম)</th></tr></thead>
  <tbody><tr><td>ভিটামিন সি</td><td>রোগপ্রতিরোধ</td><td class="num">≈ 48 mg</td></tr></tbody>
</table><figcaption>…</figcaption></figure>
```
- One header row. A per-column "numeric" toggle adds `class="num"` to that column's cells (Latin numerals, Poppins).
- The caption is optional.

### 5.6 Recipe / how-to `gn/recipe`
```html
<div class="gn-recipe">
  <div class="gn-recipe__head"><h3 class="gn-recipe__title">থানকুনি পাতার রস</h3>
    <div class="gn-recipe__facts"><span>5 min</span><span>2 servings</span></div></div>
  <div class="gn-recipe__grid">
    <div><h4>Ingredients</h4><ul>
      <li><span>টাটকা থানকুনি পাতা</span><span>১ মুঠো</span></li>
    </ul></div>
    <div><h4>Method</h4><ol>
      <li>পাতা ভালো করে কয়েকবার ধুয়ে নিন।</li>
    </ol></div>
  </div>
</div>
```
- Fields:
  - title
  - minutes (integer), rendered as `N min`
  - servings (integer), rendered as `N servings`
  - ingredients repeater: name, amount (amount in Bengali)
  - steps repeater: text
- The "Ingredients" and "Method" headings are fixed Latin labels.
- **Keep this exact structure.** Astro parses it to emit `HowTo` structured data.

### 5.7 Tip callout `gn/callout-tip`
```html
<div class="gn-callout gn-callout--tip"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 19C5 11 10 6 19 5c0 9-5 14-14 14z"/><path d="M5 19 13 11"/></svg><div><span class="gn-callout__title">ছাদবাগানের টিপস</span>…text, may contain links…</div></div>
```

### 5.8 Caution callout `gn/callout-caution` — required on health articles
```html
<div class="gn-callout gn-callout--caution"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4M12 17h.01"/></svg><div><span class="gn-callout__title">কারা এড়িয়ে চলবেন</span><ul>
  <li>গর্ভবতী ও স্তন্যদানকারী মায়েরা</li>
</ul>নিয়মিত খাওয়ার আগে চিকিৎসকের সঙ্গে কথা বলুন।</div></div>
```
- Title defaults to "কারা এড়িয়ে চলবেন". Fields: list items and a closing sentence.
- The amber style is reserved for this block and the site disclaimer. No other block may use it.

### 5.9 References `gn/references`
```html
<div class="gn-refs"><ol>
  <li>সূত্রের শিরোনাম, প্রকাশক, সাল। <a href="https://…">লিংক</a></li>
</ol></div>
```
- Required on health articles (warning, not a block). Links must be real sources.

### 5.10 Inline figure (core image → render override)
```html
<figure class="gn-figure"><img src="…" alt="…" width="…" height="…" loading="lazy" srcset="…" sizes="(max-width: 720px) 100vw, 680px"><figcaption>ক্যাপশন <span>Photo: …</span></figcaption></figure>
```
- Alt text is required. The credit comes from the attachment's `mediaFields.credit`.

### 5.11 In-article ad marker `gn/ad-slot`
```html
<!-- gn-ad -->
```
- Renders **only** this HTML comment; Astro replaces it with the ad container. At most one per post (`"multiple": false`).
- Not allowed before the second H2, and not directly after any heading (validate on publish).
- Make sure the comment survives `the_content` filters (wptexturize, wpautop) intact.

### 5.12 Paragraphs, links and lists
- Plain `<p>`, `<a href>`, `<strong>`, `<em>`, `<ul>`/`<ol>`/`<li>`. No classes, no inline styles, no `target="_blank"` on internal links.
- Internal links must use Astro URLs (section 6). The link-rewrite filter should also fix links inside content.
- The first paragraph gets a drop cap automatically from CSS; nothing is needed in WordPress.

**Acceptance:** recreate the reference article (Astro repo: `src/data/articles/thankuni-patar-upokarita.html`) with these blocks. The GraphQL `content` must match it structurally (compare by DOM, not string), and it must render identically in Astro.

---

## 6. URLs, link rewriting and redirects — Must

**Public URL map.** Everything below is served by Astro, so WordPress must *produce* these URLs everywhere: GraphQL `uri`/`link`, SEO canonicals, admin "View" links and content links.

| Content | Public URL |
|---|---|
| Article | `https://domain.com/<post-slug>/` |
| Category | `https://domain.com/<category-slug>/` (page n: `/<category-slug>/<n>/`) |
| Tag | `https://domain.com/tag/<tag-slug>/` |
| Author | `https://domain.com/author/<user-slug>/` |
| Video | `https://domain.com/video/<video-slug>/` |
| Info page | `https://domain.com/<page-slug>/` |
| Latest | `https://domain.com/latest/` |
| Search | `https://domain.com/search/?q=…` |

All internal URLs end in `/`.

**`ghoroa-core` must:**
- Filter `post_link`, `post_type_link` (video), `page_link`, `term_link`, `author_link` and the preview link to produce the URLs above.
- **301-redirect** any front-end request WordPress would render for these items to the Astro URL. This covers a single post, page, video, category, tag, author, date archive, attachment page or front-end search under `/shop/…`. Shop, cart, checkout, account, `wp-admin`, `wp-login.php`, `wp-json` and `graphql` are untouched.
- Disable feeds (or 301 them to Astro once it publishes RSS), and disable the WordPress core sitemaps for posts, pages, users and taxonomies (see section 9).

**Acceptance:**
- No public `/shop/<article-slug>` URL returns 200; each 301s to `domain.com/<slug>/`.
- GraphQL `uri` and the SEO canonical for a post equal its Astro URL.

---

## 7. Editorial safeguards (publish-time validation) — Must

Implement these as checks on the transition to `publish` (and `future`). They must work in the block editor: block publishing with a clear Bengali message, and show warnings in a pre-publish panel.

| Check | Applies to | Block or warn |
|---|---|---|
| Slug is ASCII `a-z0-9-` | posts, videos, pages, tags, users | Block |
| Slug isn't reserved: `about contact privacy medical-disclaimer newsletter search latest video tag author shop page 404 robots.txt sitemap-index.xml sitemap-0.xml pagefind scripts fonts images _astro preview`, or any of the four category slugs | posts, pages, videos | Block |
| Exactly one of the four categories | posts | Block |
| Excerpt, `dek` and featured image (with alt) present | posts | Block |
| `isHealth` on → contains a `gn/callout-caution` block | posts | Block |
| `isHealth` on → `healthClaimsReviewed` is ticked by an editor or admin | posts | Block |
| `isHealth` on → contains a `gn/references` block | posts | Warn |
| At most one `gn/ad-slot`, after the second H2, not right after a heading | posts | Block |
| Every H2 has a unique `id` | posts, pages | Block (or auto-fix) |
| Cure-claim phrases in title, excerpt, dek or body: সারিয়ে দেয়, সারিয়ে তোলে, নিরাময় করে, রোগ সারায়, চিরতরে, ১০০%, গ্যারান্টি, ওষুধের দরকার নেই (keep the list in one editable option) | posts | Warn, and list each occurrence |
| Latin digits inside a Bengali title (e.g. `10টি`) | posts | Warn |
| No emoji in title, excerpt or dek | posts | Warn |

The Astro build repeats the critical checks (caution callout, ad count) and fails if they're violated, so WordPress is the first line of defence and Astro is the backstop.

---

## 8. Roles and capabilities — Must

| Role | Can do |
|---|---|
| Administrator | Everything; 2FA required |
| Editor | Publish, edit any post, tick `healthClaimsReviewed`, edit homepage options and pages; 2FA required |
| Author | Write and edit own posts; **can't publish health articles** (`isHealth` on): those go to Pending for an editor |
| Contributor | Drafts only |
| **Medical reviewer** (new, `medical_reviewer`) | Read and comment on posts in review; the only role that can set `medicallyReviewed` and `medicalReviewer`. The reviewer must be a registered practitioner, recorded on their user profile (name and registration number) |
| Build bot (new, `gn_build`) | Read-only access to drafts for the preview server via an Application Password; no admin UI |
| Shop manager | Later (WooCommerce default) |

---

## 9. SEO — Must

- **Plugin:** Rank Math SEO (free). Editors fill its title, description, social and robots fields per post, page and video.
- **Exposing it to GraphQL:** the community plugin "WPGraphQL for Rank Math SEO" (AxeWP) exists but was last updated in June 2025, so don't depend on it. Instead, `ghoroa-core` registers its own `gnSeo` field on `Post`, `Page`, `Video`, `Category` and `Tag`. It reads Rank Math's stored meta and resolves Rank Math variables (e.g. `%title% %sep% %sitename%`) with Rank Math's own replace-vars helper:

  | `gnSeo` field | Rank Math meta |
  |---|---|
  | `title` | `rank_math_title` (variables resolved) |
  | `description` | `rank_math_description` |
  | `canonical` | `rank_math_canonical_url`, else the Astro URL from section 6 |
  | `noindex` | `rank_math_robots` contains `noindex` |
  | `ogTitle`, `ogDescription`, `ogImage` | `rank_math_facebook_title`, `rank_math_facebook_description`, `rank_math_facebook_image` |

  Every field is nullable. Astro falls back to its own title, dek and featured image when a field is empty. This avoids depending on a stale third-party plugin, and the field shape stays the same if the SEO plugin ever changes.
- In Rank Math, turn off: schema output, its redirections module (redirects live in `ghoroa-core`), breadcrumbs, the 404 monitor, and the post/page/category/tag/author sitemaps (see below).
  - **As built (M3):** `wp gn setup` applies these. It also switches off the video sitemap, the HTML sitemap and **Instant Indexing**, which would otherwise ping search engines with WordPress URLs on publish.
  - Rank Math free stays dormant until an account is connected or its wizard's "Skip" is clicked. Setup sets that skip flag (`rank_math_registration_skip`), so no Rank Math account is needed.
  - With every content type off, `sitemap_index.xml` returns 404 until shop products exist.
- **Structured data is generated by Astro** (Article, BreadcrumbList, HowTo, VideoObject). Turn off the plugin's JSON-LD for headless content so nothing is duplicated.
- **Sitemaps:** Astro owns the content sitemap. The WordPress or SEO-plugin sitemap must list **only shop URLs** (products and product categories, later) and must never list posts, pages, categories, tags, authors or videos. Tell the Astro side its final URL (e.g. `/shop/sitemap_index.xml`) so it can go into `robots.txt`.
- Astro serves the single `robots.txt` for the whole domain. WordPress must not try to serve one at the root.

---

## 10. Publish webhook and preview

### 10.1 Rebuild webhook — Must
- Trigger on:
  - publish, update, unpublish, trash or delete of a post, video or page
  - a scheduled post going live
  - edits to the homepage options
  - create, edit or delete of a category or tag
  - author profile edits
  - attachment alt, caption or credit edits on media used by published content
- Action: an empty `POST` to the site Worker's **Workers Builds deploy hook URL** (Settings → Builds → Deploy Hooks). The URL itself is the secret; treat it like a password and keep it out of the database export if possible.
- **Debounce** to one build per 60 seconds. Cloudflare also skips duplicate hook calls that arrive before a build starts (limit: 10 builds a minute per Worker), but keep the debounce so bulk edits don't burn free-plan build time.
- Retry with backoff, and log the last 50 deliveries (event, post, time, HTTP status) in an admin screen.
- Settings (hook URL, on/off) live in `wp-config.php` constants (`GN_BUILD_HOOK_URL`), not in plain options. Never hard-code them.

### 10.2 Preview — Should
- Rewrite the editor's Preview button to `https://domain.com/preview/?type=post&id=<id>&token=<t>`.
- `token` is an HMAC of `id` plus an expiry 10 minutes ahead, using a secret shared with the Astro preview route.
- The Astro preview route (to be built on the Astro side) runs as an on-demand route in the site Worker, like the form endpoints; every other page stays static. It queries drafts with the `gn_build` Application Password.

---

## 11. Seed data — Must (needed for testing both sides)

- The four categories with descriptions.
- Tags: থানকুনি `thankuni`, হজম `hojom`, ত্বকের যত্ন `toker-jotno`, ঔষধি গাছ `oushodhi-gach`, গাছপালা `gachpala`, সর্দি-কাশি `shordi-kashi`, জৈব চাষ `jaibo-chash`.
- Three authors: নুসরাত জাহান `nusrat-jahan` (with bio), রুমানা আক্তার `rumana-akter`, তানভীর হাসান `tanvir-hasan`.
- One full reference article: থানকুনি পাতার ১০টি অভাবনীয় উপকারিতা, `thankuni-patar-upokarita`. It must use every block in section 5.
- At least 12 more summary-level posts spread across the categories, so pagination and the homepage blocks have content.
- Six videos with durations, at least one with a real `youtubeId`.
- The homepage options filled in.

The content is exported as JSON at `docs/seed/sample-content.json` in the Astro repo (regenerate with `npm run export-seed` there). Field names follow the Astro types; map them per §4. Write the importer as a WP-CLI command in `ghoroa-core` (e.g. `wp gn seed <file>`), idempotent by slug.
- Only the reference article has a full body. For the others, the importer sets `dek` = excerpt, `isHealth` by category default (§4.5), a short placeholder body, and a shared placeholder featured image (with alt text). That lets them pass the publish checks in §7.
- Placeholder facts in the reference article (the nutrition table and the references) must be replaced before anything goes public.

---

## 12. Should / Later items (don't block v1)

- **`/shop` theme (Should, before the shop opens).** A minimal classic or child theme for the few pages WordPress still renders: account, login, and later product, cart and checkout.
  - It reuses the site's `tokens.css` and `ghoroa.css` from one shared, long-cached path.
  - It uses the same `gn-theme` localStorage key and no-flash head script for light and dark mode.
  - It copies the Astro header and footer markup, with a cart link.
  - It self-hosts the Kalpurush font from the same `/fonts/` path.
  - The Astro side must publish the CSS at an unhashed path for this. Coordinate before starting.
- **WooCommerce (Later).**
  - Currency BDT, store address, shop pages hidden until phase 1.
  - Product `type` attribute: physical, digital, service or affiliate. Affiliate items are External products.
  - Store API on the same domain for the header cart count.
  - Payments (bKash, Nagad, cards, COD with OTP), courier API, digital downloads, and bookings at `/shop/consultation`.
- **Analytics and consent (Later).** One GA4 property and one consent banner shared with Astro; the consent cookie must be readable on both halves.
- **Legal pages (Later).** Terms, refund and affiliate-disclosure pages when the shop opens.

---

## 13. Open decisions

| Decision | Options | Needed by |
|---|---|---|
| ~~Hosting~~ | **Decided:** Cloudflare (DNS, site Worker with static assets, `shop-router` Worker) + Hostinger KVM 1 VPS for WordPress; see 3.1 | — |
| ~~Newsletter / WhatsApp provider~~ | **Decided:** Kit (free, up to 10,000 subscribers) plus a WhatsApp Channel link. The form lives on the Astro side; nothing needed in WordPress | — |
| Real domain | Being registered at a separate registrar | Link rewriting, canonicals, cookies, Worker route |
| Server panel (deferred) | None (plain Nginx) vs CloudPanel (recommended, free) vs another panel. Decide at production deployment; local development doesn't need it | Production deployment (§3.2) |
| SMTP provider (Should) | e.g. Brevo (free tier) vs Amazon SES; not needed for v1 (§3.2) | Before the shop opens |
| ~~Field plugin~~ | **Decided:** Secure Custom Fields (free), with the day-one GraphQL check in section 3 | — |
| ~~SEO plugin~~ | **Decided:** Rank Math free, exposed through `ghoroa-core`'s `gnSeo` field (section 9) | — |
| ~~Custom blocks~~ | **Decided:** ACF Blocks via Secure Custom Fields (free; PHP templates give exact markup). Native blocks with `render_callback` only as a fallback for a block ACF Blocks can't do | — |
| ~~Media delivery~~ | **Decided:** link images straight from `/shop/wp-content/uploads`, cached by the Worker; watch the Worker request budget (§3.1) | — |

## 14. Definition of done for WordPress v1

1. Fresh install at `https://domain.com/shop` on the Hostinger VPS, reachable **only** through the `shop-router` Worker. Direct requests to the VPS IP or to `wp-origin.domain.com` without the origin key get refused. Hardened as in section 3, with a daily R2 backup and one tested restore.
2. Seed data from section 11 loaded. Every query in section 4.9 returns the expected shape.
3. The reference article built with the custom blocks renders in Astro identically to the current sample.
4. Every check in section 7 blocks or warns as specified, verified with one deliberately bad post per check.
5. Publishing a post triggers exactly one Astro build within about 60 seconds, and the post is live at `domain.com/<slug>/`.
6. `/shop/<post-slug>` 301s to the Astro URL. No WordPress-rendered article, archive or feed URL is reachable or listed in a sitemap.
7. Roles behave as in section 8, including an author being unable to publish a health article directly.
