A right-rail list of the newest posts with relative times (in the role of the inspiration's "Latest").

**Markup:** `section.gn-latest` > `.gn-latest__head` (title সর্বশেষ in `primary`, `.gn-chip` filters সব / আপনার জন্য with `aria-pressed`) > `ul.gn-latest__list` of `li.gn-latest__item` (Poppins `primary` time + `heading-sm` link) > `a.gn-latest__all`.

- Show 5 items. The consumer supplies relative times ("10 min", "3 hr").
- Pair it with an `AdSlot` below in the rail (`.gn-rail`).
