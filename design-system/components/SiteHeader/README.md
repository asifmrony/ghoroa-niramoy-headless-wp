The site header: a logo row, a category navigation row, and the search overlay it opens.

**Logo row** (`.gn-header__bar`, a three-column grid): left holds a short promo link (hidden under 720px); centre holds `a.gn-brand` with the supplied lockup `img.gn-brand__lockup` (`assets/Logos/ghoroa-lockup-bn.png`, 104px tall, 72px under 720px); right holds the search `button.gn-iconbtn`, the theme toggle, and a `.gn-btn--primary` newsletter link.

**Theme toggle** (`button.gn-iconbtn.gn-theme-toggle`, `aria-pressed`): it holds two inline icons, `.gn-icon-moon` and `.gn-icon-sun`. CSS shows the moon in light (switch to dark) and the sun in dark (switch to light). A click flips `data-theme` on `<html>` between `light` and `dark`, saves the choice to `localStorage` under `gn-theme`, and updates `aria-pressed` and the `aria-label` (ডার্ক মোড / লাইট মোড). The preview carries the reference script. Pair it with the no-flash `<head>` snippet in the brand book. In dark, the lockup sits on a `brand-plate` tile.

**Nav row** (`nav.gn-nav` > `ul.gn-nav__list`): প্রচ্ছদ, ঘরোয়া টোটকা, ভেষজ উদ্ভিদ, রান্নাঘরের মসলা, ছাদবাগান, ভিডিও. Mark the current one with `aria-current="page"` (`primary` text, 2px `primary` underline). On small screens the row scrolls horizontally.

**Search overlay** (`.gn-search`, fixed with an `overlay` scrim): `.gn-search__panel` holds a pill `.gn-search__field` input, a "Popular searches" label and `.gn-tag` chips. The consumer handles open/close, Esc, and focus trapping, and moves focus to the input on open. `.gn-search--inline` is for documentation only.
