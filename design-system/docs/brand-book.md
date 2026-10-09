GhoroaNiramoy (ঘরোয়া নিরাময়) is a Bengali blog about natural home remedies, herbs, kitchen spices and rooftop gardening. Its tagline is **প্রকৃতির যত্নে, সুস্থতার পথে** ("in nature's care, on the path to wellness"). The site should feel calm, premium and unhurried: a well-kept kitchen garden, not a pharmacy and not a tabloid.

## Content fundamentals

- Write in plain, warm standard Bengali (চলিত ভাষা). Address the reader as **আপনি**, never তুমি.
- Headlines promise something concrete: "থানকুনি পাতার ১০টি অভাবনীয় উপকারিতা", "টবে লেবু গাছ: ফলন বাড়ানোর ৭টি কৌশল". Use Bengali numerals (১০টি) inside Bengali headlines; use Latin numerals in Poppins for metadata (5 min read, 2 Oct 2026, 11:30).
- Excerpts run one or two sentences and say what the reader will learn. Health copy never promises a cure; say "সাহায্য করতে পারে" (may help), not "সারিয়ে দেয়" (cures).
- Every health article sits under the medical disclaimer (`DisclaimerBanner`). Its copy: "এই ব্লগের তথ্য শুধুমাত্র সাধারণ জ্ঞানের জন্য, চিকিৎসকের পরামর্শের বিকল্প নয়।"
- No emoji, no exclamation-mark stacks, no "অবাক করা!!" clickbait.
- English is used only for the site name (GhoroaNiramoy), the "Ghoroa Originals" video series name, and small Latin labels (kickers such as FEATURED, "Ad Space").

## Categories

The navigation and the homepage blocks use exactly these four, in this order: **ঘরোয়া টোটকা** (home remedies), **ভেষজ উদ্ভিদ** (medicinal plants), **রান্নাঘরের মসলা** (kitchen spices), **ছাদবাগান** (rooftop gardening). "গাছপালা" (plants) is a secondary tag only. The nav adds প্রচ্ছদ (home) first and ভিডিও (video) last.

## Visual foundations

**Colour.** The page ground is `background` (#FFFFFF, white). Cards, the header and the hero sit on `surface` (#F5F5DC, warm beige) with a `line` hairline and `shadow-rest`. Set all headings and body text in `ink` (#1E4B3C); metadata, excerpts and captions in `ink-muted`. `primary` (#2D6A4F) is the brand green: links, the active nav underline, section rules, primary buttons and tag text. `secondary` (#74C69D) is a fill only (play discs, carousel dots, hover borders, avatars); never set text in it on light grounds, and put `on-secondary` on it. `leaf-tint` is the soft green for tags and image placeholders. `primary-deep` is the hover state of primary fills, with `on-primary` text on both. The Ghoroa Originals band sits on `band-bg` and the footer on `footer-bg`; both stay dark in either theme and take `on-band` text (`on-band-muted` for secondary footer text). The amber trio `amber-bg` / `amber-line` / `amber-ink` is reserved for the medical disclaimer; don't use it elsewhere. `logo-green` and `logo-leaf` record the logo's own greens; the UI uses `primary` and `secondary`.

**Light and dark themes.** There are two themes, `light` (the default) and `dark`, and every colour token has a value for each. Dark mode is a deep forest ground (`background` #131B17, `surface` #1B2621) with near-white `ink`. Its greens are lifted (`primary` #6CC496), so text on primary fills flips to a dark `on-primary`. Always reference tokens, never hex values, so both themes work. Readers switch themes with the toggle in `SiteHeader`, which sets `data-theme="light|dark"` on `<html>`. On first visit, follow the reader's system setting (`prefers-color-scheme`) and remember their choice in `localStorage` under `gn-theme`. Set the attribute in a tiny inline script in `<head>`, before the CSS paints, so the page never flashes the wrong theme:

```html
<script>(function(){try{var t=localStorage.getItem('gn-theme');}catch(e){}if(!t)t=matchMedia('(prefers-color-scheme: dark)').matches?'dark':'light';document.documentElement.setAttribute('data-theme',t);})();</script>
```

**Type.** Kalpurush is the face for all Bengali text, self-hosted from `fonts/Kalpurush.woff2` (Unicode build, Regular 400 only). Two Bengali families share it and differ only in their roles; Hind Siliguri is the fallback while Kalpurush loads:
- `bn` for Bengali headings: `display` (hero only), `heading-lg` (section titles), `heading-md` (card titles), `heading-sm` (list and video titles).
- `bn-alt` for Bengali reading text and UI: `body`, `body-sm`, `nav`, `tag`.
- `latin` (Poppins, from Google Fonts) for English and all numerals in metadata: `wordmark`, `meta`, `eyebrow`.

Kalpurush has no bold file, so the browser thickens it for the 600–700 heading styles. Use only the Unicode `Kalpurush.woff2`, never the ANSI (Bijoy) build, which turns Unicode text into Latin junk. Bengali needs room: keep line heights at or above 1.45× for headings and 1.75× for body (the styles already do), and never letter-space Bengali. On the live site, load the font with `font-display: swap` and preload it on the homepage.

**Space and layout.** Centre content in `container` (1200px) with `space-4` gutters on mobile and `space-8` from 768px. Separate homepage sections by `space-12`; the Ghoroa Originals band gets `space-16` above it. Article grids are 1 / 2 / 3 columns at <640 / 640 / 960px, with a `space-8` gap on desktop. Dividers are `line` hairlines (as in a newspaper's column rules), not boxes inside boxes.

**Radii.** `radius-md` for cards and images, `radius-lg` for the hero and the search panel, `radius-sm` for ad slots and small thumbnails, `radius-pill` for tags, chips and buttons.

**Elevation and motion.** Cards rest on `shadow-rest`. On hover they lift 2px, take `shadow-hover`, swap their border to `secondary`, and their title turns `primary` (250ms ease). Nothing else moves; no parallax or auto-playing video.

**Focus.** Every link, button and field shows a 2px solid `focus-ring` outline with a 2px offset (6.6:1 on `background` in light, 8.3:1 in dark).

**Imagery.** Use natural, daylight photography of real leaves, spices, kitchens and rooftops, with soft shadows and no heavy filters. Crop images 16:10 in cards, 4:3 in the hero, 16:10 in video thumbs and 1:1 in side lists. Until real images exist, use the `.gn-img` placeholder (a `leaf-tint`, `surface-sunk` or `secondary` field with a faint line-drawn leaf).

## Monetisation

Ad slots (`AdSlot`) are part of the rhythm, not an afterthought. The homepage places a 300×250 rectangle in the right rail under সর্বশেষ, a full-width leaderboard band between the first and second rows of the article grid, a rectangle in place of one card in the second row, and a leaderboard before the footer. Every slot keeps `ad-min-height` (250px; leaderboards 120px) so pages don't shift when ads load, and carries the "Ad Space" label.

## Logo and iconography

The logo is a house-shaped shield growing from two leaves (in Logos: `ghoroa-mark.png` is the mark; `ghoroa-lockup-bn.png` is the stacked Bengali lockup with the tagline). In the header, use the full lockup `ghoroa-lockup-bn.png` exactly as supplied, centred, 104px tall on desktop and 72px on mobile, with alt text "ঘরোয়া নিরাময় — প্রকৃতির যত্নে, সুস্থতার পথে". Don't retype the name beside it. In the dark theme the lockup sits on a `brand-plate` beige tile so its greens and dark wordmark keep their contrast. On `footer-bg`, place the mark on a `logo-plate` tile. Never recolour or redraw the mark.

Icons are simple 1.8px-stroke line icons with round caps, drawn inline as SVG with `currentColor` (search, close, alert triangle, chevrons, play, moon and sun for the theme toggle). No icon font. Emoji are never used as icons.

## Using the components

Components are plain HTML plus `components/bundle.css` (class prefix `gn-`); no JavaScript framework is needed. Wrap the page in `<body class="gn">`. Each component's README lists its markup. The `HomePage` preview shows the full composition: header, then hero + সর্বশেষ rail + ad, then the নতুন লেখা grid with in-grid ads, two category blocks, the Ghoroa Originals band, two category blocks side by side, a leaderboard, and the footer. The `ArticlePage` preview shows the blog details page: breadcrumb, a centred article header with byline, review badge and share buttons, a 2:1 lead image, then a sticky table of contents, the reading column (`.gn-prose`, max 680px) and a sticky ad + popular rail, followed by tags, author bio, previous/next and three related cards.
