"use client";

import { useState, type FormEvent } from "react";
import { services } from "@/content/services";
import { company } from "@/content/company";
import { enquiryMailto, isEmail, isPhone } from "@/lib/enquiry";
import { cn } from "@/lib/cn";

const field = "w-full border-b border-ink/20 bg-transparent py-3 text-[16px] lg:text-[15px] outline-none transition-colors placeholder:text-ink/35 focus:border-brand";

/** Enquiry form — front-end only for now; shows a success state instead of sending. */
export function ContactForm() {
  const [sent, setSent] = useState<{ first: string; mailto: string } | null>(null);
  const [errors, setErrors] = useState<Record<string, string>>({});

  const submit = (e: FormEvent<HTMLFormElement>) => {
    e.preventDefault();
    const data = Object.fromEntries(new FormData(e.currentTarget)) as Record<string, string>;
    const next: Record<string, string> = {};
    if (!data.name?.trim()) next.name = "Add your name so we know who to ask for.";
    if (!isEmail(data.email)) next.email = "Enter an email like name@company.in.";
    if (!isPhone(data.phone)) next.phone = "Enter a 10-digit mobile number.";
    setErrors(next);
    if (Object.keys(next).length === 0) {
      const service = services.find((x) => x.slug === data.service)?.title ?? data.service;
      const mailto = enquiryMailto(
        `Enquiry from ${data.company || data.name}`,
        { Name: data.name, Company: data.company, Phone: data.phone, Email: data.email, Service: service },
        data.message,
      );
      setSent({ first: data.name.split(" ")[0], mailto });
    }
  };

  if (sent)
    return (
      <div role="status" className="border-t border-ink/15 pt-8">
        <p className="ui-label text-brand">Enquiry received</p>
        <p className="display mt-4 text-[clamp(1.8rem,3vw,2.6rem)] leading-tight">Thanks, {sent.first}.</p>
        <p className="mt-3 max-w-[44ch] text-ink/70">A member of our team will call you within one working day with next steps and an all-in quote.</p>
        <a
          href={sent.mailto}
          className="mt-6 inline-flex h-11 items-center rounded-[10px] bg-ink px-5 text-[13px] font-medium text-paper transition-colors hover:bg-brand hover:text-ink"
        >
          Email these details to {company.email}
        </a>
        <button type="button" onClick={() => setSent(null)} className="ui-label mt-6 border-b border-ink/30 pb-1 hover:border-brand hover:text-brand">
          Send another enquiry
        </button>
      </div>
    );

  return (
    <form onSubmit={submit} noValidate className="grid gap-6 sm:grid-cols-2">
      {[
        { id: "name", label: "Full name", type: "text", auto: "name" },
        { id: "company", label: "Company", type: "text", auto: "organization" },
        { id: "phone", label: "Phone", type: "tel", auto: "tel" },
        { id: "email", label: "Work email", type: "email", auto: "email" },
      ].map((f) => (
        <label key={f.id} htmlFor={`cf-${f.id}`} className="flex flex-col">
          <span className="ui-label text-steel">{f.label}</span>
          <input
            id={`cf-${f.id}`}
            name={f.id}
            type={f.type}
            autoComplete={f.auto}
            aria-invalid={!!errors[f.id]}
            aria-describedby={errors[f.id] ? `cf-${f.id}-err` : undefined}
            className={cn(field, errors[f.id] && "border-[#c0392b]")}
          />
          {errors[f.id] && (
            <span id={`cf-${f.id}-err`} className="mt-1.5 text-[12px] text-[#c0392b]">
              {errors[f.id]}
            </span>
          )}
        </label>
      ))}
      <label htmlFor="cf-service" className="flex flex-col sm:col-span-2">
        <span className="ui-label text-steel">Service</span>
        <select id="cf-service" name="service" className={field} defaultValue="">
          <option value="" disabled>
            Choose a service
          </option>
          {services.map((s) => (
            <option key={s.slug} value={s.slug}>
              {s.title}
            </option>
          ))}
          <option value="multiple">More than one</option>
        </select>
      </label>
      <label htmlFor="cf-message" className="flex flex-col sm:col-span-2">
        <span className="ui-label text-steel">What are you shipping?</span>
        <textarea id="cf-message" name="message" rows={4} placeholder="Cargo, volumes, lanes, timelines…" className={cn(field, "resize-none")} />
      </label>
      <div className="sm:col-span-2">
        <button type="submit" className="inline-flex h-12 items-center max-sm:w-full max-sm:justify-center gap-3 rounded-[10px] bg-ink px-6 text-[14px] font-medium text-paper transition-colors hover:bg-brand hover:text-ink">
          Send enquiry
        </button>
      </div>
    </form>
  );
}
