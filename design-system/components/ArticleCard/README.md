The standard article card for the 3-column content grid, with a gentle hover lift.

**Markup:** `article.gn-card` > linked `.gn-img` (16:10) > `.gn-card__body`: a `.gn-tag` (category), `h3.gn-card__title` (style `heading-md`), `p.gn-card__excerpt` (clamped to 3 lines), `.gn-meta` row.

- Lay cards out in `.gn-grid` (1 / 2 / 3 columns). Use `.gn-grid__full` for a full-width row such as an ad band, and an `AdSlot` may take one card's cell.
- On hover the card lifts 2px, gains `shadow-hover` and a `secondary` border, the title turns `primary`, and the image takes a faint green wash. `.is-hover` forces this state for documentation.
- The whole card is a link target, but keep one real `<a>` on the title for screen readers.
