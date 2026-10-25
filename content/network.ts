// Hub coordinates are real city locations; volumes and capabilities are DUMMY.
export type Hub = { id: string; name: string; lat: number; lng: number; kind: "hub" | "port" | "icd" | "city"; detail: string };

export const hubs: Hub[] = [
  { id: "delhi", name: "Delhi", lat: 28.7, lng: 77.27, kind: "hub", detail: "Registered & corporate office, Bhajanpura · warehousing · fleet base" },
  { id: "tkd", name: "ICD Tughlakabad", lat: 28.5, lng: 77.29, kind: "icd", detail: "Customs desk, container drayage" },
  { id: "jnpt", name: "Mumbai · Bhiwandi", lat: 19.3, lng: 73.06, kind: "port", detail: "Branch & warehouse at Bhiwandi · JNPT container moves" },
  { id: "pune", name: "Pune", lat: 18.6, lng: 74.3, kind: "city", detail: "Branch at Ranjangaon MIDC, Pune–Nagar Road" },
  { id: "aurangabad", name: "Aurangabad", lat: 19.88, lng: 75.34, kind: "city", detail: "Warehouse branch" },
  { id: "mundra", name: "Mundra", lat: 22.84, lng: 69.7, kind: "port", detail: "Ocean gateway, project cargo" },
  { id: "chennai", name: "Chennai", lat: 13.08, lng: 80.27, kind: "port", detail: "Warehouse branch · port container moves" },
  { id: "kolkata", name: "Kolkata · Howrah", lat: 22.57, lng: 88.2, kind: "port", detail: "Branch & warehouse at Alampur, Howrah" },
  { id: "bengaluru", name: "Bengaluru", lat: 13.07, lng: 77.47, kind: "city", detail: "Branch on Tumkur Road, Madanayakanahalli" },
  { id: "hyderabad", name: "Hyderabad", lat: 17.39, lng: 78.49, kind: "city", detail: "Warehouse branch" },
  { id: "ahmedabad", name: "Ahmedabad", lat: 23.02, lng: 72.57, kind: "city", detail: "Warehouse branch" },
  { id: "kanpur", name: "Kanpur", lat: 26.45, lng: 80.33, kind: "city", detail: "Government of India movements" },
  { id: "bhopal", name: "Bhopal", lat: 23.26, lng: 77.41, kind: "city", detail: "Government of India movements" },
  { id: "patna", name: "Patna", lat: 25.59, lng: 85.14, kind: "city", detail: "Government of India movements" },
  { id: "guwahati", name: "Guwahati", lat: 26.14, lng: 91.74, kind: "city", detail: "Government of India movements" },
];

export const networkStats = [
  { value: "7", label: "Warehouse cities" },
  { value: "5", label: "Branch offices" },
  { value: "1000+", label: "Vehicles on the network" },
  { value: "24×7", label: "Control tower" },
];
