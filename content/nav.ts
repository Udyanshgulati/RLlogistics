export type NavItem = { label: string; href: string };

export const primaryNav: NavItem[] = [
  { label: "About", href: "/about" },
  { label: "Services", href: "/services" },
  { label: "Industries", href: "/industries" },
  { label: "Network", href: "/network" },
  { label: "Insights", href: "/#insights" },
  { label: "Careers", href: "/careers" },
  { label: "Contact", href: "/contact" },
];

export const utilityNav: NavItem[] = [
  { label: "Get a Quote", href: "/contact#quote" },
  { label: "Track Shipment", href: "/contact#track" },
];

export const ctaNav: NavItem = { label: "Let's talk", href: "/contact" };
