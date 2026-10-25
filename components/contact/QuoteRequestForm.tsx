"use client";

import { useState, type FormEvent } from "react";
import { company } from "@/content/company";
import { enquiryMailto, isEmail, isPhone } from "@/lib/enquiry";
import { cn } from "@/lib/cn";

const field =
  "w-full border-b border-paper/25 bg-transparent py-3 text-[16px] lg:text-[15px] text-paper outline-none transition-colors placeholder:text-paper/35 focus:border-brand";

const MODES = ["Road · Full truckload", "Road · Part truckload", "Air freight", "Ocean · FCL", "Ocean · LCL", "Not sure yet"];
const CARGO = ["General cargo", "FMCG", "Pharma (2–8 °C)", "E-commerce parcels", "Machinery / over-dimensional", "Hazardous (DG)"];

/** Quote request on the dark band of /contact — collects the shipment details, no price estimate. */
export function QuoteRequestForm() {
  const [sent, setSent] = useState<{ first: string; mailto: string } | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const d = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>;
    const next: Record<string, string> = {};
    if (!d.origin?.trim()) next.origin = "Where does the cargo start?";
    if (!d.destination?.trim()) next.destination = "Where is it going?";
    if (!d.name?.trim()) next.name = "Add your name so we know who to ask for.";
    if (!isPhone(d.phone)) next.phone = "Enter a 10-digit mobile number.";
    if (d.email && !isEmail(d.email)) next.email = "Enter an email like name@company.in.";
    setErrors(next);
    if (Object.keys(next).length) return;
    const mailto = enquiryMailto(
      `Quote request: ${d.origin} → ${d.destination}`,
      {
        Origin: d.origin,
        Destination: d.destination,
        Mode: d.mode,
        Cargo: d.cargo,
        "Approx. weight (kg)": d.weight,
        "Ready date": d.date,
        Name: d.name,
        Company: d.company,
        Phone: d.phone,
        Email: d.email,
      },
      d.notes,
    );
    setSent({ first: d.name.split(" ")[0], mailto });
  };

  if (sent)
    return (
      <div role="status" className="border-t border-paper/15 pt-8">
        <p className="ui-label text-brand">Quote request received</p>
        <p className="display mt-4 text-[clamp(1.8rem,3vw,2.6rem)] leading-tight">Thanks, {sent.first}.</p>
        <p className="mt-3 max-w-[46ch] text-paper/70">
          Your account manager will call within one working day with an all-in price, transit time and a pickup slot.
        </p>
        <div className="mt-6 flex flex-wrap items-center gap-4">
          <a
            href={sent.mailto}
            className="inline-flex h-11 items-center rounded-full bg-brand px-5 text-[13px] font-medium text-ink transition-colors hover:bg-paper"
          >
            Email these details to {company.email}
          </a>
          <button type="button" onClick={() => setSent(null)} className="ui-label border-b border-paper/30 pb-1 hover:border-brand hover:text-brand">
            New request
          </button>
        </div>
      </div>
    );

  const input = (id: string, label: string, props: React.InputHTMLAttributes<HTMLInputElement> = {}) => (
    <label htmlFor={`q-${id}`} className="flex flex-col">
      <span className="ui-label text-steel">{label}</span>
      <input
        id={`q-${id}`}
        name={id}
        aria-invalid={!!errors[id]}
        aria-describedby={errors[id] ? `q-${id}-err` : undefined}
        className={cn(field, errors[id] && "border-[#ff8a6b]")}
        {...props}
      />
      {errors[id] && (
        <span id={`q-${id}-err`} className="mt-1.5 text-[12px] text-[#ff8a6b]">
          {errors[id]}
        </span>
      )}
    </label>
  );

  const select = (id: string, label: string, options: string[]) => (
    <label htmlFor={`q-${id}`} className="flex flex-col">
      <span className="ui-label text-steel">{label}</span>
      <select id={`q-${id}`} name={id} className={field} defaultValue={options[0]}>
        {options.map((o) => (
          <option key={o} value={o} className="text-ink">
            {o}
          </option>
        ))}
      </select>
    </label>
  );

  return (
    <form onSubmit={submit} noValidate className="grid gap-6 sm:grid-cols-2">
      {input("origin", "Pickup city / port", { placeholder: "e.g. Okhla, Delhi" })}
      {input("destination", "Delivery city / port", { placeholder: "e.g. Peenya, Bengaluru" })}
      {select("mode", "Mode", MODES)}
      {select("cargo", "Cargo type", CARGO)}
      {input("weight", "Approx. weight (kg)", { type: "number", min: 1, inputMode: "numeric", placeholder: "1200" })}
      {input("date", "Ready date", { type: "date" })}
      {input("name", "Your name", { autoComplete: "name" })}
      {input("company", "Company", { autoComplete: "organization" })}
      {input("phone", "Phone", { type: "tel", autoComplete: "tel" })}
      {input("email", "Email (optional)", { type: "email", autoComplete: "email" })}
      <label htmlFor="q-notes" className="flex flex-col sm:col-span-2">
        <span className="ui-label text-steel">Anything else?</span>
        <textarea id="q-notes" name="notes" rows={3} placeholder="Dimensions, packaging, frequency, special handling…" className={cn(field, "resize-none")} />
      </label>
      <div className="sm:col-span-2">
        <button type="submit" className="inline-flex h-12 items-center max-sm:w-full max-sm:justify-center rounded-full bg-brand px-6 text-[14px] font-medium text-ink transition-colors hover:bg-paper">
          Request a quote
        </button>
      </div>
    </form>
  );
}
