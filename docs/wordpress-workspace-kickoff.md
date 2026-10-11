# Starting the WordPress workspace with Claude Code

How to set up the separate WordPress repo and drive it with Claude Code. Everything to build is in [wordpress-v1-requirements.md](wordpress-v1-requirements.md) (the "spec" below).

## 1. One-time setup on your machine
1. **Docker Desktop** (free for personal use) with the WSL 2 backend. `@wordpress/env` runs WordPress in Docker.
2. **Node 22**, which you already have through nvm: `nvm use 22.19.0`.
3. A new **GitHub repo**, e.g. `ghoroa-niramoy-wordpress`, cloned next to this one:
   ```
   F:\personal\ghoroa-niramoy-blog-frontend   (this repo: Astro)
   F:\personal\ghoroa-niramoy-wordpress        (new: WordPress)
   ```
   Set the repo-local git identity as you did here.

## 2. Copy these files into the new repo

| From this repo | To the WordPress repo | Why |
|---|---|---|
| `docs/wordpress-v1-requirements.md` | `docs/wordpress-v1-requirements.md` | The spec |
| `docs/seed/sample-content.json` | `docs/seed/sample-content.json` | Seed data (§11) |
| `src/data/articles/thankuni-patar-upokarita.html` | `docs/reference/thankuni-patar-upokarita.html` | Reference article markup to match (§5) |
| `design-system/` (whole folder) | `design-system/` | CSS for the block-editor preview, and later the `/shop` theme |

**The working copy of the spec now lives in the WordPress repo.** If the GraphQL contract (§4) or the block markup (§5) changes there, tell the Astro-side session, so `src/lib/types.ts` and `docs/astro-todo.md` here stay in step.

## 3. `CLAUDE.md` for the WordPress repo (paste as-is)

```markdown
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
  markup exactly: no wrapper divs, no wp-block-* classes, no inline styles.
- Never hard-code domains or the /shop prefix: use GN_PUBLIC_URL and GN_BUILD_HOOK_URL (spec §3.1a).
- Content is Bengali (চলিত, আপনি). Health copy never promises a cure ("সাহায্য করতে পারে").
- Free tools first; ask before adding any paid plugin or service.
- I commit and push myself; don't commit unless asked. End each task by listing changed files.

## References
- docs/seed/sample-content.json — seed data (spec §11)
- docs/reference/thankuni-patar-upokarita.html — the article body the blocks must reproduce
- design-system/ — tokens.css + ghoroa.css (load as block-editor styles so the editor looks like the site)
```

## 4. First prompt in Claude Code

Open the new folder in VS Code, start Claude Code, and send:

> Read CLAUDE.md and docs/wordpress-v1-requirements.md. Set up the local environment with @wordpress/env (.wp-env.json with the plugins listed in CLAUDE.md, and our ghoroa-core plugin mapped in). Then do milestone M1 from docs/wordpress-workspace-kickoff.md section 5. Stop after M1 and show me how to verify it.

Then run one milestone per prompt: "Do M2", and so on. Review and push between milestones.

## 5. Milestones (local; production last)

| # | Milestone | Spec | Done when |
|---|---|---|---|
| **M1** | wp-env and plugins; `ghoroa-core` skeleton; the four categories (locked), `video` post type, every SCF field group and the homepage options page; `GN_*` constants | §3.1a, §4.1–4.8 | Admin shows all fields; the SCF + WPGraphQL **day-one check** passes for every field type |
| **M2** | Seed importer `wp gn seed docs/seed/sample-content.json` (idempotent by slug) | §11 | 24 posts, 6 videos, 7 tags, 3 authors and the homepage options exist |
| **M3** | GraphQL contract: `readingMinutes`, `gnSeo`, YouTube thumbnail fallback; image crops and WebP | §4.1, §4.9, §9 | Every query in §4.9 returns the expected shape |
| **M4** | The 11 editor blocks plus the block allowlist; the editor loads `tokens.css` + `ghoroa.css`; the reference article rebuilt with blocks | §5 | GraphQL `content` matches `docs/reference/…html` structurally |
| **M5** | Link rewriting and redirects; publish checks; roles and capabilities | §6, §7, §8 | Each check in §7 verified with one deliberately bad post; an author can't publish a health article |
| **M6** | Rebuild webhook (logs locally); preview link and token | §10 | Publishing logs exactly one debounced hook call |
| **M7** | **Production deployment** on the Hostinger VPS: decide the server panel then, server hardening, Worker routing, R2 backups | §3.1–3.4 | Spec §14 definition of done |

**Cross-check with the Astro side after M3:** run this repo with `WP_GRAPHQL_URL=http://localhost:8888/graphql` once the Astro task "Connect WordPress" (astro-todo §1) is done. That's the real end-to-end test of the contract.
