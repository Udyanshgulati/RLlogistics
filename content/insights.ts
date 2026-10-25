// Articles for /insights. Written as practical guidance; review with the client before launch.
export type Article = {
  slug: string;
  tag: string;
  date: string;
  read: string;
  title: string;
  excerpt: string;
  cover: { src: string; alt: string };
  sections: { heading?: string; paragraphs: string[] }[];
};

export const insights: Article[] = [
  {
    slug: "festive-season-playbook",
    tag: "E-commerce",
    date: "2026-09-12",
    read: "6 min",
    title: "The festive-season playbook: shipping 3× the orders without 3× the chaos",
    excerpt:
      "What we change in slotting, rider rosters and returns control six weeks before Diwali, and the three numbers we check every morning.",
    cover: { src: "/photos/parcels.webp", alt: "Rows of packed cartons ready for dispatch" },
    sections: [
      {
        paragraphs: [
          "Peak season does not break an operation. An unplanned peak season does. Every year the order curve for D2C and marketplace brands in North India starts climbing in late September and peaks in the week before Diwali. The brands that come through it well start preparing in August.",
        ],
      },
      {
        heading: "Six weeks out: fix the warehouse first",
        paragraphs: [
          "We re-slot the top 20% of SKUs by velocity to the pick faces nearest the packing stations. That one change usually cuts picker walking time by a third. We also freeze non-critical SKU and listing changes for the peak window, because every new barcode is a new chance for a mis-pick.",
          "Packing material, labels and printer ribbons are ordered for 1.5× the forecast. Running out of 10×10 boxes on day three of a sale costs more than the extra stock ever will.",
        ],
      },
      {
        heading: "Two weeks out: riders by pin code, not by city",
        paragraphs: [
          "We plan last-mile capacity per pin code from last year's order density, not as one number for Delhi NCR. Gurugram's Sector 56 and Noida's Sector 62 behave nothing alike. Address-quality checks run at order time, so incomplete addresses get a WhatsApp nudge before the parcel ever leaves the warehouse.",
        ],
      },
      {
        heading: "During the peak: three numbers every morning",
        paragraphs: [
          "Orders older than 24 hours that have not shipped. First-attempt delivery rate by pin code. Return-to-origin by pin code and by payment mode. If any of them drifts, the control tower acts the same day by adding a wave, re-routing a rider cluster or switching COD orders in a problem area to prepaid-only.",
          "None of this is exotic. It is simply decided early, written down and checked daily. That is what keeps a 3× week from turning into a month of escalations.",
        ],
      },
    ],
  },
  {
    slug: "icd-vs-port-clearance",
    tag: "Customs",
    date: "2026-07-28",
    read: "5 min",
    title: "ICD or port clearance? How North Indian importers should decide",
    excerpt:
      "Clearing at ICD Tughlakabad instead of Nhava Sheva changes your duty timing, your detention exposure and your trucking bill. Here is how to choose.",
    cover: { src: "/photos/container-stack.webp", alt: "Stacked shipping containers in blue and red" },
    sections: [
      {
        paragraphs: [
          "For an importer in Delhi NCR, a container landing at Nhava Sheva (JNPT) or Mundra can be cleared in two places: at the port, or at an inland container depot (ICD) close to the factory or warehouse, such as Tughlakabad, Patparganj or Dadri. Both are legitimate. They suit different cargo.",
        ],
      },
      {
        heading: "Clearing at the port",
        paragraphs: [
          "The bill of entry is filed and assessed at the port, duty is paid, and the cleared container moves inland by road or rail as domestic cargo. This is the fastest route for urgent, high-value cargo, because there is no wait for a rail slot to the ICD.",
          "The cost is a long road haul for a full container and the risk of port detention if documents are not ready on arrival. Advance filing of the bill of entry on ICEGATE before the vessel arrives is what makes port clearance work.",
        ],
      },
      {
        heading: "Clearing at an ICD",
        paragraphs: [
          "The container moves from the port to the ICD under customs control and is cleared near its final destination. ICD Tughlakabad, operated by CONCOR, is one of the largest dry ports in the country and is connected to the western ports by rail. The Western Dedicated Freight Corridor, which is progressively linking Dadri with the Gujarat and Maharashtra ports, keeps improving that rail link.",
          "ICD clearance usually lowers the inland transport cost per container and lets the importer deal with customs locally. The trade-offs are rail transit time and congestion at the ICD in peak months.",
        ],
      },
      {
        heading: "Our rule of thumb",
        paragraphs: [
          "Time-critical or high-value cargo: clear at the port and move by road. Steady replenishment cargo with predictable demand: route to the ICD. For every shipment we compare the shipping line's free days, the rail slot situation and the cargo's urgency before deciding, and we tell the client which route we picked and why.",
        ],
      },
    ],
  },
  {
    slug: "e-way-bill-checklist",
    tag: "Compliance",
    date: "2026-06-16",
    read: "4 min",
    title: "E-way bills without the roadside surprises",
    excerpt:
      "When you need one, how long it stays valid, and the small mistakes that get trucks stopped on the highway.",
    cover: { src: "/photos/highway-truck.webp", alt: "Truck on a lit highway at night" },
    sections: [
      {
        paragraphs: [
          "Under GST, goods moving by road generally need an e-way bill when the consignment value is above ₹50,000. Some states set their own limits for movements within the state, so check the rule for your origin state. Most roadside detentions we see come from small, avoidable errors rather than missing bills.",
        ],
      },
      {
        heading: "Validity is tied to distance",
        paragraphs: [
          "For regular cargo an e-way bill is valid for one day for every 200 km, or part of it. For over-dimensional cargo it is one day for every 20 km. Plan the generation time carefully: a bill generated the evening before a slow-moving load can expire before the truck reaches its destination.",
        ],
      },
      {
        heading: "The mistakes that stop trucks",
        paragraphs: [
          "A vehicle number in Part B that doesn't match the truck actually carrying the goods, usually because the cargo was shifted to another vehicle at a hub and Part B was never updated. Invoice values or HSN codes that don't match the e-way bill. Expired bills on loads delayed by weather or a breakdown, which can be extended if done in time.",
          "Our control tower checks validity against live GPS positions and flags any bill that will expire before the truck arrives, so it can be extended before it becomes a problem on the road.",
        ],
      },
    ],
  },
  {
    slug: "reefer-checklist",
    tag: "Pharma",
    date: "2026-05-04",
    read: "4 min",
    title: "A 10-point checklist before you book a 2–8 °C reefer",
    excerpt:
      "Pre-cooling, logger placement, door-open discipline: the small details that decide whether a cold-chain shipment arrives in spec.",
    cover: { src: "/photos/warehouse.webp", alt: "Warehouse aisles with palletised stock" },
    sections: [
      {
        paragraphs: [
          "Temperature excursions rarely happen on the highway. They happen at the dock: a reefer that was not pre-cooled, a door left open while pallets were rearranged, a logger buried where it cannot see the warm air. This is the checklist our pharma desk runs before every 2–8 °C dispatch.",
        ],
      },
      {
        heading: "Before loading",
        paragraphs: [
          "1. Pre-cool the reefer body to the set point for at least 60 minutes and record the reading. 2. Check the unit's fuel or power and the last service date. 3. Confirm that the product was conditioned to temperature before it reached the dock. 4. Agree on the excursion protocol in writing: who gets called, within how many minutes, and what the driver does.",
        ],
      },
      {
        heading: "While loading",
        paragraphs: [
          "5. Load by pallet in a planned sequence and keep door-open time under 10 minutes. 6. Leave air gaps at the front wall, the floor and the sides so cold air can circulate. 7. Place one data logger near the door and one at the front wall, because that is where excursions show up first.",
        ],
      },
      {
        heading: "In transit and at delivery",
        paragraphs: [
          "8. Write the seal numbers and logger IDs on the transport documents so the receiver can verify them. 9. Monitor live temperature through the control tower and investigate any alarm within minutes. 10. Download the logger data at delivery and share it with the consignee before the truck leaves.",
        ],
      },
    ],
  },
];
