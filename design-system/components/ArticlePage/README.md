The blog details (single article) page, composed from the system's components plus the article-only styles in `bundle.css`. It's a reference composition, not an export.

**Order, top to bottom**

1. `DisclaimerBanner`, `SiteHeader` (the article's category active in the nav), then `.gn-progress`, a 3px `primary` reading-progress bar pinned to the top.
2. `.gn-breadcrumb`: প্রচ্ছদ › category › title.
3. `.gn-article-head`, centred and at most 820px wide: category `.gn-tag`, `h1.gn-article-title` (44/60, 32/46 on mobile), `.gn-article-dek` (one or two sentences), then the meta row: `.gn-byline` (avatar, author, Poppins date / updated / reading time), the `.gn-review` "চিকিৎসক কর্তৃক পর্যালোচিত" badge (show it only when a registered practitioner has actually reviewed the piece), and `.gn-share` (Facebook, WhatsApp, copy link).
4. `.gn-article-lead`: a 2:1 lead image at most 1040px wide, with a caption and photo credit.
5. `.gn-article-layout`, a three-column grid at 1100px and wider: a sticky `.gn-toc` (200px), the main column (`.gn-article-main`), and a sticky right rail (300px) holding an `AdSlot` and a `.gn-popular` list. Under 1100px it stacks: the TOC (a collapsible `<details>`) comes first, then the article, then the rail.
6. Main column: `article.gn-prose` (max 680px), then `.gn-article-foot` with tags, a "লেখাটি কি কাজে লেগেছে?" helpful row with share buttons, a `.gn-bio` author card, and `.gn-pagenav` previous/next links.
7. "আরও পড়ুন": three `ArticleCard`s from the same category, a leaderboard `AdSlot`, then `SiteFooter`.

**Inside `.gn-prose`**

- Body text is Kalpurush 18/34 (17/32 on mobile), and the first paragraph opens with a `primary` drop cap.
- `h2` section headings carry a 2px `primary` rule above them and an `id` the TOC links to. `h3` items in a numbered list put the number in a `.gn-num` disc, written in Bengali numerals.
- `.gn-takeaways` is a three-bullet summary at the top. Readers who skim get the answer, and search engines get a clean snippet.
- One in-article `AdSlot` after the fourth item. Never place an ad inside the first screen of text, and never between a heading and its paragraph.
- A centred `blockquote` pull quote (Kalpurush 26/42, `primary`).
- `table` inside `figure.gn-table-wrap` (scrolls sideways on mobile), with Poppins numbers in `td.num`.
- `.gn-recipe`: the how-to box, with ingredients (name and amount) and method steps, plus time and servings in Poppins.
- `.gn-callout--tip` (`leaf-tint`) for gardening or kitchen tips. `.gn-callout--caution` (the amber trio) for "who should avoid this" on every remedy article.
- `.gn-refs`: numbered sources at the end. Health articles cite real sources.

**Behaviour**

The preview includes the reference script. On scroll it fills `.gn-progress__bar` and sets `aria-current="true"` on the TOC link of the section in view. Headings carry `scroll-margin-top`, so TOC jumps don't hide them under the sticky bar.

**Structured data to emit on the live page:** `Article` (headline, author, datePublished, dateModified, image), `BreadcrumbList`, plus `HowTo` for the recipe box and `FAQPage` if you add a Q&A section.
