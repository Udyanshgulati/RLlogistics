"use client";

import { useEffect, useRef, useState } from "react";
import { globeMarkers, globeRest } from "@/content/home";

const DEG = Math.PI / 180;
const MARKERS = globeMarkers.filter((m) => m.id === "delhi" || m.id === "mumbai" || m.id === "chennai");

/** Base64 Int16 (lat*100, lng*100) pairs, every other point kept (~8k dots). Inlined so phones don't pull in three.js. */
async function loadPoints() {
  const { b64 } = (await fetch("/globe/land-points.json").then((r) => r.json())) as { b64: string };
  const bin = atob(b64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  const raw = new Int16Array(bytes.buffer);
  const out = new Float32Array(Math.ceil(raw.length / 4) * 2);
  let j = 0;
  for (let i = 0; i + 1 < raw.length; i += 4) {
    out[j++] = (raw[i] / 100) * DEG;
    out[j++] = (raw[i + 1] / 100) * DEG;
  }
  return out.subarray(0, j);
}

/**
 * Phone hero globe: orthographic dotted earth on a 2D canvas (no WebGL context to lose), centred on India,
 * round dots, orange/blue rim glow, pulsing Delhi pin, three labels, slow idle sway.
 * A pre-rendered image of the same globe sits underneath and stays visible until the canvas has drawn,
 * so the hero always shows a globe, even if the script or the data fails.
 */
export function MobileGlobe({ className }: { className?: string }) {
  const wrap = useRef<HTMLDivElement>(null);
  const canvas = useRef<HTMLCanvasElement>(null);
  const glow = useRef<HTMLDivElement>(null);
  const [drawn, setDrawn] = useState(false);

  useEffect(() => {
    const el = wrap.current!;
    const c = canvas.current!;
    const g = c.getContext("2d");
    if (!g) return;
    let raf = 0;
    let alive = true;
    let visible = true;
    let pts: Float32Array | null = null;
    const still = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

    // Round dot sprite, drawn once.
    const dot = document.createElement("canvas");
    const dotPx = 6;
    dot.width = dot.height = dotPx;
    const dg = dot.getContext("2d")!;
    dg.fillStyle = "#d9deea";
    dg.beginPath();
    dg.arc(dotPx / 2, dotPx / 2, dotPx / 2, 0, Math.PI * 2);
    dg.fill();

    let w = 0, h = 0, dpr = 1;
    const resize = () => {
      dpr = Math.min(1.5, window.devicePixelRatio || 1);
      w = el.clientWidth;
      h = el.clientHeight;
      c.width = Math.round(w * dpr);
      c.height = Math.round(h * dpr);
      if (glow.current) glow.current.style.width = glow.current.style.height = `${Math.min(w, h) * 0.92}px`;
    };

    const draw = (t: number) => {
      if (!alive) return;
      if (!still && visible) raf = requestAnimationFrame(draw);
      if (!pts || !w || !h) return;
      g.setTransform(dpr, 0, 0, dpr, 0, 0);
      g.clearRect(0, 0, w, h);
      const R = Math.min(w, h) * 0.46;
      const cx = w / 2;
      const cy = h / 2;
      const lat0 = globeRest.lat * DEG;
      const lng0 = (globeRest.lng + (still ? 0 : Math.sin(t / 9000) * 14)) * DEG;
      const sin0 = Math.sin(lat0);
      const cos0 = Math.cos(lat0);
      const size = Math.max(1.6, R / 150);

      for (let i = 0; i < pts.length; i += 2) {
        const lat = pts[i];
        const dl = pts[i + 1] - lng0;
        const cosLat = Math.cos(lat);
        const cosc = sin0 * Math.sin(lat) + cos0 * cosLat * Math.cos(dl);
        if (cosc < 0.04) continue;
        const x = cx + R * cosLat * Math.sin(dl);
        const y = cy - R * (cos0 * Math.sin(lat) - sin0 * cosLat * Math.cos(dl));
        // Dots fade toward the limb for depth.
        g.globalAlpha = 0.35 + 0.65 * cosc;
        g.drawImage(dot, x - size / 2, y - size / 2, size, size);
      }
      g.globalAlpha = 1;

      g.font = "500 11px ui-monospace, SFMono-Regular, Menlo, monospace";
      g.textBaseline = "middle";
      for (const m of MARKERS) {
        const lat = m.lat * DEG;
        const dl = m.lng * DEG - lng0;
        const x = cx + R * Math.cos(lat) * Math.sin(dl);
        const y = cy - R * (cos0 * Math.sin(lat) - sin0 * Math.cos(lat) * Math.cos(dl));
        const hub = m.id === "delhi";
        if (hub) {
          const k = (t % 1800) / 1800;
          g.strokeStyle = `rgba(242,169,0,${(1 - k) * 0.85})`;
          g.lineWidth = 1.5;
          g.beginPath();
          g.arc(x, y, 5 + k * 14, 0, Math.PI * 2);
          g.stroke();
        }
        g.fillStyle = hub ? "#f2a900" : "#ffffff";
        g.beginPath();
        g.arc(x, y, hub ? 4.5 : 3, 0, Math.PI * 2);
        g.fill();
        const label = m.label.toUpperCase();
        const lw = g.measureText(label).width;
        g.fillStyle = hub ? "#f2a900" : "rgba(244,247,251,0.9)";
        g.fillText(label, m.side === "left" ? x - 10 - lw : x + 10, y);
      }
      if (!drawnOnce) {
        drawnOnce = true;
        setDrawn(true);
      }
    };
    let drawnOnce = false;

    const io = new IntersectionObserver(([e]) => {
      const was = visible;
      visible = e.isIntersecting;
      if (visible && !was && !still) raf = requestAnimationFrame(draw);
    });
    io.observe(el);
    const ro = new ResizeObserver(() => {
      resize();
      if (still) draw(0);
    });
    ro.observe(el);
    resize();

    loadPoints()
      .then((p) => {
        if (!alive) return;
        pts = p;
        raf = requestAnimationFrame(draw);
      })
      .catch(() => {
        /* the pre-rendered image underneath stays visible */
      });

    return () => {
      alive = false;
      cancelAnimationFrame(raf);
      io.disconnect();
      ro.disconnect();
    };
  }, []);

  return (
    <div ref={wrap} data-m-globe className={className} aria-hidden>
      {/* Rim glow: orange from the top-left, blue from the bottom-right. */}
      <div
        ref={glow}
        className="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2 rounded-full"
        style={{
          background: "radial-gradient(circle at 50% 50%, #061331 66%, transparent 71%)",
          boxShadow:
            "-16px -20px 46px -6px rgba(56,189,248,0.7), 18px 24px 56px -8px rgba(30,107,255,0.8), inset 10px 14px 30px -10px rgba(30,107,255,0.6), inset -8px -10px 26px -10px rgba(56,189,248,0.6)",
        }}
      />
      {/* eslint-disable-next-line @next/next/no-img-element */}
      <img
        src="/assets/globe-mobile.webp"
        alt=""
        className={`absolute inset-0 h-full w-full object-contain transition-opacity duration-500 ${drawn ? "opacity-0" : "opacity-100"}`}
      />
      <canvas ref={canvas} className="absolute inset-0 h-full w-full" />
    </div>
  );
}
