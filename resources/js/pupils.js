const BASE_X = -40;
const BASE_Y = 0;

const INNER_R = 92;
const PUPIL_R = 49;
const MARGIN = 3;
const MAX = Math.max(0, INNER_R - PUPIL_R - MARGIN);

const SENS_X = 2.0;
const SENS_Y = 1.5;

const FOLLOW_L = 0.22;
const FOLLOW_R = 0.18;

const SACCADE_SPEED_TH = 0.85;
const SACCADE_IMPULSE = 7.5;
const SACCADE_DECAY = 0.7;

const INPUT_SMOOTH = 0.35;

const clamp = (v, min, max) => Math.max(min, Math.min(max, v));
const hypot = Math.hypot;

const boundCircle = (x, y, max) => {
    const len = hypot(x, y);
    if (len <= max || len === 0) return { x, y };
    const k = max / len;
    return { x: x * k, y: y * k };
};

const setPupil = (el, dx, dy) => {
    el.setAttribute(
        'transform',
        `translate(${(BASE_X + dx).toFixed(2)} ${(BASE_Y + dy).toFixed(2)})`,
    );
};

const logoStates = new Map();
let started = false;
let listening = false;

let tX = 0;
let tY = 0;
let sX = 0;
let sY = 0;

let prevMX = null;
let prevMY = null;
let prevMT = performance.now();

const addLogo = (logoEl) => {
    const pupilL = logoEl.querySelector('[data-pupil="left"]');
    const pupilR = logoEl.querySelector('[data-pupil="right"]');
    if (!pupilL || !pupilR) return;

    const state = {
        pupilL,
        pupilR,
        cLX: 0,
        cLY: 0,
        cRX: 0,
        cRY: 0,
        kLX: 0,
        kLY: 0,
        kRX: 0,
        kRY: 0,
    };

    setPupil(pupilL, 0, 0);
    setPupil(pupilR, 0, 0);

    logoStates.set(logoEl, state);
};

const refreshLogos = () => {
    const logos = document.querySelectorAll('[data-zookr-logo]');
    const next = new Set(logos);

    logos.forEach((logoEl) => {
        if (!logoStates.has(logoEl)) {
            addLogo(logoEl);
        }
    });

    Array.from(logoStates.keys()).forEach((logoEl) => {
        if (!next.has(logoEl)) {
            logoStates.delete(logoEl);
        }
    });
};

const onMouseMove = (e) => {
    const now = performance.now();

    if (prevMX !== null) {
        const dt = Math.max(16, now - prevMT);
        const vx = (e.clientX - prevMX) / dt;
        const vy = (e.clientY - prevMY) / dt;
        const speed = hypot(vx, vy);

        if (speed > SACCADE_SPEED_TH) {
            const inv = 1 / (speed || 1);
            const dirX = vx * inv;
            const dirY = vy * inv;

            const impulseX = dirX * SACCADE_IMPULSE;
            const impulseY = dirY * SACCADE_IMPULSE;
            const kMax = MAX * 0.45;

            logoStates.forEach((state) => {
                state.kLX = clamp(state.kLX + impulseX * 1.0, -kMax, kMax);
                state.kLY = clamp(state.kLY + impulseY * 0.85, -kMax, kMax);
                state.kRX = clamp(state.kRX + impulseX * 0.92, -kMax, kMax);
                state.kRY = clamp(state.kRY + impulseY * 0.78, -kMax, kMax);
            });
        }
    }

    prevMX = e.clientX;
    prevMY = e.clientY;
    prevMT = now;

    let nx = (e.clientX / (window.innerWidth || 1)) * 2 - 1;
    let ny = (e.clientY / (window.innerHeight || 1)) * 2 - 1;

    nx = clamp(nx * SENS_X, -1, 1);
    ny = clamp(ny * SENS_Y, -1, 1);

    const raw = boundCircle(nx * MAX, ny * MAX, MAX);
    tX = raw.x;
    tY = raw.y;
};

const animate = () => {
    if (!logoStates.size) {
        started = false;
        return;
    }

    sX += (tX - sX) * INPUT_SMOOTH;
    sY += (tY - sY) * INPUT_SMOOTH;

    logoStates.forEach((state) => {
        state.kLX *= SACCADE_DECAY;
        state.kLY *= SACCADE_DECAY;
        state.kRX *= SACCADE_DECAY;
        state.kRY *= SACCADE_DECAY;

        const left = boundCircle(sX + state.kLX, sY + state.kLY, MAX);
        const right = boundCircle(
            sX * 0.96 + state.kRX,
            sY * 0.94 + state.kRY,
            MAX,
        );

        state.cLX += (left.x - state.cLX) * FOLLOW_L;
        state.cLY += (left.y - state.cLY) * FOLLOW_L;
        state.cRX += (right.x - state.cRX) * FOLLOW_R;
        state.cRY += (right.y - state.cRY) * FOLLOW_R;

        const L2 = boundCircle(state.cLX, state.cLY, MAX);
        const R2 = boundCircle(state.cRX, state.cRY, MAX);
        state.cLX = L2.x;
        state.cLY = L2.y;
        state.cRX = R2.x;
        state.cRY = R2.y;

        setPupil(state.pupilL, state.cLX, state.cLY);
        setPupil(state.pupilR, state.cRX, state.cRY);
    });

    requestAnimationFrame(animate);
};

export const initPupils = () => {
    if (typeof window === 'undefined') return;

    refreshLogos();

    if (!listening) {
        window.addEventListener('mousemove', onMouseMove, { passive: true });
        listening = true;
    }

    if (!started && logoStates.size) {
        started = true;
        requestAnimationFrame(animate);
    }
};
