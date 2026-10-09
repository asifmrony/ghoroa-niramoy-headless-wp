# GhoroaNiramoy — WordPress (headless CMS + future shop)

WordPress here is the content backend for the Astro site (separate repo). It never renders public
articles; Astro does. Everything to build is in docs/wordpress-v1-requirements.md ("the spec").
Read it before any task, and quote spec sections (§) in commits and summaries.

## Stack and rules

- Local: @wordpress/env (`npx wp-env start`), WP at http://localhost:8888, WP-CLI via
  `npx wp-env run cli wp …`. Node 22.
- Plugins (all free): WPGraphQL, WPGraphQL for ACF, Secure Custom Fields (never with ACF),
  Rank Math SEO, Two Factor. Our code lives in ONE custom plugin: wp-content/plugins/ghoroa-core.
- Register post types, taxonomies, fields (SCF, in PHP via acf_add_local_field_group), blocks,
  GraphQL fields and options in code — never only in the database.
- Custom blocks: ACF Blocks via SCF (block.json + PHP render_template). Output must match spec §5
  markup exactly: no wrapper divs, no wp-block-\* classes, no inline styles.
- Never hard-code domains or the /shop prefix: use GN_PUBLIC_URL and GN_BUILD_HOOK_URL (spec §3.1a).
- Content is Bengali (চলিত, আপনি). Health copy never promises a cure ("সাহায্য করতে পারে").
- Free tools first; ask before adding any paid plugin or service.
- I commit and push myself; don't commit unless asked. End each task by listing changed files.

## References

- docs/seed/sample-content.json — seed data (spec §11)
- docs/reference/thankuni-patar-upokarita.html — the article body the blocks must reproduce
- design-system/ — tokens.css + ghoroa.css (load as block-editor styles so the editor looks like the site)
