import { ImageResponse } from "next/og";

export const dynamic = "force-static";
export const alt = "RL Logistics & Warehousing — Every mile. Every milestone.";
export const size = { width: 1200, height: 630 };
export const contentType = "image/png";

export default function OpengraphImage() {
  return new ImageResponse(
    (
      <div style={{ width: "100%", height: "100%", display: "flex", flexDirection: "column", justifyContent: "space-between", background: "#0A0D14", color: "#F3F2EE", padding: 72, fontFamily: "sans-serif" }}>
        <div style={{ display: "flex", alignItems: "center", gap: 18 }}>
          <div style={{ display: "flex", background: "#fff", color: "#0A0D14", padding: "10px 18px", borderRadius: 6, fontSize: 30, fontWeight: 800 }}>RL | LOGISTICS AND WAREHOUSING</div>
        </div>
        <div style={{ display: "flex", flexDirection: "column", fontSize: 104, fontWeight: 900, lineHeight: 0.92, letterSpacing: -3 }}>
          <span>EVERY MILE.</span>
          <span>EVERY MILESTONE.</span>
        </div>
        <div style={{ display: "flex", justifyContent: "space-between", fontSize: 24, color: "#8A8F98" }}>
          <span>Warehousing · Road · Freight forwarding · Customs</span>
          <span style={{ color: "#FF5A1F" }}>Delhi · Since 2018</span>
        </div>
      </div>
    ),
    size,
  );
}
