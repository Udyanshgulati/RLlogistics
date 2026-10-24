# RL Logistics & Warehousing — website

Scroll-driven cinematic marketing site for **RL Logistics and Warehousing Pvt. Ltd.** (New Delhi).

**Stack:** Next.js 15 (App Router) · TypeScript · Tailwind CSS 3 · GSAP 3.15 (ScrollTrigger, SplitText, ScrambleText, CustomEase) · Lenis · React Three Fiber + drei (globe only).

## Run

```bash
npm install
npm run dev        # http://localhost:3000
npm run build      # production build (also type-checks + lints)
```

Routes so far: `/` (Scene 1 — hero, globe and dive) and `/playground` (the five text effects on their own).

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

`public/globe/land-points.bin` holds about 16.8k land points (Int16 lat/lng pairs), sampled from `world-atlas` land-50m. To regenerate it:

```bash
node scripts/build-globe-points.mjs
```

### Nav theme

The header changes colour to match the section underneath it. Any section can set `data-nav-theme="dark" | "light"`.

### Image overrides (planned for the illustration scenes)

If any of these files exist in `/public/assets/`, they will replace the SVG illustrations: `yard/reach-stacker-body.png`, `yard/reach-stacker-boom.png`, `yard/container-white.png`, `truck-side.png`, `truck-top.png`, `ship-top.png`, `plane-top.png`, `clouds-1.png`…`clouds-3.png`, `ocean.mp4`, `hero-road.jpg`.

## Accessibility & motion

- `prefers-reduced-motion`: pins and scrubbing are turned off, and text shows in its final state.
- SplitText keeps an accessible label for the full sentence. Decorative layers use `aria-hidden`.
