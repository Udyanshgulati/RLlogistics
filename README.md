# RL Logistics & Warehousing — website

Scroll-driven cinematic marketing site for **RL Logistics and Warehousing Pvt. Ltd.** (New Delhi).

**Stack:** Next.js 15 (App Router) · TypeScript · Tailwind CSS 3 · GSAP 3.15 (ScrollTrigger, SplitText, ScrambleText, CustomEase) · Lenis · React Three Fiber + drei (globe only).

## Run

```bash
npm install
npm run dev        # http://localhost:3000
npm run build      # production build (also type-checks + lints)
```

Routes: `/` (the scroll journey), `/about`, `/services`, `/services/[slug]` (6 services), `/industries`, `/network`, `/careers`, `/contact`, a 404 page, and `/playground` (the five text effects on their own; noindex).

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

### Asset slots

All vehicles, ship, plane, clouds and water are drawn in code (SVG or canvas), so no image files are needed. When real artwork arrives, these are the planned file names to swap in under `/public/assets/`: `yard/reach-stacker-body.png`, `yard/reach-stacker-boom.png`, `yard/container-white.png`, `truck-side.png`, `truck-top.png`, `ship-top.png`, `plane-top.png`, `clouds-1.png`…`clouds-3.png`, `ocean.mp4`, `hero-road.jpg`.

## Accessibility & motion

- `prefers-reduced-motion`: pins and scrubbing are turned off, and each scene shows its final state as a static section.
- SEO: per-page metadata, `opengraph-image`, `sitemap.xml`, `robots.txt`, and JSON-LD `Organization` + `LocalBusiness` with the registered address.
- Copy marked `// DUMMY` (numbers, testimonials, openings, rates) is placeholder content to replace.
- SplitText keeps an accessible label for the full sentence. Decorative layers use `aria-hidden`.
