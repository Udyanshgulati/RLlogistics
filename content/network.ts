// Hub coordinates are real city locations; volumes and capabilities are DUMMY.
export type Hub = { id: string; name: string; lat: number; lng: number; kind: "hub" | "port" | "icd" | "city"; detail: string };

export const hubs: Hub[] = [
  { id: "delhi", name: "Delhi NCR", lat: 28.61, lng: 77.21, kind: "hub", detail: "Head office, control tower, 3 warehouses, fleet base" },
  { id: "tkd", name: "ICD Tughlakabad", lat: 28.5, lng: 77.29, kind: "icd", detail: "Customs desk, container drayage" },
  { id: "jnpt", name: "JNPT / Mumbai", lat: 18.95, lng: 72.95, kind: "port", detail: "Ocean FCL/LCL gateway, drayage" },
  { id: "mundra", name: "Mundra", lat: 22.84, lng: 69.7, kind: "port", detail: "Ocean gateway, project cargo" },
  { id: "chennai", name: "Chennai", lat: 13.08, lng: 80.27, kind: "port", detail: "Ocean gateway for South India" },
  { id: "kolkata", name: "Kolkata", lat: 22.57, lng: 88.36, kind: "port", detail: "East India distribution, Haldia port" },
  { id: "bengaluru", name: "Bengaluru", lat: 12.97, lng: 77.59, kind: "city", detail: "E-commerce cross-dock" },
  { id: "hyderabad", name: "Hyderabad", lat: 17.39, lng: 78.49, kind: "city", detail: "Pharma corridor partner hub" },
  { id: "ahmedabad", name: "Ahmedabad", lat: 23.02, lng: 72.57, kind: "city", detail: "Textile & chemicals partner hub" },
  { id: "ludhiana", name: "Ludhiana", lat: 30.9, lng: 75.85, kind: "city", detail: "Textile & auto components pickups" },
  { id: "jaipur", name: "Jaipur", lat: 26.91, lng: 75.79, kind: "city", detail: "First RL corridor, est. 2018" },
  { id: "lucknow", name: "Lucknow", lat: 26.85, lng: 80.95, kind: "city", detail: "UP distribution partner hub" },
  { id: "guwahati", name: "Guwahati", lat: 26.14, lng: 91.74, kind: "city", detail: "North-East gateway" },
];

export const networkStats = [
  { value: "28", label: "States served" }, // DUMMY
  { value: "13", label: "Hubs & gateways" }, // DUMMY
  { value: "650+", label: "Vehicles on network" }, // DUMMY
  { value: "24×7", label: "Control tower" },
];
