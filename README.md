# RL Logistics & Warehousing — website

Scroll-driven cinematic marketing site for **RL Logistics and Warehousing Pvt. Ltd.** (New Delhi).

**Stack:** Next.js 15 (App Router) · TypeScript · Tailwind CSS 3 · GSAP 3.15 (ScrollTrigger, SplitText, ScrambleText, CustomEase) · Lenis · React Three Fiber + drei (globe only).

## Run

```bash
npm install
npm run dev        # http://localhost:3000
npm run build      # production build (also type-checks + lints)
```

Routes: `/` (the scroll journey), `/about`, `/services`, `/services/[slug]` (6 services), `/industries`, `/network`, `/insights` (+ article pages), `/contact`, a 404 page, and `/playground` (the five text effects on their own; noindex).

### Homepage scenes

| Scene | Component | What happens |
|---|---|---|
| 1 | `Scene1Hero` | Dotted globe (R3F on desktop, 2D canvas on phones) → dive through the atmosphere |
| 2 | `Scene2About` | "We move freight" with scrub copy and odometer counters |
| 3–4 | `Scene3Yard` | Reach-stacker loads a container onto a truck, then the dark panel rises and the services row appears |
| 5 | `Scene5Road` | S-curve road straightens; a truck drives past the milestones |
| 6 | `Scene6Ocean` | Top-down ship zoom-out, features, then the clouds roll in |
| 7 | `Scene7Trust` | Plane clears the clouds; pinned testimonials; client strip |
| 8 | `Scene8Cta` + `Footer` | Magnetic CTA, footer wordmark reveal |

### Preview export

`./scripts/export-preview.sh` writes a static copy to `out/`, with Next's assets under `/rl/_next`. It is only used for the hosted preview; deploy normally with `npm run build && npm start`, or on Vercel.

## Deploy

The site is a standard Next.js app, so the simplest host is **Vercel**:

1. Push this repo to GitHub (already done) and sign in at vercel.com with GitHub.
2. Choose **Add New → Project**, pick the repo, keep the defaults (framework: Next.js) and click **Deploy**.
3. Under **Settings → Domains**, add `rllogistics.in` and follow the DNS instructions.

Any Node host also works: `npm ci && npm run build && npm start` (port 3000).

### Staging on `rllogistics.thodalogic.com`

**With Coolify (recommended, the server already runs it):** New Resource → Private Repository (GitHub App) → `Udyanshgulati/RLlogistics`, branch `claude/new-session-jlvwbz` → Build Pack **Dockerfile** → Ports Exposes `80` → Domains `https://rllogistics.thodalogic.com` → Deploy. The `Dockerfile` builds the static export (staging URL, noindex) and serves it with nginx; Coolify's proxy handles HTTPS. Every push redeploys if auto-deploy is on.

**Without Coolify (plain nginx):**

The client preview runs as a plain static site on the Thoda Logic server (`200.141.13.226`), next to the other client subdomains.

1. **Cloudflare DNS** (thodalogic.com → DNS → Records → Add record): Type `A`, Name `rllogistics`, IPv4 `200.141.13.226`, Proxy on (orange cloud), TTL Auto.
2. **Server, once:** copy `deploy/nginx-rllogistics.thodalogic.com.conf` into nginx (commands are in the file header), and create `/var/www/rllogistics`.
3. **Deploy (every update):** `SERVER=user@200.141.13.226 ./deploy/deploy.sh`. This builds with `npm run export:subdomain` and rsyncs `out/` to the server.
4. **HTTPS:** with the Cloudflare proxy on, set SSL/TLS mode to *Full* and either run `certbot --nginx -d rllogistics.thodalogic.com` on the server or install a Cloudflare Origin certificate.

The subdomain build sets `NEXT_PUBLIC_SITE_URL=https://rllogistics.thodalogic.com` and `NEXT_PUBLIC_NOINDEX=1`: canonical URLs point at the subdomain, and robots.txt plus a `noindex` meta keep search engines away until the real launch on rllogistics.in. Production builds omit both variables, so they default to `https://rllogistics.in` and indexable.
CI (`.github/workflows/ci.yml`) runs typecheck, lint and build on every push.

## Where things live

| Path | What |
|---|---|
| `app/globals.css` | Design tokens (CSS variables). Change the theme here. |
| `tailwind.config.ts` | Maps the tokens to Tailwind (`bg-ink`, `text-brand`…), display sizes, fonts. |
| `lib/gsap.ts` | Plugin registration, `expo` CustomEase, media-query constants. |
| `lib/lenis.tsx` | Lenis smooth scroll, driven by `gsap.ticker` and synced to ScrollTrigger. |
| `lib/loader.tsx` | Loader state. Components can `track()` a promise, e.g. the globe's first frame. |
| `content/*.ts` | All copy. Numbers marked `// DUMMY` are placeholders. |
| `components/text/` | `RevealHeading`, `GhostHeading`, `ScrubParagraph`, `Counter`, `Marquee`. Each can play by itself (`mode`) or hand a `build*Tl()` timeline to a scene's master timeline (`mode="external"`). |
| `components/globe/` | R3F dotted globe: instanced land dots, fresnel atmosphere, pins, arcs, labels. |
| `components/scenes/` | One component per homepage scene. |
| `components/ui/` | Nav (left vertical list, menu overlay), Loader, Cursor, Button, Logo. |
| `docs/reference-frames.md` | Frame-by-frame notes on the reference recording. |

### Globe land data

`public/globe/land-points.json` holds about 16.8k land points (base64 Int16 lat/lng pairs), sampled from `world-atlas` land-50m. To regenerate it:

```bash
node scripts/build-globe-points.mjs
```

### Nav theme

The header changes colour to match the section underneath it. Any section can set `data-nav-theme="dark" | "light"`.

### Asset overrides

All vehicles, the ship, plane, clouds and water are drawn in code (SVG or canvas), so the site needs no image files. To use real artwork, drop a file with one of these names into `/public/assets/` and rebuild. `lib/assets.ts` checks at build time and the matching scene switches to the file automatically (slots are listed in `lib/asset-slots.ts`).

| File | Replaces | Art box / notes |
|---|---|---|
| `hero-road.jpg` | Scene 2 image card | 2:1 landscape photo |
| `yard/reach-stacker-body.png` | Reach-stacker body | Side view facing right, 480 × 250, ground on the bottom edge, **no wheels** (the coded wheels keep spinning) |
| `yard/reach-stacker-boom.png` | Telescopic boom | 620 × 60, pivot 40 px from the left edge, vertically centred |
| `yard/container-white.png` | The 40 ft container that gets lifted | 410 × 160 side view |
| `truck-side.png` | Side-view truck + trailer | 660 × 280 facing right, **no wheels** |
| `truck-top.png` | Top-down truck on the road | 60 × 200 portrait, cab at the bottom |
| `ship-top.png` | Top-down container ship | 240 × 1000 portrait, bow at the top, transparent background |
| `plane-top.png` | Top-down cargo plane | 1000 × 800, nose pointing right, transparent background |
| `clouds-1.png` … `clouds-3.png` | Cloud banks (Scenes 6 and 7) | Wide transparent PNGs, about 1400 × 700 |
| `ocean.mp4` | Animated water | Looping top-down sea, muted |

## Accessibility & motion

- `prefers-reduced-motion`: pins and scrubbing are turned off, and each scene shows its final state as a static section.
- SEO: per-page metadata, `opengraph-image`, `sitemap.xml`, `robots.txt`, and JSON-LD `Organization` + `LocalBusiness` with the registered address.
- Copy marked `// DUMMY` (some numbers, testimonials) is placeholder content to replace. Contact details, branches, client names, mission/vision, fleet size and transit times come from the client's current site (rllogistics.in).
- Photos in `public/photos` are CC0 (Openverse); sources in `public/photos/CREDITS.json`.
- Visual test harness: `npm run snap` (see `scripts/snap.ts`), audit in `AUDIT.md`.
- SplitText keeps an accessible label for the full sentence. Decorative layers use `aria-hidden`.
