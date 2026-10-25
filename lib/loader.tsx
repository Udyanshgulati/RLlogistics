"use client";

import { createContext, useCallback, useContext, useMemo, useRef, useState, type ReactNode } from "react";

type LoaderState = {
  /** The intro loader screen was removed: the site starts "done" and the hero plays in immediately. */
  done: boolean;
  markDone: () => void;
  /** Register work the loader should wait for (e.g. the globe's first frame). */
  track: (p: Promise<unknown>) => void;
  pending: () => Promise<unknown>[];
};

const LoaderContext = createContext<LoaderState | null>(null);

export function LoaderProvider({ children }: { children: ReactNode }) {
  const [done, setDone] = useState(true);
  const tasks = useRef<Promise<unknown>[]>([]);
  const track = useCallback((p: Promise<unknown>) => void tasks.current.push(p), []);
  const pending = useCallback(() => tasks.current, []);
  const markDone = useCallback(() => setDone(true), []);
  const value = useMemo(() => ({ done, markDone, track, pending }), [done, markDone, track, pending]);
  return <LoaderContext.Provider value={value}>{children}</LoaderContext.Provider>;
}

export function useLoader() {
  const ctx = useContext(LoaderContext);
  if (!ctx) throw new Error("useLoader must be used inside <LoaderProvider>");
  return ctx;
}
