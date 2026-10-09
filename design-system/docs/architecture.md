# GhoroaNiramoy — platform architecture

Readers browse a fast static **Astro** site on `domain.com`. **One WordPress + WooCommerce install at `domain.com/shop`** runs the shop and consultation bookings, and also acts as the headless CMS that feeds Astro. Both sit on one domain, with one routing rule between them.

| Path | Served by | What lives there |
| --- | --- | --- |
| `domain.com/` and every other path | Astro (static pages, rebuilt on publish) | Home, categories, articles, Ghoroa Originals videos, affiliate boxes |
| `domain.com/shop/*` | WordPress + WooCommerce | Shop, product pages, cart, checkout, account, digital downloads, `/shop/consultation` bookings, `wp-admin`, API |

```
Readers ──► Cloudflare edge (domain.com) ──┬── every other path ──► Astro static site
                                           └── /shop/* ───────────► WordPress + WooCommerce (one install)
WordPress ── publish webhook + content API ──► Astro build ── deploy ──► Astro static site
WordPress ◄──► payments (bKash, Nagad, cards, COD) and courier APIs
```

## Content model (in WordPress)

- Posts are articles. Categories are the four sections: ঘরোয়া টোটকা, ভেষজ উদ্ভিদ, রান্নাঘরের মসলা, ছাদবাগান. Tags and authors as normal.
- Custom post type `video` for Ghoroa Originals: video ID, duration, thumbnail, related products.
- Per-article fields: reading time, featured flag, featured product, "used in this article" products, medical-review flag and reviewer, health-claims review checkbox.
- A homepage options page: hero article, category order, video row picks.
- Products have a `type` field (physical / digital / service / affiliate); affiliate items are WooCommerce "external" products.

## Setup checklist

### A. Hosting and routing
- [x] Choose: (a) one VPS (Singapore) with Nginx serving Astro's build at `/` and PHP for `/shop`, behind Cloudflare; or (b) Astro on Cloudflare Pages + WordPress elsewhere + a Cloudflare Worker routing `domain.com/shop*`. **Decided: (b)**, with the Astro site on Cloudflare Workers static assets (Astro 7's adapter targets Workers), WordPress on a Hostinger KVM 1 VPS and DNS on Cloudflare (details in `docs/wordpress-v1-requirements.md` §3).
- [ ] Install WordPress with site address `https://domain.com/shop` so everything (wp-admin, wp-json, uploads) lives under `/shop`.
- [ ] Forward the original protocol and host; trust them in `wp-config.php`.
- [ ] Only accept WordPress traffic from the proxy/CDN; redirect the raw origin hostname.
- [ ] Give the Astro build a private route to the WordPress API.

### B. WordPress as headless CMS
- [ ] WPGraphQL (or REST) + the SEO plugin's GraphQL extension + custom-field extension.
- [ ] Register the `video` post type, article fields and homepage options page.
- [ ] Small plugin: rewrite post/category/video links to `domain.com/<slug>`.
- [ ] Same plugin: redirect WordPress-rendered article/archive URLs under `/shop/...` to Astro; disable unused comments and feeds.
- [ ] Publish webhook → Astro rebuild (or cache purge) on post, video, options or product-price changes.
- [ ] Preview button → Astro preview route with a secret token.
- [ ] Roles: editors/authors, shop manager, customers; 2FA for admins.

### C. Astro site
- [ ] Build the design-system components as Astro components (this package).
- [ ] Pages: home, 4 categories, articles, video pages, authors, paginated archives.
- [ ] SEO from WordPress: title, description, canonical, Open Graph, Article/Video/FAQ/HowTo/BreadcrumbList structured data.
- [ ] Sitemaps: Astro for content, WooCommerce/SEO plugin for `/shop`; both in robots.txt and Search Console.
- [ ] Product/affiliate cards fetched by product ID at build time.
- [ ] Search (Pagefind or the WordPress API); newsletter/WhatsApp sign-up.

### D. Caching and security (critical)
- [ ] Cache Astro pages at the edge; purge on rebuild.
- [ ] Never cache `/shop/cart*`, `/shop/checkout*`, `/shop/my-account*`, `/shop/wp-admin*`, `/shop/wp-json*`, `/shop/wp-login.php`, responses that set cookies, or requests with `woocommerce_items_in_cart`, `wp_woocommerce_session_*`, `wordpress_logged_in_*` cookies.
- [ ] Cache product/listing pages for anonymous visitors only.
- [ ] Exempt payment-gateway callback URLs from cache, bot challenges and rate limits.
- [ ] Rate-limit wp-login, WAF on, few plugins, kept updated; tested daily backups.

### E. Shop integrations
- [ ] Gateway aggregator (bKash, Nagad, cards) + COD with phone/OTP confirmation.
- [ ] Courier API with COD reconciliation; delivery zones and fees.
- [ ] Digital downloads (expiring links, stamping); booking plugin at `/shop/consultation`.
- [ ] Light WordPress theme built from the same CSS so `/shop` matches.
- [ ] Refund, privacy, terms, affiliate disclosure, medical disclaimer pages.

### F. Consistency across both halves
- [ ] Shared header/footer markup and CSS; cart link in both.
- [ ] Cart count in the Astro header via the WooCommerce Store API (same domain).
- [ ] Same `gn-theme` key and no-flash head script on both halves.
- [ ] Kalpurush font and CSS served from one path, long-cached.
- [ ] One GA4 property and one consent banner.

### G. Before launch
- [ ] Two-browser cart isolation test.
- [ ] Real test payments: success, failure, cancel, refund.
- [ ] Core Web Vitals on a mid-range Android over mobile data (article + product page).
- [ ] Crawl: no `/shop/<article>` duplicates indexed, canonicals correct, no 404s.
- [ ] Publish a test article and confirm it goes live via the webhook.

## Roadmap (revenue lines open one at a time)

0. Foundation (months 0–3): legal setup, Astro site + `/shop` install, GA4, claims rules, lead magnet.
1. Affiliate (3–6): Daraz + local partners, AffiliateBox, disclosure, email/WhatsApp list.
2. Digital (6–9): e-books and guides, gateway, ProductCard, cart, checkout.
3. Services (9–12): consultations and workshops, booking.
4. Physical (12+): BFSA registration, plants (Dhaka first), kits, courier, COD.

Shop-side design components (ProductCard, cart, checkout, booking, AffiliateBox, DisclosureNote) are **not** in this package yet; design them when their phase starts, from the same tokens.
