// Real company data — keep exact.
export const company = {
  name: "RL Logistics",
  nameSub: "& Warehousing",
  legalName: "RL Logistics and Warehousing Private Limited",
  cin: "U74999DL2018PTC333219",
  incorporated: "2018-04-27",
  incorporatedLabel: "27 April 2018",
  roc: "RoC Delhi",
  directors: ["Randhir Kumar Jha", "Rekha Jha"],
  email: "randhir@rllogistics.in",
  phone: "+91 98XXX XXXXX", // DUMMY placeholder
  address: {
    lines: ["D-228, G/F, Kh. No. 448, Gali No. 7", "Bhajanpura, Near Kali Mata Mandir", "Delhi – 110053, India"],
    street: "D-228, G/F, Kh. No. 448, Gali No. 7, Bhajanpura, Near Kali Mata Mandir",
    locality: "Delhi",
    postalCode: "110053",
    country: "IN",
  },
  url: "https://rllogistics.in",
} as const;
