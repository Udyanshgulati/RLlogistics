// Shared colour logic: orange/red on the upper-left rim, electric blue on the lower-right.
const rimColor = /* glsl */ `
  uniform vec3 uWarm;
  uniform vec3 uCool;
  vec3 rimColor(vec2 dir) {
    float t = dot(normalize(dir + 1e-5), normalize(vec2(-1.0, 1.15)));
    return mix(uCool, uWarm, smoothstep(-0.45, 0.4, t));
  }
`;

/** Outer glow — rendered on the back faces of a slightly larger sphere, additive. */
export const atmosphere = {
  vertex: /* glsl */ `
    varying vec3 vNormal;
    void main() {
      vNormal = normalize(normalMatrix * normal);
      gl_Position = projectionMatrix * modelViewMatrix * vec4(position, 1.0);
    }
  `,
  fragment: /* glsl */ `
    ${rimColor}
    uniform float uLimb;
    uniform float uStrength;
    varying vec3 vNormal;
    void main() {
      float k = clamp(-vNormal.z / uLimb, 0.0, 1.0);
      float glow = pow(k, 1.9);
      vec3 col = rimColor(vNormal.xy);
      col += vec3(1.0, 0.92, 0.85) * pow(k, 10.0) * 0.35;
      gl_FragColor = vec4(col * glow * uStrength, glow * uStrength);
    }
  `,
};

/** Ocean body: near-black with a coloured fresnel rim. */
export const ocean = {
  vertex: /* glsl */ `
    varying vec3 vNormal;
    varying vec3 vView;
    void main() {
      vec4 mv = modelViewMatrix * vec4(position, 1.0);
      vNormal = normalize(normalMatrix * normal);
      vView = normalize(-mv.xyz);
      gl_Position = projectionMatrix * mv;
    }
  `,
  fragment: /* glsl */ `
    ${rimColor}
    uniform vec3 uBase;
    uniform float uRim;
    varying vec3 vNormal;
    varying vec3 vView;
    void main() {
      float ndv = clamp(dot(vNormal, vView), 0.0, 1.0);
      float fres = pow(1.0 - ndv, 3.2);
      vec3 col = uBase + rimColor(vNormal.xy) * fres * uRim;
      gl_FragColor = vec4(col, 1.0);
    }
  `,
};

/** Instanced land dots — tinted by the rim colour toward the limb, faded right at the edge. */
export const dots = {
  vertex: /* glsl */ `
    varying float vNdv;
    varying vec2 vDir;
    void main() {
      vec4 center = modelViewMatrix * instanceMatrix * vec4(0.0, 0.0, 0.0, 1.0);
      vec4 origin = modelViewMatrix * vec4(0.0, 0.0, 0.0, 1.0);
      vec3 n = normalize(center.xyz - origin.xyz);
      vNdv = dot(n, normalize(-center.xyz));
      vDir = n.xy;
      gl_Position = projectionMatrix * modelViewMatrix * instanceMatrix * vec4(position, 1.0);
    }
  `,
  fragment: /* glsl */ `
    ${rimColor}
    uniform vec3 uDot;
    uniform float uOpacity;
    varying float vNdv;
    varying vec2 vDir;
    void main() {
      float edge = 1.0 - clamp(vNdv, 0.0, 1.0);
      vec3 col = mix(uDot, rimColor(vDir) * 1.4, pow(edge, 2.2));
      float a = uOpacity * smoothstep(0.0, 0.22, vNdv);
      gl_FragColor = vec4(col, a);
    }
  `,
};
