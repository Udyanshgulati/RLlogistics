import * as THREE from "three";

const DEG = Math.PI / 180;

/** lat/lng → unit vector. lng 0 / lat 0 faces +Z (toward the camera at rest). */
export function latLngToVec3(lat: number, lng: number, radius = 1, target = new THREE.Vector3()) {
  const la = lat * DEG;
  const ln = lng * DEG;
  return target.set(Math.cos(la) * Math.sin(ln), Math.sin(la), Math.cos(la) * Math.cos(ln)).multiplyScalar(radius);
}

/** Group rotation that brings (lat, lng) to the centre of the view. Apply as Euler(x, y, 0, "XYZ"). */
export function rotationFor(lat: number, lng: number) {
  return { x: lat * DEG, y: -lng * DEG };
}

/** Great-circle arc lifted off the surface — used for the Delhi → city routes. */
export function arcPoints(a: THREE.Vector3, b: THREE.Vector3, segments = 64) {
  const angle = a.angleTo(b);
  const lift = 0.04 + angle * 0.22;
  const pts: THREE.Vector3[] = [];
  const qa = new THREE.Quaternion();
  const axis = new THREE.Vector3().crossVectors(a, b).normalize();
  for (let i = 0; i <= segments; i++) {
    const t = i / segments;
    qa.setFromAxisAngle(axis, angle * t);
    const p = a.clone().normalize().applyQuaternion(qa);
    pts.push(p.multiplyScalar(1 + Math.sin(Math.PI * t) * lift));
  }
  return pts;
}

/** Loads the pre-baked land points (base64 Int16 lat*100, lng*100 pairs). */
export async function loadLandPoints(url = "/globe/land-points.json") {
  const { b64 } = (await fetch(url).then((r) => r.json())) as { b64: string };
  const bin = atob(b64);
  const bytes = new Uint8Array(bin.length);
  for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
  return new Int16Array(bytes.buffer);
}

export const smoothstep = (e0: number, e1: number, x: number) => {
  const t = Math.min(1, Math.max(0, (x - e0) / (e1 - e0)));
  return t * t * (3 - 2 * t);
};
