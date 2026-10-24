import type { Config } from "tailwindcss";

// All colours resolve to CSS variables in app/globals.css — swap the theme there.
const token = (name: string) => `rgb(var(--${name}) / <alpha-value>)`;

const config: Config = {
  content: ["./app/**/*.{ts,tsx}", "./components/**/*.{ts,tsx}", "./content/**/*.ts"],
  theme: {
    extend: {
      colors: {
        ink: token("ink"),
        paper: token("paper"),
        steel: token("steel"),
        brand: token("brand"),
        ocean: { DEFAULT: token("ocean"), bright: token("ocean-bright") },
        rule: token("rule"),
        container: {
          orange: token("c-orange"),
          navy: token("c-navy"),
          teal: token("c-teal"),
          red: token("c-red"),
        },
      },
      fontFamily: {
        display: ["var(--font-display)", "system-ui", "sans-serif"],
        sans: ["var(--font-body)", "system-ui", "sans-serif"],
        mono: ["var(--font-mono)", "ui-monospace", "monospace"],
      },
      fontSize: {
        eyebrow: ["11px", { lineHeight: "1.2", letterSpacing: "0.12em" }],
        "display-xl": ["clamp(3.25rem, 9.2vw, 9.5rem)", { lineHeight: "0.9", letterSpacing: "-0.02em" }],
        "display-lg": ["clamp(2.75rem, 7vw, 7rem)", { lineHeight: "0.9", letterSpacing: "-0.02em" }],
        "display-md": ["clamp(2rem, 4.6vw, 4.5rem)", { lineHeight: "0.92", letterSpacing: "-0.015em" }],
      },
      spacing: { gutter: "var(--gutter)" },
      transitionTimingFunction: { expo: "cubic-bezier(0.16, 1, 0.3, 1)" },
    },
  },
  plugins: [],
};

export default config;
