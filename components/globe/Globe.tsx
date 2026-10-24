"use client";

import { Canvas, useFrame, useThree } from "@react-three/fiber";
import { Line } from "@react-three/drei";
import { useEffect, useMemo, useRef, useState, type MutableRefObject } from "react";
import * as THREE from "three";
import { globeMarkers, globeRest } from "@/content/home";
import { tokenRGB } from "@/lib/tokens";
import { atmosphere, dots, ocean } from "./shaders";
import { arcPoints, latLngToVec3, loadLandPoints, rotationFor, smoothstep } from "./geo";

export type GlobeProgress = {
  /** 0 → 1 load-in (scale 0.9 → 1, fade). */
  intro: number;
  /** 0 → 1 scroll dive. */
  dive: number;
};

type Props = {
  progress: MutableRefObject<GlobeProgress>;
  /** Render loop runs only while true (hero on screen). */
  active: boolean;
  onReady?: () => void;
  /** Horizontal resting offset of the globe in world units (desktop pushes it right). */
  offsetX?: number;
};

const ATMOS_R = 1.24;
const DELHI = globeMarkers[0];
const lerp = THREE.MathUtils.lerp;

// ── Land dots ────────────────────────────────────────────────────────────────
function LandDots({ points, uniforms }: { points: Int16Array; uniforms: Record<string, THREE.IUniform> }) {
  const ref = useRef<THREE.InstancedMesh>(null);
  const count = points.length / 2;
  const geometry = useMemo(() => new THREE.CircleGeometry(0.0038, 10), []);

  useEffect(() => {
    const mesh = ref.current;
    if (!mesh) return;
    const dummy = new THREE.Object3D();
    const v = new THREE.Vector3();
    for (let i = 0; i < count; i++) {
      latLngToVec3(points[i * 2] / 100, points[i * 2 + 1] / 100, 1.002, v);
      dummy.position.copy(v);
      dummy.lookAt(v.x * 2, v.y * 2, v.z * 2);
      dummy.updateMatrix();
      mesh.setMatrixAt(i, dummy.matrix);
    }
    mesh.instanceMatrix.needsUpdate = true;
  }, [points, count]);

  return (
    <instancedMesh ref={ref} args={[geometry, undefined, count]} frustumCulled={false}>
      <shaderMaterial
        vertexShader={dots.vertex}
        fragmentShader={dots.fragment}
        uniforms={uniforms}
        transparent
        depthWrite={false}
      />
    </instancedMesh>
  );
}

// ── Routes ───────────────────────────────────────────────────────────────────
function Routes({ opacity }: { opacity: MutableRefObject<number> }) {
  const brand = useMemo(() => new THREE.Color(...tokenRGB("brand")), []);
  const arcs = useMemo(() => {
    const from = latLngToVec3(DELHI.lat, DELHI.lng, 1.004);
    return globeMarkers.slice(1).map((m) => ({ id: m.id, pts: arcPoints(from, latLngToVec3(m.lat, m.lng, 1.004)) }));
  }, []);
  const lines = useRef<Array<{ material: THREE.Material & { dashOffset: number; opacity: number } } | null>>([]);

  useFrame((_, dt) => {
    lines.current.forEach((l, i) => {
      if (!l) return;
      l.material.dashOffset -= dt * (0.12 + i * 0.015);
      l.material.opacity = 0.9 * opacity.current;
    });
  });

  return (
    <group>
      {arcs.map((a, i) => (
        <Line
          key={a.id}
          ref={(el) => void (lines.current[i] = el as never)}
          points={a.pts}
          color={brand}
          lineWidth={1.6}
          renderOrder={9}
          dashed
          dashSize={0.035}
          gapSize={0.025}
          transparent
          depthWrite={false}
        />
      ))}
    </group>
  );
}

// ── Pins (surface dots + pulsing hub ring) ───────────────────────────────────
function Pins({ opacity }: { opacity: MutableRefObject<number> }) {
  const group = useRef<THREE.Group>(null);
  const ring = useRef<THREE.Mesh>(null);
  const brand = useMemo(() => new THREE.Color(...tokenRGB("brand")), []);
  const placed = useMemo(
    () =>
      globeMarkers.map((m) => {
        const pos = latLngToVec3(m.lat, m.lng, 1.006);
        const q = new THREE.Quaternion().setFromUnitVectors(new THREE.Vector3(0, 0, 1), pos.clone().normalize());
        return { ...m, pos, q };
      }),
    [],
  );

  useFrame(({ clock }) => {
    group.current?.traverse((o) => {
      if (o instanceof THREE.Mesh && o !== ring.current) (o.material as THREE.MeshBasicMaterial).opacity = opacity.current;
    });
    const r = ring.current;
    if (!r) return;
    const t = (clock.elapsedTime % 1.6) / 1.6;
    r.scale.setScalar(1 + t * 3.2);
    (r.material as THREE.MeshBasicMaterial).opacity = (1 - t) * 0.8 * opacity.current;
  });

  // Drawn after the land dots so the pins stay crisp on top.
  return (
    <group ref={group}>
      {placed.map((m) => (
        <group key={m.id} position={m.pos} quaternion={m.q}>
          <mesh renderOrder={10}>
            <circleGeometry args={["hub" in m ? 0.014 : 0.009, 20]} />
            <meshBasicMaterial color={"hub" in m ? brand : "#ffffff"} transparent depthWrite={false} />
          </mesh>
          {"hub" in m && (
            <mesh ref={ring} renderOrder={10}>
              <ringGeometry args={[0.014, 0.018, 32]} />
              <meshBasicMaterial color={brand} transparent depthWrite={false} />
            </mesh>
          )}
        </group>
      ))}
    </group>
  );
}

// ── Stars ────────────────────────────────────────────────────────────────────
function Stars({ opacity }: { opacity: MutableRefObject<number> }) {
  const ref = useRef<THREE.Points>(null);
  const geometry = useMemo(() => {
    const n = 900;
    const arr = new Float32Array(n * 3);
    for (let i = 0; i < n; i++) {
      const v = new THREE.Vector3().randomDirection().multiplyScalar(8 + Math.random() * 10);
      arr.set([v.x, v.y, v.z - 6], i * 3);
    }
    const g = new THREE.BufferGeometry();
    g.setAttribute("position", new THREE.BufferAttribute(arr, 3));
    return g;
  }, []);

  useFrame((_, dt) => {
    const p = ref.current;
    if (!p) return;
    p.rotation.y += dt * 0.004;
    (p.material as THREE.PointsMaterial).opacity = 0.55 * opacity.current;
  });

  return (
    <points ref={ref} geometry={geometry}>
      <pointsMaterial size={0.025} sizeAttenuation color="#c9d2e6" transparent depthWrite={false} />
    </points>
  );
}

// ── Scene ────────────────────────────────────────────────────────────────────
function GlobeScene({
  points,
  progress,
  offsetX,
  labels,
  onFirstFrame,
}: {
  points: Int16Array;
  progress: MutableRefObject<GlobeProgress>;
  offsetX: number;
  labels: MutableRefObject<Array<HTMLDivElement | null>>;
  onFirstFrame: () => void;
}) {
  const { camera, size } = useThree();
  const root = useRef<THREE.Group>(null);
  const spin = useRef<THREE.Group>(null);
  const atmosMat = useRef<THREE.ShaderMaterial>(null);
  const oceanMat = useRef<THREE.ShaderMaterial>(null);
  const overlayOpacity = useRef(1);
  const starOpacity = useRef(1);
  const parallax = useRef(new THREE.Vector2());
  const first = useRef(true);

  const colors = useMemo(() => {
    const warm = new THREE.Color("#ff5a24");
    const cool = new THREE.Color(...tokenRGB("ocean-bright"));
    return { warm, cool };
  }, []);

  const dotUniforms = useMemo(
    () => ({
      uDot: { value: new THREE.Color("#d9deea") },
      uOpacity: { value: 1 },
      uWarm: { value: colors.warm },
      uCool: { value: colors.cool },
    }),
    [colors],
  );

  const rest = useMemo(() => rotationFor(globeRest.lat, globeRest.lng), []);
  const delhi = useMemo(() => rotationFor(DELHI.lat, DELHI.lng), []);
  const markerPos = useMemo(() => globeMarkers.map((m) => latLngToVec3(m.lat, m.lng, 1.006)), []);
  const tmp = useMemo(() => ({ v: new THREE.Vector3(), n: new THREE.Vector3(), c: new THREE.Vector3() }), []);

  useFrame(({ clock, pointer }, dt) => {
    const g = root.current;
    const s = spin.current;
    if (!g || !s) return;
    const { intro, dive } = progress.current;
    const swing = smoothstep(0, 0.42, dive); // rotate Delhi to centre, globe slides to middle
    const plunge = smoothstep(0.38, 0.78, dive); // camera falls into the surface

    // Mouse parallax eases out as the dive takes over.
    parallax.current.lerp(pointer, 1 - Math.pow(0.001, dt));
    const par = 1 - swing;
    const idle = Math.sin(clock.elapsedTime * 0.12) * 0.07 * par;

    s.rotation.x = lerp(rest.x, delhi.x, swing) - parallax.current.y * 0.06 * par;
    s.rotation.y = lerp(rest.y, delhi.y, swing) + idle + parallax.current.x * 0.08 * par;

    const introScale = lerp(0.9, 1, intro);
    g.scale.setScalar(introScale);
    g.position.x = lerp(offsetX, 0, swing);
    // Portrait screens: pull the camera back and lift the globe above the copy.
    const aspect = size.width / size.height;
    const baseZ = aspect >= 1.2 ? 3.8 : 3.8 * 0.85 * Math.min(2, 1.2 / aspect);
    g.position.y = lerp(aspect < 1 ? 0.95 : 0, 0, swing);

    camera.position.z = lerp(lerp(baseZ, 2.35, swing), 1.04, plunge);
    camera.updateProjectionMatrix();

    const fadeIn = intro;
    dotUniforms.uOpacity.value = fadeIn * (1 - smoothstep(0.42, 0.6, dive));
    overlayOpacity.current = fadeIn * (1 - smoothstep(0.04, 0.22, dive));
    starOpacity.current = fadeIn * (1 - smoothstep(0.2, 0.55, dive));
    if (atmosMat.current) atmosMat.current.uniforms.uStrength.value = fadeIn * lerp(1.15, 2.2, plunge);
    if (oceanMat.current) oceanMat.current.uniforms.uRim.value = lerp(1.25, 2.4, plunge);

    // Project marker labels into screen space; hide those on the far side.
    g.updateMatrixWorld();
    markerPos.forEach((p, i) => {
      const el = labels.current[i];
      if (!el) return;
      tmp.v.copy(p).applyMatrix4(s.matrixWorld);
      tmp.c.setFromMatrixPosition(s.matrixWorld);
      tmp.n.copy(tmp.v).sub(tmp.c).normalize();
      const facing = tmp.n.dot(tmp.c.copy(camera.position).sub(tmp.v).normalize());
      tmp.v.project(camera);
      const x = (tmp.v.x * 0.5 + 0.5) * size.width;
      const y = (-tmp.v.y * 0.5 + 0.5) * size.height;
      el.style.transform = `translate3d(${x}px, ${y}px, 0)`;
      el.style.opacity = String(smoothstep(0.12, 0.35, facing) * overlayOpacity.current);
    });

    if (first.current) {
      first.current = false;
      onFirstFrame();
    }
  });

  return (
    <>
      <Stars opacity={starOpacity} />
      <group ref={root}>
        <group ref={spin}>
          <mesh>
            <sphereGeometry args={[1, 96, 96]} />
            <shaderMaterial
              ref={oceanMat}
              vertexShader={ocean.vertex}
              fragmentShader={ocean.fragment}
              uniforms={{
                uBase: { value: new THREE.Color("#05070d") },
                uRim: { value: 1.25 },
                uWarm: { value: colors.warm },
                uCool: { value: colors.cool },
              }}
            />
          </mesh>
          <LandDots points={points} uniforms={dotUniforms} />
          <Routes opacity={overlayOpacity} />
          <Pins opacity={overlayOpacity} />
        </group>
        <mesh>
          <sphereGeometry args={[ATMOS_R, 96, 96]} />
          <shaderMaterial
            ref={atmosMat}
            vertexShader={atmosphere.vertex}
            fragmentShader={atmosphere.fragment}
            uniforms={{
              uLimb: { value: Math.sqrt(1 - 1 / (ATMOS_R * ATMOS_R)) },
              uStrength: { value: 0 },
              uWarm: { value: colors.warm },
              uCool: { value: colors.cool },
            }}
            side={THREE.BackSide}
            blending={THREE.AdditiveBlending}
            transparent
            depthWrite={false}
          />
        </mesh>
      </group>
    </>
  );
}

// ── Public component ─────────────────────────────────────────────────────────
export default function Globe({ progress, active, onReady, offsetX = 1 }: Props) {
  const [points, setPoints] = useState<Int16Array | null>(null);
  const labels = useRef<Array<HTMLDivElement | null>>([]);
  const readyFired = useRef(false);

  useEffect(() => {
    let alive = true;
    loadLandPoints().then((p) => alive && setPoints(p));
    return () => {
      alive = false;
    };
  }, []);

  const handleFirstFrame = () => {
    if (readyFired.current) return;
    readyFired.current = true;
    onReady?.();
  };

  return (
    <div className="absolute inset-0" aria-hidden>
      <Canvas
        flat
        frameloop={active ? "always" : "never"}
        dpr={[1, 1.75]}
        gl={{ antialias: true, alpha: true, powerPreference: "high-performance" }}
        camera={{ fov: 35, near: 0.01, far: 100, position: [0, 0, 3.8] }}
      >
        {points && (
          <GlobeScene points={points} progress={progress} offsetX={offsetX} labels={labels} onFirstFrame={handleFirstFrame} />
        )}
      </Canvas>

      {/* Marker labels with leader lines, positioned each frame from the 3D scene */}
      <div className="pointer-events-none absolute inset-0 overflow-hidden">
        {globeMarkers.map((m, i) => (
          <div
            key={m.id}
            ref={(el) => void (labels.current[i] = el)}
            className="absolute left-0 top-0 opacity-0 will-change-transform"
          >
            <div className={m.side === "left" ? "absolute bottom-0 right-0 flex items-end" : "absolute bottom-0 left-0 flex items-end"}>
              {m.side === "left" && <MarkerText label={m.label} sub={m.sub} hub={"hub" in m} align="right" />}
              <svg width="38" height="26" viewBox="0 0 38 26" className={m.side === "left" ? "" : "-scale-x-100"} fill="none">
                <path d="M1 1 H14 L37 25" stroke="currentColor" strokeWidth="1" className="text-paper/60" vectorEffect="non-scaling-stroke" />
              </svg>
              {m.side === "right" && <MarkerText label={m.label} sub={m.sub} hub={"hub" in m} align="left" />}
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}

function MarkerText({ label, sub, hub, align }: { label: string; sub: string; hub: boolean; align: "left" | "right" }) {
  return (
    <div className={`-mb-1.5 flex flex-col leading-tight ${align === "right" ? "items-end pr-1" : "items-start pl-1"}`} style={{ transform: "translateY(-18px)" }}>
      <span className={`ui-label rounded-sm bg-ink/80 px-1 py-px text-[10px] ${hub ? "text-brand" : "text-paper"}`}>
        {hub && <span className="mr-1.5 inline-block h-1.5 w-1.5 animate-pulse rounded-full bg-brand align-middle" />}
        {label}
      </span>
      <span className="ui-label px-1 text-[9px] text-steel">{sub}</span>
    </div>
  );
}
