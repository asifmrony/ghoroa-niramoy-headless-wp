A sticky amber notice that pins the medical disclaimer to the very top of every page.

**Markup:** `aside`/`div.gn-disclaimer[role=note]` > `.gn-container.gn-disclaimer__inner` > alert SVG (`.gn-disclaimer__icon`), `p.gn-disclaimer__text` (lead with `<strong>স্বাস্থ্য সতর্কতা:</strong>`), optional `button.gn-disclaimer__close`.

- Place it first in `<body>`, above the header. It is `position: sticky; top: 0`.
- The consumer provides the copy and decides whether it's dismissible (remember the dismissal per visitor; show it again on article pages).
- Use the amber tokens only here. Keep it to one sentence on desktop; it may wrap to three lines on mobile.
- Don't turn it into a modal, and don't hide it on health articles.
