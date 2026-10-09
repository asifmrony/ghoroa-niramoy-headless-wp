An empty, labelled AdSense container that reserves space so the layout never shifts.

**Markup:** `aside.gn-ad[aria-label=বিজ্ঞাপন]` > `span.gn-ad__label` "Ad Space" and the AdSense `<ins class="adsbygoogle">` tag (the preview shows a size note instead). `.gn-ad--leaderboard` is the 120px-tall horizontal variant; wrap it in `.gn-ad-band` for hairlines above and below.

- `min-height: var(--ad-min-height)` (250px) on rectangles is required. Never collapse the slot when no ad fills it.
- Placement on the homepage: the right rail under সর্বশেষ, a band after the first grid row, one in-grid rectangle in the second row, and a leaderboard before the footer. Don't place one inside the hero or between a heading and its content.
