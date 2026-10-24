"use client";

import { useEffect, useRef, type MutableRefObject } from "react";
import { globeMarkers, globeRest } from "@/content/home";
import { loadLandPoints, smoothstep } from "./geo";
import type { GlobeProgress } from "./Globe";

type Props = { progress: MutableRefObject<GlobeProgress>; active: boolean; onReady?: () => void };

const DEG = Math.PI / 180;
const DELHI = globeMarkers[0];
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

/**
 * Mobile globe: orthographic projection drawn on a 2D canvas (no WebGL). Same props and dive behaviour
 * as the R3F globe, with the rim glow done in CSS.
 */
export default function GlobeFlat({ progress, active, onReady }: Props) {
  const canvas = useRef<HTMLCanvasElement>(null);
  const glow = useRef<HTMLDivElement>(null);
  const pts = useRef<Float32Array | null>(null);
  const activeRef = useRef(active);
  activeRef.current = active;

  useEffect(() => {
    let raf = 0;
    let alive = true;
    const c = canvas.current!;
    const g = c.getContext("2d")!;

    loadLandPoints().then((raw) => {
      if (!alive) return;
      // Pre-convert to radians once.
      const f = new Float32Array(raw.length);
      for (let i = 0; i < raw.length; i++) f[i] = (raw[i] / 100) * DEG;
      pts.current = f;
      raf = requestAnimationFrame(draw);
    });

    let readyFired = false;
    const draw = (t: number) => {
      raf = requestAnimationFrame(draw);
      if (!activeRef.current || !pts.current) return;
      const dpr = Math.min(2, window.devicePixelRatio || 1);
      const w = c.clientWidth;
      const h = c.clientHeight;
      if (c.width !== Math.round(w * dpr)) {
        c.width = Math.round(w * dpr);
        c.height = Math.round(h * dpr);
      }
      g.setTransform(dpr, 0, 0, dpr, 0, 0);
      g.clearRect(0, 0, w, h);

      const { intro, dive } = progress.current;
      const swing = smoothstep(0, 0.42, dive);
      const plunge = smoothstep(0.38, 0.78, dive);
      const lat0 = lerp(globeRest.lat, DELHI.lat, swing) * DEG;
      const lng0 = (lerp(globeRest.lng, DELHI.lng, swing) + Math.sin(t / 8000) * 4 * (1 - swing)) * DEG;
      const R = Math.min(w, h) * 0.56 * lerp(0.9, 1, intro) * lerp(1, 1.9, swing) * lerp(1, 6, plunge);
      const cx = w / 2;
      const cy = lerp(h * 0.3, h / 2, swing);
      const alpha = intro * (1 - smoothstep(0.42, 0.6, dive));
      const sin0 = Math.sin(lat0);
      const cos0 = Math.cos(lat0);

      if (glow.current) {
        glow.current.style.transform = `translate(${cx - R}px, ${cy - R}px)`;
        glow.current.style.width = glow.current.style.height = `${R * 2}px`;
        glow.current.style.opacity = String(intro);
      }

      g.fillStyle = `rgba(217,222,234,${alpha})`;
      const p = pts.current;
      const size = Math.max(1.2, R / 260);
      for (let i = 0; i < p.length; i += 2) {
        const lat = p[i];
        const dl = p[i + 1] - lng0;
        const cosLat = Math.cos(lat);
        const cosc = sin0 * Math.sin(lat) + cos0 * cosLat * Math.cos(dl);
        if (cosc < 0.05) continue;
        const x = cx + R * cosLat * Math.sin(dl);
        const y = cy - R * (cos0 * Math.sin(lat) - sin0 * cosLat * Math.cos(dl));
        if (x < -4 || x > w + 4 || y < -4 || y > h + 4) continue;
        g.fillRect(x, y, size, size);
      }

      // Markers
      const mAlpha = intro * (1 - smoothstep(0.04, 0.22, dive));
      if (mAlpha > 0.01) {
        g.font = "500 9px ui-monospace, monospace";
        for (const m of globeMarkers) {
          const lat = m.lat * DEG;
          const dl = m.lng * DEG - lng0;
          const cosc = sin0 * Math.sin(lat) + cos0 * Math.cos(lat) * Math.cos(dl);
          if (cosc < 0.2) continue;
          const x = cx + R * Math.cos(lat) * Math.sin(dl);
          const y = cy - R * (cos0 * Math.sin(lat) - sin0 * Math.cos(lat) * Math.cos(dl));
          const hub = "hub" in m;
          g.globalAlpha = mAlpha;
          g.fillStyle = hub ? "#ff5a1f" : "#ffffff";
          g.beginPath();
          g.arc(x, y, hub ? 4 : 2.6, 0, Math.PI * 2);
          g.fill();
          if (hub) {
            const k = (t % 1600) / 1600;
            g.strokeStyle = `rgba(255,90,31,${(1 - k) * 0.8})`;
            g.beginPath();
            g.arc(x, y, 4 + k * 12, 0, Math.PI * 2);
            g.stroke();
          }
          g.fillStyle = hub ? "#ff5a1f" : "rgba(243,242,238,0.85)";
          g.fillText(m.label.toUpperCase(), x + (m.side === "left" ? -8 - g.measureText(m.label.toUpperCase()).width : 8), y - 6);
          g.globalAlpha = 1;
        }
      }

      if (!readyFired) {
        readyFired = true;
        onReady?.();
      }
    };

    return () => {
      alive = false;
      cancelAnimationFrame(raf);
    };
  }, [progress, onReady]);

  return (
    <div className="absolute inset-0 overflow-hidden" aria-hidden>
      <div
        ref={glow}
        className="absolute left-0 top-0 rounded-full opacity-0"
        style={{
          background: "radial-gradient(circle at 50% 50%, #05070d 66%, transparent 71%)",
          boxShadow: "-18px -22px 50px -6px rgba(255,90,36,0.75), 20px 26px 60px -8px rgba(30,107,255,0.8), inset 10px 14px 30px -10px rgba(30,107,255,0.6), inset -8px -10px 26px -10px rgba(255,90,36,0.7)",
        }}
      />
      <canvas ref={canvas} className="absolute inset-0 h-full w-full" />
    </div>
  );
}
