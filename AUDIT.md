# Visual audit — homepage journey

Harness: `npm run snap` (scripts/snap.ts) against the production build on :3000 with `?test=1` (Lenis off), 21 frames per viewport in 5% steps, settle 1200 ms per step (the scene timelines use `scrub: 1`, so 300 ms would capture them mid catch-up). Snaps in `snaps/<viewport>/NN.png`, sheets in `snaps/contact-<viewport>.png`, motion in `snaps/scroll.webm`.

Run: 2026-10-25, before any fixes. Reference: `reference-frames/01–19`.

## Scene by scene

| Scene | Reference does | Ours does | Visual bugs seen (snap) | Fix |
|---|---|---|---|---|
| **Hero** | Dark hero, vertical nav, wide caps headline bottom-left, big dotted globe cropped right with orange/blue rim glow, labelled pins (ref 01). | All of that is present and close to the reference. | Stars are square `PointsMaterial` points (tiny, but square). `THREE.Clock` deprecation warning in the console. 1.0–1.1 s long task at load (WebGL init plus shader compile; inflated by SwiftShader here). | Round star sprite via shader (`discard` outside radius). Silence or upgrade for the Clock warning. Defer non-hero work further (see perf). |
| **Dive** | Globe fills the screen → blue → white haze, continuous (ref 02). | Works, but the haze holds on empty paper and Scene 2's 14vh top padding adds more blank space. | **Dead frame:** 1440/01, 1280/01 (5%) is an almost empty paper screen. 390/01 shows a grey-blue haze with a sliver of the next card. `blur(14px)` runs on the full-screen globe wrapper (rectangular fade at the viewport edges mid-dive; needs a finer-step snap to confirm). | Shorten the hold; start Scene 2's card and heading inside the haze (overlap). Replace the wrapper blur with a dot-opacity fade in the shader. Add grain to the gradient against banding. |
| **We Move Freight** | Card left, giant 3-line heading, grey→ink paragraph, counters with blur settle (ref 03–04). | Present. The heading is sticky on desktop, the stats scroll on the right. | 1440/02: only 2 of 3 counters fit; the "8+" stat is never on screen together with the heading. 390/02: the heading isn't visible in the frame at all (copy only). The reach-stacker does not overlap the exit (ref 04 shows it entering while the heading is still on screen). | Tighten the stats column; let the yard's stacker enter while Scene 2 exits (overlap the start of the yard pin). Mobile: heading before the copy. |
| **Yard** | Photo-real reach-stacker, boom, spreader; container lifted, zoom, lowered onto a truck; truck on the panel edge (ref 05–08). | Kinematic rig works: drive in → lock → lift + zoom → truck reverses → lower → release → dock. | Flat clip-art look versus the photo-real reference. **Truck proportions wrong:** single drive axle, trailer bogie spacing too wide. 1440/05: the truck drives *in front of* the stack (no depth, reads as overlapping boxes). No contact shadows. 390/03: on mobile the stacker is mostly off-screen and the shot is empty. | Step 3.5: realistic truck (tandem drive, tri-axle bogie, dual wheels, fuel tank, mudguards, shading, contact shadows) for your approval first. Stack behind a mid layer with atmospheric fade. Mobile camera framing. |
| **Services** | Dark panel rises, truck rides its edge, ghost heading, icons stagger, faint "OUR SERVICES" (ref 08–09). | Present (1440/06). | Watermark was invisible until the `.text-outline` fix (already fixed in source, not in this build). 390/06: the service row is cut off at the right, with no visual hint that it scrolls. | Verify the watermark in the next run; add an edge fade / "swipe" hint on mobile. |
| **Services → Road** | The dark panel itself bends into the road: curved top edge (ref 10), then a vertical strip with a rounded corner (ref 11). | Panel goes full black, then the road section starts as a giant black curve. | **Dead frame:** 1440/07 and 1280/07 (35%) are 100% black. 390/07: black frame with a stray "03 — HOW WE DELIVER" eyebrow floating on it. 1440/08: lane dashes render as crude giant white bars at 9× scale. | Merge: the panel's top edge morphs into the road path (shared shape), with no black hold. Hide the eyebrow until the road settles. Scale the lane dash pattern inversely with the camera. |
| **Road** | Vertical road centre, top-down truck driving down, features alternate with connector lines (ref 11–12). | Present (1440/09–10). | 390/08: heading overflows (`RELIABVUFNA…` clipped mid-scramble; ghost lines are `nowrap`). **390/10: milestone texts overlap each other.** The top-down truck is very small at 1440. | Mobile: smaller heading with wrapping allowed, milestone spacing derived from the text height. Scale the truck with the road width. |
| **Road → Ocean** | Road tints to water, then the ship (ref 13). | The tint never shows. | **Hard horizontal cut** between the white road section and the blue ocean: 1440/11, 1280/11. Root cause: `translate-y-full` (a CSS transform) on the tint, and GSAP then adds `yPercent` on top, so it stays off-screen. Same class of bug as the plane earlier. | Remove the CSS transform; let GSAP own the start state. |
| **Ocean** | Real drone footage: ship close-up → zoom out, ghost heading, features (ref 13–16). | Present (1440/12–13). | **Wake is blurry/blocky** (600 px texture shown at ~1840 px, crop of 1440/12). Water texture 512 px tile scaled 2.4× (pixelated). Permanent `will-change` on the ship container (rasterised at one scale). 390/11–12: the ship is too big on mobile, and the features sit on top of it. | Wake/water as a vector or shader (or render at max size and only scale down). Remove the permanent `will-change`. Mobile: smaller ship, features above/below it. |
| **Clouds / Plane** | Clouds roll in, the plane passes, clears to white, tail stays at the right (ref 17–19). | Clouds then plane then paper. | **Boxes:** the cloud banks show straight rectangular edges (crop of 1440/14). Cause: radial puffs clipped at the canvas border, and a 900 px canvas upscaled to 120vw × 1.6. **Dead frames:** 1440/14–15 and 1280/14–15 (≈10% of the page) are clouds only. The plane is never visible in any 5% frame (it passes in under 5% of scroll). The plane tail does not stay as decoration (it ends off-screen). | Clouds: pad the canvas so no puff touches an edge, render at display resolution, soft alpha falloff to 0 at the borders. Shorten the cloud hold; give the plane ~15% of the scene; park the tail at the right edge like ref 19. |
| **Testimonials** | Heading with grey last line, avatar, name, long quote scrubbing grey→ink, logo strip (ref 19). | Present (1440/16–19). | 1440/16: faint cloud residue over the heading. Ink and "active" contrast fine. | Clouds fully cleared before the heading reveals. |
| **CTA / Footer** | Ghost heading, magnetic button, dark footer with wordmark (not in ref). | Present on mobile (390/19). | **1280/19: the CTA heading is missing** (the scroll-triggered ghost heading never fires; its trigger was measured before the pins above it were added). | Create the CTA trigger after the pinned scenes (`refreshPriority`) or scrub it. |

## Step 1 checklist — causes of "boxes around pixels"

| Cause | Applies? | Where |
|---|---|---|
| Fake transparency in image assets | **No** | `/public/assets` only has `hero-road.jpg` (opaque photo, intentionally). No transparent PNGs in use. |
| Raster scaled beyond native resolution | **Yes** | Cloud canvas (900 px → up to ~2700 px), wake (600 → ~1840 px), water tile (512 → ~2400 px). |
| Permanent `will-change: transform` | **Yes** | Ship camera, road truck, testimonial list, marquee tracks, ghost layers, counter strips, globe labels. |
| `box-shadow` on transparent images | No | The ship uses `drop-shadow` (correct). Logo plate is an opaque white box by design. |
| `filter: blur()` on wrappers | **Yes** | Hero dive blurs the full-screen globe wrapper. Counter blurs its visual wrapper (inside an unclipped parent; low risk). |
| Backgrounds on split-text spans | No | No backgrounds on SplitText / ghost layers. |
| Canvas / WebGL resolution | Partly | R3F `dpr [1, 1.75]` (should be `[1, 2]`). Globe land dots are round instanced circles ✓, stars are square points ✗. 2D mobile globe sizes by DPR ✓. |
| Seams between sections / clip-path | **Yes** | Road → ocean hard cut (tint bug). Panel clip-path uses % values (sub-pixel edge at some heights). |
| Gradient banding | Likely | Atmosphere and ocean gradients have no dither. |
| SVG rendering (`crispEdges`, `pixelated`) | No | None found. |
| Leftovers (markers, outlines, TODO, broken images) | No | None found (`placeholder=` hits are form placeholders). |
| Fonts swap jump | Minor | `next/font` with `display: swap` ✓, `ScrollTrigger.refresh()` after `document.fonts.ready` ✓; `adjustFontFallback` is on by default. |

## Smoothness

- **Dead scroll:** ~25% of the page shows no meaningful motion: 5% (paper hold), 35% (black hold), 70–75% (clouds only).
- **Missing overlaps:** Services → Road (no shared shape), Road → Ocean (cut), Scene 2 → Yard (no overlap).
- **Engine:** one Lenis instance, `gsap.ticker`, `lagSmoothing(0)`, `scrub: 1` on all scene timelines ✓. No CSS `scroll-behavior: smooth` ✓.
- **Long tasks:** 9 over 50 ms per desktop run (max 1.07–1.12 s at load, others 50–214 ms during scroll), 5 on mobile (max 426 ms). Measured with SwiftShader (no GPU), so absolute values are pessimistic.
- **Console:** 0 page errors. 1 warning (`THREE.Clock` deprecated, from R3F internals).
- **Overflow-x:** none at any viewport ✓.

## Realism (Step 3.5) — the biggest gap

The reference uses photo-real 3D renders and real drone footage for every vehicle. Ours are flat SVG (single fills, no shading, no contact shadows, simplified proportions). The SVG can be pushed a long way (shading, AO shadows, correct axles, dual wheels, parallax layers), but it will not become photo-real. For a reference-level match the vehicles should be rendered assets. The asset list and prompts come in Step 3.5, after you approve the before/after of the truck.
