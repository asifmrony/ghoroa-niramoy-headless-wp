# GhoroaNiramoy — instructions for Claude

This folder is the **GhoroaNiramoy (ঘরোয়া নিরাময়) design system**, version 1, exported for building the real site. GhoroaNiramoy is a Bengali blog about natural home remedies, medicinal plants, kitchen spices and rooftop gardening. Treat everything here as the source of truth for look, voice and structure.

## Read first

1. `docs/brand-book.md`: voice, categories, colour, type, spacing, logo and iconography rules.
2. `docs/architecture.md`: the platform (Astro on `domain.com`, WordPress + WooCommerce at `domain.com/shop` as shop and headless CMS) and the setup checklist.
3. `components/<Name>/README.md`: markup and rules per component.
4. `previews/*.html`: working reference HTML for every component and both full pages (`HomePage`, `ArticlePage`). Open `previews/index.html` in a browser.

## Files

| Path | What it is | How to use it |
| --- | --- | --- |
| `tokens/tokens.json` | Source of truth for all tokens (colours with light/dark values, type, spacing, radius, shadow, size) | Change tokens here, then regenerate `tokens.css` |
| `tokens/tokens.css` | Generated CSS custom properties: `:root`/`[data-theme="light"]`, `[data-theme="dark"]`, type-style classes, Kalpurush `@font-face` | Import globally, before `ghoroa.css` |
| `css/ghoroa.css` | All component styles, class prefix `gn-` (imports Poppins + Hind Siliguri from Google Fonts) | Import globally after tokens |
| `fonts/Kalpurush.woff2` | Unicode Kalpurush (Regular 400) | Serve from `/fonts/`, preload on key pages |
| `assets/logos/` | `ghoroa-lockup-bn.png` (header lockup), `ghoroa-mark.png` (mark) | Use exactly as supplied; never redraw or recolour |
| `scripts/theme-head.html` | No-flash theme snippet | First thing in `<head>` |
| `scripts/theme-toggle.js` | Header light/dark toggle | Load once per page |
| `scripts/article.js` | Reading progress bar and TOC highlight | Article pages only |

## Building the Astro site

- Put `tokens.css` and `ghoroa.css` in `src/styles/` and import them in the base layout. Put the font in `public/fonts/` (the `@font-face` URL in `tokens.css` is `../fonts/Kalpurush.woff2`; adjust it to `/fonts/Kalpurush.woff2`). Put logos in `public/images/`.
- Turn each preview into an Astro component with props, keeping the class names and markup structure: `DisclaimerBanner`, `SiteHeader` (with search overlay and theme toggle), `HeroFeature`, `LatestList`, `ArticleCard`, `CategoryTag`, `AdSlot`, `CategorySection`, `VideoRow`, `SiteFooter`, plus page-level `ArticleLayout` from `ArticlePage`.
- `BaseLayout`: `<html lang="bn">`, the theme-head snippet, `<body class="gn">`, DisclaimerBanner, SiteHeader, slot, SiteFooter.
- Data comes from WordPress (WPGraphQL or REST) at build time; see `docs/architecture.md` for the content model.
- Ship zero client JavaScript except the theme toggle, search overlay, and `article.js`.

## Rules that must not break

- **Tokens only.** Never hard-code hex colours, font sizes or spacing in components; use `var(--token)`. Both themes must keep working.
- **Contrast.** Text ≥ 4.5:1 on its ground in both themes. `secondary` is a fill, never text on light grounds. Text on `band-bg`/`footer-bg` uses `on-band`.
- **Type.** Kalpurush for all Bengali (`--font-bn`, `--font-bn-alt`), Poppins (`--font-latin`) for English and metadata numerals. Keep Bengali line heights ≥ 1.45× (headings) and 1.75× (body); never letter-space Bengali. Bengali numerals inside Bengali headlines.
- **Health claims.** Copy never promises a cure: "সাহায্য করতে পারে", not "সারিয়ে দেয়". Every health article shows the DisclaimerBanner and a caution callout.
- **Ads.** Every `AdSlot` keeps its min-height (250px; leaderboard 120px) and the "Ad Space" label. Never put an ad inside the first screen of article text or between a heading and its paragraph.
- **Categories.** Exactly four, in order: ঘরোয়া টোটকা, ভেষজ উদ্ভিদ, রান্নাঘরের মসলা, ছাদবাগান. Nav adds প্রচ্ছদ first and ভিডিও last.
- **Logo.** Header uses the supplied lockup, 104px tall (72px on mobile), on `brand-plate` in dark mode.
- **No AI tropes:** no emoji as icons, no gradient-purple, no left-border accent cards. Icons are 1.8px-stroke inline SVGs with `currentColor`.
- **Accessibility.** Visible 2px `focus-ring` on every interactive element; real `<button>`/`<a>`; alt text on images; `aria-current` on active nav and TOC.

## Not designed yet

Shop-side pieces (ProductCard, cart, checkout, booking, AffiliateBox, DisclosureNote, category archive page, author page, search results page) are not in v1. Build them from the same tokens and patterns, and keep them visually consistent with the existing cards and buttons.
