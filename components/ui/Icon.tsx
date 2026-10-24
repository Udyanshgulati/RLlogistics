import { cn } from "@/lib/cn";

/** 24px line icons, 1.5px stroke, currentColor. Decorative unless a label is passed. */
const paths = {
  warehouse: "M3 10.5 12 5l9 5.5V20H3v-9.5ZM7 20v-6h10v6M7 17h10",
  truck: "M2 6h11v10H2zM13 9h4.5L21 12.5V16h-8M5.5 19a1.8 1.8 0 1 0 0-.01M16.5 19a1.8 1.8 0 1 0 0-.01",
  plane: "M10.5 13.5 3 11l1.5-1.5 7 1L16 6c1-1 2.6-1.4 3-1s0 2-1 3l-4.5 4.5 1 7L13 21l-2.5-7.5M7 17l-2 2",
  ship: "M3 15h18l-2.5 4.5h-13L3 15ZM6 15V9h12v6M9 9V6h6v3M12 3v3",
  customs: "M5 3h10l4 4v14H5zM15 3v4h4M8.5 12.5l2 2 4-4M8 17.5h8",
  parcel: "M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5v-9ZM3.5 7.5 12 12l8.5-4.5M12 12v9M7.8 5.3l8.4 4.5",
  crane: "M4 21h9M6 21V4h2v17M8 4h12l-3 3H8M17 7v5M15.5 12h3v2.5h-3zM6 8l2-2",
  gps: "M12 21s-6.5-6.2-6.5-11a6.5 6.5 0 1 1 13 0c0 4.8-6.5 11-6.5 11ZM12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z",
  user: "M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM4.5 20.5c.8-3.8 3.9-6 7.5-6s6.7 2.2 7.5 6M17.5 4.5l1.5 1.5 2.5-2.5",
  tower: "M12 9a2 2 0 1 0 0-.01M12 11v10M8 21h8M7.8 4.8a6 6 0 0 0 0 8.4M16.2 4.8a6 6 0 0 1 0 8.4M5 2a10 10 0 0 0 0 14M19 2a10 10 0 0 1 0 14",
  pod: "M4 5h16v14H4zM8 3v4M16 3v4M4 10h16M9 14.5l2 2 4-4",
  thermo: "M10 14.5V5a2 2 0 1 1 4 0v9.5a4 4 0 1 1-4 0ZM12 17.5V9M17 5h3M17 8h2M17 11h3",
  contact: "M4 5h16v11H9l-5 4V5ZM8 9.5h8M8 12.5h5",
  eye: "M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12ZM12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z",
  shield: "M12 3 4.5 6v5.5c0 4.6 3.2 8.3 7.5 9.5 4.3-1.2 7.5-4.9 7.5-9.5V6L12 3ZM8.5 12l2.5 2.5 4.5-4.5",
  rupee: "M7 4h10M7 8.5h10M7 4h3.5a4.5 4.5 0 0 1 0 9H7l7.5 7",
  fmcg: "M7 3h10l-1 4H8L7 3ZM8 7h8v13a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V7ZM8 12h8",
  pharma: "M10.5 4.5a4.95 4.95 0 0 1 7 7l-6 6a4.95 4.95 0 0 1-7-7l6-6ZM7.5 7.5l9 9M15 3.5 20.5 9",
  cart: "M3 4h2.5l2.2 10.5h10.6L20.5 7H6.6M9.5 19.5a1.3 1.3 0 1 0 0-.01M17 19.5a1.3 1.3 0 1 0 0-.01",
  car: "M3.5 16.5V12l2-5h13l2 5v4.5h-17ZM3.5 12h17M7 19.5v-3M17 19.5v-3M7 14.2h.01M17 14.2h.01",
  chip: "M7 7h10v10H7zM10 10h4v4h-4zM9 3v4M15 3v4M9 17v4M15 17v4M3 9h4M3 15h4M17 9h4M17 15h4",
  textile: "M4 7c2.7-2.7 5.3 2.7 8 0s5.3 2.7 8 0M4 12c2.7-2.7 5.3 2.7 8 0s5.3 2.7 8 0M4 17c2.7-2.7 5.3 2.7 8 0s5.3 2.7 8 0",
  globe: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3Z",
  bolt: "M13 2 4.5 13.5H11L10 22l8.5-11.5H12L13 2Z",
  clock: "M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18ZM12 7v5l3.5 2",
  arrowUp: "M12 20V5M6 11l6-6 6 6",
} as const;

export type IconName = keyof typeof paths;

export function Icon({ name, className, label }: { name: IconName; className?: string; label?: string }) {
  return (
    <svg
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      strokeWidth={1.5}
      strokeLinecap="round"
      strokeLinejoin="round"
      className={cn("h-6 w-6", className)}
      role={label ? "img" : undefined}
      aria-label={label}
      aria-hidden={label ? undefined : true}
    >
      <path d={paths[name]} />
    </svg>
  );
}
