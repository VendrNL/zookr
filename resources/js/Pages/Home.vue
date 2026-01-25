<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';
import ApplicationLogo from '@/Components/ApplicationLogo.vue';

const props = defineProps({
    canLogin: {
        type: Boolean,
    },
    canRegister: {
        type: Boolean,
    },
    searchRequests: {
        type: Array,
        default: () => [],
    },
    tickerOrganizations: {
        type: Array,
        default: () => [],
    },
});

const bmcScriptId = 'bmc-button-script';

let parallaxObserver;
let onScrollHandler;
let scrollHint;
const carousel = ref(null);
const carouselShell = ref(null);
const canScrollPrev = ref(false);
const canScrollNext = ref(false);
let onCarouselScrollHandler;
let onCarouselResizeHandler;

const scrollCarousel = (direction) => {
    const viewport = carousel.value;
    if (!viewport) {
        return;
    }

    const track = viewport.querySelector('.carousel-track');
    const card = track?.querySelector('.request-card');
    if (!track || !card) {
        return;
    }

    const styles = window.getComputedStyle(track);
    const gap = Number.parseFloat(styles.gap || '0') || 0;
    const step = card.getBoundingClientRect().width + gap;

    viewport.scrollBy({ left: direction * step, behavior: 'smooth' });
};

const formatLabel = (value) => {
    if (!value) return "-";
    const parts = value.replaceAll("_", " ").split(" ");
    return parts
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join(" ");
};

const acquisitionLabel = (value) => (value === "huur" ? "Huur" : "Koop");

const acquisitionList = (list) => {
    if (!list?.length) return "-";
    return list.map(acquisitionLabel).join(", ");
};

const formatProvince = (value) => {
    if (!value) return "-";
    const parts = value.split("_");
    if (parts.length > 1) {
        return parts
            .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
            .join("-");
    }
    return formatLabel(value);
};

const formatProvinceList = (list) => {
    if (!list?.length) return "-";
    return list.map(formatProvince).join(", ");
};

const formatLocation = (item) =>
    item.location || formatProvinceList(item.provinces);

const shuffle = (items) => {
    const result = [...items];
    for (let i = result.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [result[i], result[j]] = [result[j], result[i]];
    }
    return result;
};

const tickerLogos = ref([]);

const initialsFor = (name) => {
    if (!name) return "";
    return name
        .split(" ")
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part.charAt(0).toUpperCase())
        .join("");
};

const updateCarouselLayout = () => {
    const shell = carouselShell.value;
    const viewport = carousel.value;
    if (!shell || !viewport) {
        return;
    }

    const track = viewport.querySelector('.carousel-track');
    const card = track?.querySelector('.request-card');
    if (!track || !card) {
        return;
    }

    const shellStyles = window.getComputedStyle(shell);
    const shellGap = Number.parseFloat(shellStyles.gap || '0') || 0;
    const arrowWidth =
        Array.from(shell.querySelectorAll('.carousel-arrow')).reduce(
            (total, arrow) => total + arrow.getBoundingClientRect().width,
            0,
        ) || 0;
    const containerWidth =
        shell.parentElement?.getBoundingClientRect().width || window.innerWidth;
    const viewportStyles = window.getComputedStyle(viewport);
    const paddingX =
        (Number.parseFloat(viewportStyles.paddingLeft || '0') || 0) +
        (Number.parseFloat(viewportStyles.paddingRight || '0') || 0);
    const available =
        containerWidth -
        arrowWidth -
        Math.max(0, shellGap * 2) -
        paddingX;

    const trackStyles = window.getComputedStyle(track);
    const cardGap = Number.parseFloat(trackStyles.gap || '0') || 0;
    const cardWidth = card.getBoundingClientRect().width || 0;
    if (!cardWidth) {
        return;
    }
    const cardUnit = cardWidth + cardGap;
    const visible = Math.max(1, Math.floor((available + cardGap) / cardUnit));

    const width =
        visible * cardWidth + Math.max(0, visible - 1) * cardGap + paddingX;
    viewport.style.width = `${width}px`;
    shell.style.width = `${width + arrowWidth + shellGap * 2}px`;
    updateCarouselState();
};

const updateCarouselState = () => {
    const viewport = carousel.value;
    if (!viewport) {
        canScrollPrev.value = false;
        canScrollNext.value = false;
        return;
    }

    const maxScroll = viewport.scrollWidth - viewport.clientWidth;
    const current = viewport.scrollLeft;
    const threshold = 1;

    canScrollPrev.value = current > threshold;
    canScrollNext.value = current < maxScroll - threshold;
};

onMounted(() => {
    if (document.getElementById(bmcScriptId)) {
        return;
    }

    const script = document.createElement('script');
    script.id = bmcScriptId;
    script.type = 'text/javascript';
    script.src = 'https://cdnjs.buymeacoffee.com/1.0.0/button.prod.min.js';
    script.dataset.name = 'bmc-button';
    script.dataset.slug = 'leonvanleersum';
    script.dataset.color = '#FFDD00';
    script.dataset.emoji = '☕';
    script.dataset.font = 'Cookie';
    script.dataset.text = 'Buy me a coffee';
    script.dataset.outlineColor = '#000000';
    script.dataset.fontColor = '#000000';
    script.dataset.coffeeColor = '#ffffff';

    document.body.appendChild(script);

    const parallaxItems = Array.from(
        document.querySelectorAll('[data-parallax]'),
    );
    const revealItems = Array.from(document.querySelectorAll('[data-reveal]'));
    scrollHint = document.querySelector('[data-scroll-hint]');
    let ticking = false;

    const updateParallax = () => {
        const scrollY = window.scrollY || window.pageYOffset || 0;
        const viewportHeight = window.innerHeight || 1;

        parallaxItems.forEach((element) => {
            const rect = element.getBoundingClientRect();
            const progress =
                (rect.top + rect.height * 0.5 - viewportHeight * 0.5) /
                viewportHeight;
            const speed = Number(element.dataset.speed || 0.1);
            const zoom = Number(element.dataset.zoom || 0);
            const fade = Number(element.dataset.fade || 0);
            const opacityMode = element.dataset.opacity || '';
            const base = Number(element.dataset.base || 0);
            const direction = element.dataset.direction || 'up';
            const distance = Math.abs(speed) * scrollY;
            const translateY =
                direction === 'down' ? base + distance : base - distance;
            const scale = 1 + -progress * zoom;

            element.style.transform = `translate3d(0, ${translateY}px, 0) scale(${scale})`;

            if (fade) {
                const opacity = Math.min(
                    1,
                    Math.max(0, 1 - Math.abs(progress) * fade),
                );
                element.style.opacity = `${opacity}`;
            }

            if (opacityMode === 'down') {
                const opacity = Math.min(
                    1,
                    Math.max(0, 1 - scrollY / (viewportHeight * 0.6)),
                );
                element.style.opacity = `${opacity}`;
            }
        });

        if (scrollHint) {
            const hintOpacity = Math.min(
                1,
                Math.max(0, 1 - scrollY / (viewportHeight * 0.3)),
            );
            scrollHint.style.opacity = `${hintOpacity}`;
        }
        ticking = false;
    };

    onScrollHandler = () => {
        if (!ticking) {
            window.requestAnimationFrame(updateParallax);
            ticking = true;
        }
    };

    parallaxObserver = new IntersectionObserver(
        (entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                }
            });
        },
        { threshold: 0.2 },
    );

    revealItems.forEach((element) => parallaxObserver.observe(element));
    updateParallax();
    window.addEventListener('scroll', onScrollHandler, { passive: true });
    window.addEventListener('resize', onScrollHandler);

    if (!tickerLogos.value.length) {
        tickerLogos.value = shuffle(props.tickerOrganizations);
    }

    updateCarouselLayout();
    onCarouselScrollHandler = () => updateCarouselState();
    if (carousel.value) {
        carousel.value.addEventListener('scroll', onCarouselScrollHandler, {
            passive: true,
        });
    }
    onCarouselResizeHandler = () => updateCarouselLayout();
    window.addEventListener('resize', onCarouselResizeHandler);
});

onBeforeUnmount(() => {
    document.getElementById(bmcScriptId)?.remove();
    document
        .querySelectorAll('.bmc-btn-container')
        .forEach((element) => element.remove());

    if (onScrollHandler) {
        window.removeEventListener('scroll', onScrollHandler);
        window.removeEventListener('resize', onScrollHandler);
    }

    if (parallaxObserver) {
        parallaxObserver.disconnect();
    }

    if (carousel.value && onCarouselScrollHandler) {
        carousel.value.removeEventListener('scroll', onCarouselScrollHandler);
    }

    if (onCarouselResizeHandler) {
        window.removeEventListener('resize', onCarouselResizeHandler);
    }
});
</script>

<template>
    <Head title="Zookr — Platform voor bedrijfsmakelaars">
        <meta
            name="description"
            content="Zookr is een platform voor bedrijfsmakelaars waar zij zoekvragen en passend aanbod voor commercieel vastgoed centraal en efficient worden gedeeld."
        />
    </Head>

    <div class="home-page min-h-screen">

        <header class="home-header">
            <div class="logo-wrap">
                <ApplicationLogo class="home-logo" />
            </div>

            <nav v-if="canLogin" class="auth-actions">
                <Link
                    v-if="canRegister"
                    :href="route('register')"
                    class="register-link"
                >
                    Registreren
                </Link>
                <Link :href="route('login')" class="login-button">
                    Inloggen
                </Link>
            </nav>
        </header>

        <main class="home-main">
            <section class="hero-stage">
                <div class="hero-parallax">
                    <div id="parallax" class="parallax">
                        <div class="group">
                            <div
                                class="layer sun-layer"
                                data-parallax
                                data-speed="0.75"
                                data-base="0"
                                data-direction="down"
                            >
                                <svg viewBox="-72 -45 362 245" preserveAspectRatio="xMidYMid slice">
                                    <circle cx="131" cy="60" r="18"></circle>
                                </svg>
                            </div>
                            <div class="layer skyline-5" data-parallax data-speed="0.8" data-base="0" data-direction="down">
                                <svg viewBox="-72 -35 362 245" preserveAspectRatio="xMidYMid slice">
                                    <path d="M 254.26,56.2207 254.26,61.00478 253.27509,61.00478 253.27509,58.33129 252.71234,58.33129 252.71234,61.00478 252.29014,61.00478 252.29014,62.27116 250.60158,62.27116 250.60158,59.87912 250.03882,59.87912 250.03882,62.27116 243.98837,62.27116 243.98837,61.1455 243.42548,61.1455 243.42548,62.27116 241.87764,62.27116 241.87764,67.05525 238.78211,67.05525 238.78211,159.36025 209.37401,159.36025 209.37401,103.63945 185.73491,103.63945 185.73491,91.67925 179.12154,91.67925 179.12154,89.99068 173.35252,89.99068 173.35252,86.89517 172.64889,86.89517 172.64889,89.99068 168.99046,89.99068 168.99046,91.67925 160.40723,91.67925 160.40723,159.36025 147.32133,159.36025 147.32133,118.55465 137.47171,118.55465 137.47171,107.72015 131.56198,107.72015 131.56198,118.55465 128.04429,118.55465 128.04429,159.36025 93.42989,159.36025 93.42989,124.04235 72.32349,124.04235 72.32349,121.65031 67.53941,121.65031 67.53941,124.04235 56.98611,124.04235 56.98611,159.36025 9.98931,159.36025 9.98931,113.34835 5.34596,113.34835 5.34596,108.28281 1.40608,108.28281 1.40608,111.23776 -6.47369,111.23776 -6.47369,109.40863 -4.78511,109.40863 -4.78511,99.55901 -8.44353,99.55901 -8.44353,102.93599 -15.05691,102.93599 -15.05691,100.12177 -17.30823,100.12177 -17.30823,91.81998 -23.78088,91.81998 -23.78088,88.16156 -26.87639,88.16156 -26.87639,76.76406 -27.580033,76.76406 -27.580033,88.16156 -29.690613,88.16156 -29.690613,91.81998 -36.022533,91.81998 -36.022533,97.44826 -38.414573,97.44826 -38.414573,102.93599 -41.369363,102.93599 -41.369363,130.41929 -41.403103,130.41929 -41.403103,159.36019 -55.721803,159.36019 -55.721803,132.62559 -74.858103,132.62559 -74.858103,169.02389 -75.331253,169.02389 -75.331253,214.49359 -51.452153,214.49359 -51.452153,214.3775 372.45585,214.3775 372.45585,159.3602 333.47945,159.3602 333.47945,88.0208 319.88345,88.0208 320.11211,86.1915 318.14224,84.64383 309.84045,84.784389 306.0413,88.020799 306.0413,159.3602 297.03587,159.3602 297.03587,123.7609 280.85427,123.7609 280.85427,159.3602 268.19047,159.3602 268.19047,67.055199 264.10987,67.055199 264.10987,62.271109 261.57727,62.271109 261.57727,61.145449 259.04451,61.145449 259.04451,62.271109 255.24533,62.271109 255.24533,61.004729 254.96388,61.004729 254.96388,56.220649 254.26023,56.220649 Z"></path>
                                </svg>
                            </div>
                            <div class="layer skyline-4" data-parallax data-speed="0.52" data-base="0" data-direction="down">
                                <svg viewBox="-72 -35 362 245" preserveAspectRatio="xMidYMid slice">
                                    <path d="M 212.565,55.2 A 8.25819,9.15366 0 0 0 204.30693,64.35374 8.25819,9.15366 0 0 0 204.43433,65.94562 L 204.30676,65.94562 204.30676,149.12452 188.38746,149.12452 188.38746,126.43932 150.97686,126.43932 150.97686,149.12452 135.85346,149.12452 135.85346,55.39912 101.62666,55.39912 101.62666,108.92812 88.29426,108.92812 88.29426,111.5149 80.13557,111.5149 80.13557,149.1246 66.40497,149.1246 66.40497,135.3942 39.93897,135.3942 39.93897,129.82227 24.01967,129.82227 24.01967,135.3942 2.32947,135.3942 2.32947,143.15483 -3.04334,143.15483 -3.04334,108.33123 -25.92754,108.33123 -25.92754,103.15736 -35.47917,103.15736 -35.47917,108.33123 -36.474047,108.33123 -36.474047,124.44963 -56.771247,124.44963 -56.771247,135.79203 -75.476647,135.79203 -75.476647,176.38653 -75.377107,176.38653 -75.377107,214.49363 -59.756307,214.49363 -59.756307,214.39413 372.25669,214.39413 372.25669,214.1952 372.45559,214.1952 372.45559,146.5377 368.47573,146.5377 368.47573,122.2606 368.07784,122.2606 368.07784,111.5605 372.13249,111.5605 A 22.8652,14.0005 0 0 0 362.20846,110.15339 22.8652,14.0005 0 0 0 343.39256,116.20384 L 345.98961,116.20384 345.98961,122.26056 341.41277,122.26056 341.41277,127.23549 332.45813,127.23549 332.45813,149.12459 285.29693,149.12459 285.29693,102.75939 279.92414,102.75939 279.92414,100.96839 260.22384,100.96839 260.22384,102.75939 254.0551,102.75939 254.0551,149.12459 241.9165,149.12459 241.9165,70.52249 241.50461,70.52249 A 4.96914,9.04849 0 0 0 241.51261,70.223994 4.96914,9.04849 0 0 0 236.5434,61.175544 4.96914,9.04849 0 0 0 236.14535,61.208434 L 236.14535,55.199984 213.06225,55.199984 213.06225,55.227684 A 8.25819,9.15366 0 0 0 212.56481,55.199984 Z"></path>
                                </svg>
                            </div>
                            <div class="layer skyline-3" data-parallax data-speed="0.32" data-base="0" data-direction="down">
                                <svg viewBox="-72 -35 362 245" preserveAspectRatio="xMidYMid slice">
                                    <path d="M 152.762,91.3174 152.762,97.0881 149.38364,97.0881 149.38364,91.71528 147.99256,91.71528 147.99256,97.0881 146.99899,97.0881 146.99899,98.67998 141.43465,98.67998 141.43465,160.56678 129.70965,160.56678 129.70965,113.60438 95.52855,113.60438 95.52855,123.55406 75.85455,123.55406 75.85455,145.44316 57.17405,145.44316 57.17405,140.86646 54.39189,140.86646 54.39189,135.29454 53.887523,135.29454 A 4.07391,7.16373 0 0 0 50.814833,129.90367 L 50.814833,125.94201 50.698057,125.94201 49.813192,123.76089 49.132564,125.94201 49.026231,125.94201 49.026231,129.9067 A 4.07391,7.16373 0 0 0 45.952191,135.29454 L 44.852971,135.29454 44.852971,140.86646 40.083511,140.86646 40.083511,145.44316 21.800611,145.44316 21.800611,162.55646 4.113911,162.55646 4.113911,140.46836 -18.143589,140.46836 -18.143589,162.55646 -30.265989,162.55646 -30.265989,140.07036 -74.979689,140.07036 -74.979689,214.39406 -75.377048,214.39406 -75.377048,214.49356 372.45595,214.49356 372.45595,214.41286 372.42335,214.41286 372.42335,145.64216 360.82995,145.64216 360.82995,148.22892 337.38015,148.22892 337.38015,142.45823 329.03348,142.45823 329.03348,148.22892 326.05261,148.22892 326.05261,139.87129 312.73781,139.87129 312.73781,136.28948 302.00651,136.28948 302.00651,139.87129 289.28811,139.87129 289.28811,168.32719 272.39621,168.32719 272.39621,145.04509 232.45201,145.04509 232.45201,154.9946 224.50288,154.9946 224.50288,146.83592 224.10553,146.83592 224.6023,146.63697 221.32319,138.57784 221.20843,138.93573 221.12453,138.67723 218.66118,146.83592 218.54102,146.83592 218.54102,154.9946 215.75887,154.9946 215.75887,143.2541 207.01484,143.2541 207.01484,138.47829 201.05314,138.47829 201.05314,109.22639 173.62864,109.22639 173.62864,147.23399 161.10884,147.23399 161.10884,98.67979 154.94829,98.67979 154.94829,97.08791 153.95457,97.08791 153.95457,91.31721 152.76216,91.31721 Z"></path>
                                </svg>
                            </div>
                            <div class="layer skyline-2" data-parallax data-speed="0.2" data-base="0" data-direction="down">
                                <svg viewBox="-72 -35 362 245" preserveAspectRatio="xMidYMid slice">
                                    <path d="M 194.622,131.148 C 194.622,131.148 193.59002,139.75614 193.41659,142.6158 L 190.07533,142.6158 C 189.49755,138.95259 188.4363,133.1882 188.4363,133.1882 L 188.4363,133.68074 187.06787,143.95254 186.74923,143.95254 186.74923,180.11464 184.78096,180.11464 184.78096,150.77684 151.46256,150.77684 151.46256,174.83814 147.24514,174.83814 147.24514,149.08844 141.6217,149.08844 141.6217,145.14857 131.4998,145.14857 131.4998,149.08844 122.78358,149.08844 122.78358,153.02816 111.25568,153.02816 111.25568,199.32146 92.97978,199.32146 92.97978,144.02286 62.89478,144.02286 62.89478,166.95836 47.85228,166.95836 47.85228,153.73176 26.62428,153.73176 26.62428,166.95836 24.09376,166.95836 24.09376,144.44486 -6.97534,144.44486 -6.97534,203.15076 -8.38121,203.15076 -8.38121,137.83166 -13.72345,137.83166 -13.72345,134.73598 -32.42115,134.73598 -32.42115,137.83166 -40.57495,137.83166 -40.57495,203.15076 -51.11885,203.15076 -51.11885,149.36966 -60.95956,149.36966 -60.95956,143.03774 -71.92526,143.03774 -71.92526,149.36966 -73.47156,149.36966 -73.47156,151.48042 -75.15863,151.48042 -75.15863,214.49342 372.45537,214.49342 372.45537,211.11054 365.57208,211.11054 365.57208,141.34934 343.92228,141.34934 343.92228,146.41488 308.49498,146.41488 308.49498,142.61572 300.763,142.61572 300.763,146.41488 298.79469,146.41488 298.79469,209.91648 291.90617,209.91648 291.90617,133.46958 262.10237,133.46958 262.10237,206.42718 205.72827,206.42718 205.72827,180.11458 197.71491,180.11458 197.71491,143.95248 197.64461,143.95248 197.64461,143.03775 197.39392,143.03775 194.62215,131.14795 Z"></path>
                                </svg>
                            </div>
                            <div class="layer skyline-1">
                                <svg viewBox="-72 -35 362 245" preserveAspectRatio="xMidYMid slice">
                                    <path d="M 88.9271,169.291 88.9271,171.54231 82.73591,171.54231 82.73591,172.38668 71.61991,172.38668 71.61991,174.63799 53.32781,174.63799 53.32781,180.96992 22.79401,180.96992 22.79401,199.68422 8.58231,199.68422 8.58231,193.63376 3.93896,193.63376 3.93896,189.97534 -8.72484,189.97534 -8.72484,184.06543 -24.90644,184.06543 -24.90644,189.97534 -33.91185,189.97534 -33.91185,172.52744 -75.43985,172.52744 -75.43985,214.49414 -68.80571,214.49414 -33.91191,214.49414 -22.51441,214.49414 -15.47884,214.49414 372.42816,214.49414 372.42816,210.09664 372.45526,210.09664 372.45526,180.54774 317.57866,180.54774 317.57866,183.78398 305.05556,183.78398 305.05556,191.24155 285.21556,191.24155 285.21556,202.77975 267.48636,202.77975 267.48636,192.78941 218.66026,192.78941 218.66026,202.21701 208.52916,202.21701 208.52916,191.80451 195.72476,191.80451 195.72476,182.09562 181.79456,182.09562 181.79456,191.80451 164.34666,191.80451 164.34666,184.90985 132.82796,184.90985 132.82796,180.68851 97.65066,180.68851 97.65066,171.54236 91.74093,171.54236 91.74093,169.29105 88.9267,169.29105 Z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="hero-stage-inner"
                    data-parallax
                    data-speed="0.8"
                    data-base="0"
                    data-direction="down"
                    data-opacity="down"
                >
                    <section class="hero">
                        <div class="content" data-reveal>
                            <h1>
                                Het platform voor zoekvragen en aanbod in commercieel
                                vastgoed.
                            </h1>
                            <p>
                                Zookr is een platform voor bedrijfsmakelaars waar
                                zoekvragen en passend aanbod voor commercieel vastgoed
                                centraal en efficient worden gematchd.
                            </p>
                        </div>
                    </section>
                </div>
                <div class="scroll-hint" data-scroll-hint aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" role="img">
                        <path d="M5 15C5 16.8565 5.73754 18.6371 7.05029 19.9498C8.36305 21.2626 10.1435 21.9999 12 21.9999C13.8565 21.9999 15.637 21.2626 16.9498 19.9498C18.2625 18.6371 19 16.8565 19 15V9C19 7.14348 18.2625 5.36305 16.9498 4.05029C15.637 2.73754 13.8565 2 12 2C10.1435 2 8.36305 2.73754 7.05029 4.05029C5.73754 5.36305 5 7.14348 5 9V15Z" stroke="currentColor" stroke-width="0.648" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M12 6V14" stroke="currentColor" stroke-width="0.648" stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M15 11L12 14L9 11" stroke="currentColor" stroke-width="0.648" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </div>
            </section>

            <section class="stats" data-reveal>
                <div class="stat">
                    <p class="stat-value">5x</p>
                    <p class="stat-label">sneller overzicht</p>
                </div>
                <div class="stat">
                    <p class="stat-value">37%</p>
                    <p class="stat-label">meer matchkansen</p>
                </div>
                <div class="stat">
                    <p class="stat-value">4.8x</p>
                    <p class="stat-label">snellere follow-up</p>
                </div>
            </section>

            <section class="value" data-reveal>
                <div class="value-header">
                    <h2>Versnel de transactie. Krijg real time inzicht.</h2>
                </div>
                <div class="value-grid">
                    <div class="value-card" data-reveal>
                        <p class="card-title">Goede fit</p>
                        <p class="card-text">
                            Match sneller met actuele zoekvragen en
                            beschikbaar aanbod.
                        </p>
                    </div>
                    <div class="value-card" data-reveal>
                        <p class="card-title">Gemak dient de makelaar</p>
                        <p class="card-text">
                            Geen tijd meer verspillen aan handmatige mailings
                            uitsturen en presentaties knippen en plakken. Zookr
                            stuurt jouw zoekvraag in een handomdraai naar alle
                            relevante makelaars in jouw zoekgebied en type
                            vastgoed.
                        </p>
                    </div>
                    <div class="value-card" data-reveal>
                        <p class="card-title">Blije opdrachtgevers</p>
                        <p class="card-text">
                            Krijgt al het passende aanbod eenduidig
                            gepresenteerd, zodat je dit makkelijk online kan
                            delen met jouw opdrachtgever. Ontvang snellere
                            feedback voor jouw verhuurder/verkoper
                        </p>
                    </div>
                </div>
            </section>

            <section class="search-requests" data-reveal>
                <div class="section-header">
                    <h2>Actuele zoekvragen.</h2>
                </div>
                <div ref="carouselShell" class="carousel-shell">
                    <button
                        type="button"
                        class="carousel-arrow"
                        aria-label="Vorige zoekvraag"
                        :disabled="!canScrollPrev"
                        @click="scrollCarousel(-1)"
                    >
                        <svg
                            class="w-[42px] h-[42px] text-gray-800 dark:text-white"
                            aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1"
                                d="m14 8-4 4 4 4"
                            />
                        </svg>
                    </button>
                    <div class="carousel-viewport">
                        <div ref="carousel" class="carousel-scroll">
                            <div class="carousel-track">
                            <Link
                                v-for="item in props.searchRequests"
                                :key="item.id"
                                :href="route('search-requests.show', item.id)"
                                class="request-card-link"
                            >
                                <article class="request-card">
                                    <div class="card-logo">
                                        <img
                                            v-if="item.organization?.logo_url"
                                            :src="item.organization.logo_url"
                                            :alt="item.organization?.name || 'Logo'"
                                            loading="lazy"
                                        />
                                    </div>
                                    <p class="card-title">{{ item.title }}</p>
                                    <p class="card-text card-badges">
                                        <span
                                            class="bg-neutral-secondary-medium border border-default-medium text-heading text-xs font-medium px-1.5 py-0.5 rounded"
                                        >
                                            {{ formatLabel(item.property_type) }}
                                        </span>
                                        <span
                                            v-for="acquisition in item.acquisitions || []"
                                            :key="`${item.id}-${acquisition}`"
                                            class="bg-neutral-secondary-medium border border-default-medium text-heading text-xs font-medium px-1.5 py-0.5 rounded"
                                        >
                                            {{ acquisitionLabel(acquisition) }}
                                        </span>
                                    </p>
                                    <p class="card-meta">
                                        {{ formatLocation(item) }}
                                        <span v-if="item.surface_area">
                                            · {{ item.surface_area }}
                                        </span>
                                    </p>
                                    <div class="card-readmore">
                                        Lees verder...
                                    </div>
                                    <div class="card-contact">
                                        <div class="card-avatar">
                                            <img
                                                v-if="item.contact?.avatar_url"
                                                :src="item.contact.avatar_url"
                                                :alt="item.contact?.name || 'Contact'"
                                                loading="lazy"
                                            />
                                            <span
                                                v-else
                                                class="card-avatar-fallback"
                                                aria-hidden="true"
                                            >
                                                {{ initialsFor(item.contact?.name) }}
                                            </span>
                                        </div>
                                        <span class="card-contact-name">
                                            {{ item.contact?.name || "-" }}
                                        </span>
                                    </div>
                                </article>
                            </Link>
                            <p
                                v-if="props.searchRequests.length === 0"
                                class="card-meta"
                            >
                                Geen zoekvragen beschikbaar.
                            </p>
                            </div>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="carousel-arrow"
                        aria-label="Volgende zoekvraag"
                        :disabled="!canScrollNext"
                        @click="scrollCarousel(1)"
                    >
                        <svg
                            class="w-[42px] h-[42px] text-gray-800 dark:text-white"
                            aria-hidden="true"
                            xmlns="http://www.w3.org/2000/svg"
                            width="24"
                            height="24"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            <path
                                stroke="currentColor"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1"
                                d="m10 16 4-4-4-4"
                            />
                        </svg>
                    </button>
                </div>
            </section>

            <section class="reach" data-reveal>
                <div class="section-header">
                    <h2>Bereik alle actieve bedrijfsmakelaars van Nederland</h2>
                    <p>
                        Zoekvragen worden automatisch gematched aan Nederlandse
                        makelaars die actief zijn in het zoekgebied en de juiste
                        specialisatie.
                    </p>
                </div>
                <div class="ticker" aria-hidden="true">
                    <div class="ticker-track">
                        <img
                            v-for="organization in tickerLogos"
                            :key="`ticker-${organization.id}`"
                            class="ticker-logo"
                            :src="organization.logo_url"
                            :alt="organization.name"
                            loading="lazy"
                        />
                        <img
                            v-for="organization in tickerLogos"
                            :key="`ticker-dup-${organization.id}`"
                            class="ticker-logo"
                            :src="organization.logo_url"
                            :alt="organization.name"
                            loading="lazy"
                        />
                    </div>
                </div>
            </section>

            <section class="about-zookr" data-reveal>
                <div class="section-header">
                    <h2>Over Zookr</h2>
                </div>
                <div class="about-content">
                    <div class="about-photo">
                        <img src="/images/PaulLeon.png" alt="Leon van Leersum en Paul" />
                    </div>
                    <div class="about-text">
                        <p>
                            Na eerdere successen met Vendr, RealMarchr en
                            myLeaseAdmin, was het tijd om het aanhuurproces
                            drastisch te verbeteren door de inzet van
                            automatisering. Aanhuren anno 2025 gebeurde nog
                            grotendeels op basis van heel veel handmatig knip-
                            en plakwerk van inventarisaties. Mailings naar
                            makelaars, waarvan veel ongeopend retour kwamen door
                            de wisselingen van banen of afwezigheid door
                            vakantie. Vervolgens alle informatie die in de
                            meest uiteenlopende formats werd toegestuurd moest
                            je een mooi boekje maken voor de klant met foto's,
                            plattegronden, kaartjes en teksten. Een tijdvretende
                            bezigheid - wat eigenlijk gewoon dom werk is en
                            prima geautomatiseerd kan.
                        </p>
                        <p>
                            Met Vendr brengen we niet alleen snelheid en logica
                            in het aanhuurproces, maar brengen we onze
                            jarenlange expertise op het gebied van
                            aanhuur/aankoop in. Omdat we denken dat iedereen
                            profiteert van betere vastgoedprocessen, hebben we
                            het gebruik van de basisfuncties van Zookr
                            <strong>HELEMAAL GRATIS</strong> gemaakt. Misschien
                            dat we nog uit gaan breiden met een Pro-versie,
                            waarbij je onder andere uitgebreide analyses kunt
                            maken. Voor nu: veel plezier ermee. We leveren geen
                            uitgebreide support, maar dat is ook eigenlijk niet
                            nodig, want we hebben het weer kinderlijk eenvoudig
                            gemaakt.
                        </p>
                        <p>
                            Met vriendelijke groet,<br />
                            Leon van Leersum FRICS
                        </p>
                        <p>
                            P.S.: Als je ons initiatief waardeert kan je me met
                            twee dingen een plezier doen: Feedback geven via
                            leon@zookr.nl of gewoon een kop koffie voor me kopen
                            ;-)
                        </p>
                        <a
                            class="about-bmc"
                            href="https://www.buymeacoffee.com/leonvanleersum"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            <img
                                src="https://cdn.buymeacoffee.com/buttons/v2/default-yellow.png"
                                alt="Buy Me A Coffee"
                                @error="(event) => { event.target.src = '/images/buymeacoffee.png'; }"
                            />
                        </a>
                    </div>
                </div>
            </section>

            <section class="app-download" data-reveal>
                <div class="section-header">
                    <h2>Download de app op je favoriete mobiele device</h2>
                </div>
                <div class="store-logos">
                    <a href="https://apps.apple.com/" target="_blank" rel="noopener noreferrer">
                        <picture>
                            <source
                                srcset="/images/appstore-light.svg"
                                media="(prefers-color-scheme: dark)"
                            />
                            <img
                                src="/images/appstore-dark.svg"
                                alt="Download in de App Store"
                            />
                        </picture>
                    </a>
                    <a href="https://play.google.com/store" target="_blank" rel="noopener noreferrer">
                        <picture>
                            <source
                                srcset="/images/googleplay-light.svg"
                                media="(prefers-color-scheme: dark)"
                            />
                            <img
                                src="/images/googleplay-dark.svg"
                                alt="Download via Google Play"
                            />
                        </picture>
                    </a>
                </div>
            </section>

            <section class="other-solutions" data-reveal>
                <div class="section-header">
                    <h2>Andere software voor makelaars van RESAAS</h2>
                </div>
                <div class="value-grid other-solutions-grid">
                    <a
                        class="value-card solution-card"
                        href="https://about.vendr.nl/"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <div class="solution-logo">
                            <img class="vendr-logo" src="/images/Vendr.svg" alt="Vendr" />
                        </div>
                        <p class="solution-text">
                            Vendr digitaliseert het verkoopproces van commercieel
                            vastgoed.
                        </p>
                    </a>
                    <a
                        class="value-card solution-card"
                        href="https://www.realmatchr.com"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <div class="solution-logo">
                            <img src="/images/LogoRM-Blue.svg" alt="Real Matchr" />
                        </div>
                        <p class="solution-text">
                            Software om het aankoopproces van beleggingsmakelaars
                            te optimaliseren.
                        </p>
                    </a>
                    <a
                        class="value-card solution-card"
                        href="https://www.myleaseadmin.com"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <div class="solution-logo">
                            <img src="/images/myLeaseAdmin.svg" alt="myLeaseAdmin" />
                        </div>
                        <p class="solution-text">
                            Eenvoudige oplossing om huurcontracten te beheren en
                            tijdig geinformeerd te worden voor belangrijke
                            evenementen.
                        </p>
                    </a>
                </div>
            </section>
        </main>
    </div>
</template>


<style>
.home-page {
    --bg: #ffffff;
    --text: #000000;
    --muted: rgba(0, 0, 0, 0.66);
    --border: rgba(0, 0, 0, 0.14);
    --peach: #f3d6b2;
    --gold: #f0c68a;
    --sage: #c6d2c2;
    --sky: #f9f3eb;
    --pad-left: clamp(36px, 8vw, 160px);
    --pad-right: clamp(20px, 4vw, 72px);
    --measure: 74ch;
    --h1-size: clamp(36px, 5vw, 68px);
    --h1-line: 1.06;
    --h1-track: -0.035em;
    background: #f7f7f7;
    color: var(--text);
    font-family: "Figtree";
    font-size: 17px;
    position: relative;
    overflow-x: hidden;
    min-height: 500vh;
}

.hero-stage {
    position: relative;
    min-height: 100vh;
    overflow: hidden;
}

.hero-stage-inner {
    position: relative;
    z-index: 1;
    min-height: 100vh;
    display: flex;
    align-items: flex-start;
    padding: max(20vh, 120px) var(--pad-right) 80px var(--pad-left);
}

.scroll-hint {
    position: fixed;
    left: 50%;
    bottom: 24px;
    transform: translateX(-50%);
    width: 68px;
    height: 68px;
    color: rgba(0, 0, 0, 0.6);
    transition: opacity 0.2s ease;
    pointer-events: none;
    z-index: 4;
}

.scroll-hint svg {
    display: block;
    width: 100%;
    height: 100%;
}

.hero-parallax {
    position: absolute;
    inset: 0;
    height: 100%;
    overflow: hidden;
    z-index: 2;
    pointer-events: none;
}

.parallax {
    height: 100vh;
    overflow: hidden;
}

.parallax .group {
    position: relative;
    height: 100vh;
    transform-style: preserve-3d;
}

.parallax .layer {
    position: absolute;
    inset: auto 0 0 0;
    height: 60vh;
    width: 100%;
    overflow: hidden;
    will-change: transform;
    z-index: 1;
}

.parallax .layer svg {
    width: 100%;
    height: 100%;
    display: block;
}

.parallax .windows {
    fill: rgba(0, 0, 0, 0.1);
}

.sun-layer {
    inset: 0 0 auto 0;
    height: 45vh;
    opacity: 0.5;
    z-index: 1;
}

.sun-layer circle {
    fill: #f5d6a1;
}

.skyline-5 {
    fill: #d9d9d9;
    z-index: 2;
}

.skyline-4 {
    fill: #e2e2e2;
    z-index: 3;
}

.skyline-3 {
    fill: #e9e9e9;
    z-index: 4;
}

.skyline-2 {
    fill: #f1f1f1;
    z-index: 5;
}

.skyline-1 {
    fill: #f7f7f7;
    height: 60vh;
    position: relative;
    z-index: 6;
    transform: translate3d(0, 0, 0);
}

.skyline-1 svg {
    position: relative;
    z-index: 2;
}

.skyline-1::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: -40vh;
    height: 40vh;
    background: #f7f7f7;
    z-index: 1;
}

.home-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 24px;
    padding: 34px var(--pad-right) 18px var(--pad-left);
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 3;
}

.logo-wrap {
    display: inline-flex;
    line-height: 1;
    user-select: none;
}

.home-logo {
    height: clamp(68px, 7.5vw, 104px);
    width: auto;
    display: block;
    transform: translateY(4px);
    transform-origin: left top;
    color: var(--text);
}

.auth-actions {
    display: inline-flex;
    align-items: center;
    gap: 16px;
    margin-top: 8px;
    font-weight: 600;
}

.register-link {
    color: var(--text);
    text-decoration: none;
    font-size: 14px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
}

.register-link:hover {
    text-decoration: underline;
}

.login-button {
    display: inline-flex;
    align-items: center;
    padding: 12px 22px;
    border: 1px solid var(--text);
    background: var(--text);
    color: var(--bg);
    text-decoration: none;
    font-weight: 700;
    letter-spacing: 0.02em;
    transition: transform 0.08s ease, box-shadow 0.18s ease;
}

.login-button:hover {
    transform: translateY(-1px);
    box-shadow: 0 8px 18px rgba(0, 0, 0, 0.18);
}

.home-main {
    display: flex;
    flex-direction: column;
    gap: 96px;
    padding: 0 0 140px;
    position: relative;
    z-index: 2;
}

.home-main > section:not(.hero-stage) {
    padding: 0 var(--pad-right) 0 var(--pad-left);
}

.home-main > section.search-requests {
    padding: 0;
}

.search-requests .section-header {
    padding: 0 var(--pad-right) 0 var(--pad-left);
}

.content {
    max-width: var(--measure);
}

.content h1 {
    font-size: var(--h1-size);
    line-height: var(--h1-line);
    letter-spacing: var(--h1-track);
    margin: 0 0 28px 0;
    font-weight: 800;
}

.content p {
    font-size: 19px;
    margin: 0 0 24px 0;
    color: var(--muted);
    font-weight: 500;
}

.hero {
    display: grid;
    gap: 48px;
    grid-template-columns: minmax(0, 1.3fr) minmax(0, 0.7fr);
    align-items: center;
}

.eyebrow {
    font-size: 12px;
    letter-spacing: 0.36em;
    text-transform: uppercase;
    color: rgba(0, 0, 0, 0.4);
    margin-bottom: 18px;
}

.hero-card {
    background: #ffffff;
    border-radius: 28px;
    border: 1px solid rgba(0, 0, 0, 0.08);
    padding: 28px;
    box-shadow: 0 28px 60px rgba(0, 0, 0, 0.12);
    display: grid;
    gap: 24px;
    position: relative;
    overflow: hidden;
    will-change: transform, opacity;
}

.hero-card::after {
    content: "";
    position: absolute;
    inset: auto -10% -40% -10%;
    height: 70%;
    background: radial-gradient(circle, rgba(248, 192, 140, 0.6), transparent 70%);
    opacity: 0.7;
}

.hero-card-top {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.22em;
    color: rgba(0, 0, 0, 0.5);
    position: relative;
    z-index: 1;
}

.pill {
    background: rgba(0, 0, 0, 0.08);
    padding: 4px 10px;
    border-radius: 999px;
    font-weight: 600;
}

.hero-card-body {
    display: grid;
    gap: 16px;
    position: relative;
    z-index: 1;
}

.hero-card-metric strong {
    display: block;
    font-size: 26px;
    font-weight: 750;
}

.hero-card-metric span {
    color: rgba(0, 0, 0, 0.56);
    font-size: 14px;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 24px;
    padding-top: 32px;
}

.stat-value {
    font-size: 26px;
    font-weight: 700;
}

.stat-label {
    color: var(--muted);
    font-size: 15px;
    margin-top: 8px;
}

.value-header {
    text-align: center;
}

.value-header h2 {
    font-size: 30px;
    margin: 0 0 28px;
    font-weight: 700;
}

.value-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.value-card {
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 20px;
    padding: 22px;
    background: #fff;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
    text-align: center;
}

.card-title {
    font-weight: 700;
    margin-bottom: 8px;
    min-height: 2.6em;
    font-size: 17px;
}

.card-text {
    color: var(--muted);
    font-size: 16px;
    min-height: 2.4em;
}


.section-header {
    text-align: center;
}

.section-header h2 {
    font-size: 30px;
    margin: 0 0 28px;
    font-weight: 700;
}

.section-header p {
    color: var(--muted);
    margin: 0 auto;
    max-width: 68ch;
    font-size: 17px;
}

.search-requests .carousel-scroll {
    --card-width: 320px;
    --card-gap: 18px;
    margin-top: 24px;
    overflow-x: auto;
    overflow-y: hidden;
    scroll-snap-type: x mandatory;
    padding: 32px 26px 52px;
    margin-left: auto;
    margin-right: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.search-requests .carousel-scroll::-webkit-scrollbar {
    display: none;
}

.carousel-viewport {
    overflow: hidden;
}

.carousel-shell {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin: 0 auto;
}

.carousel-arrow {
    border: none;
    background: transparent;
    padding: 0;
    cursor: pointer;
    line-height: 0;
}

.carousel-arrow:disabled {
    opacity: 0;
    pointer-events: none;
    visibility: hidden;
}

.carousel-track {
    display: flex;
    gap: var(--card-gap);
    min-width: max-content;
    padding: 2px;
    justify-content: center;
    align-items: stretch;
}

.request-card {
    background: #fff;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 20px;
    padding: 22px;
    width: var(--card-width);
    flex: 0 0 var(--card-width);
    box-shadow: 16px 16px 20px rgba(0, 0, 0, 0.08);
    transition: box-shadow 0.2s ease, transform 0.2s ease;
    scroll-snap-align: start;
    text-align: left;
    display: flex;
    flex-direction: column;
    min-height: 270px;
    gap: 0;
    height: 100%;
}

.request-card-link {
    text-decoration: none;
    color: inherit;
    display: flex;
    cursor: pointer;
    align-items: stretch;
}

.request-card:hover {
    box-shadow: 16px 16px 20px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.card-logo {
    height: 42px;
    display: flex;
    align-items: center;
    margin-bottom: 16px;
}

.card-logo img {
    height: 100%;
    width: auto;
    display: block;
    object-fit: contain;
}

.card-meta {
    color: rgba(0, 0, 0, 0.5);
    font-size: 14px;
    min-height: 1.6em;
}

.request-card .card-title {
    margin: 0;
    min-height: 2.8em;
}

.request-card .card-text {
    margin: 2px 0 0;
    min-height: 2.6em;
}

.card-badges {
    display: flex;
    flex-wrap: nowrap;
    gap: 6px;
    align-items: center;
    line-height: 1;
}

.request-card .card-meta {
    margin: 10px 0 0;
}

.card-readmore {
    margin-top: 8px;
    margin-left: auto;
    font-size: 13px;
    color: rgba(0, 0, 0, 0.5);
    font-weight: 600;
}

.card-contact {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: auto;
    padding-top: 16px;
}

.card-avatar {
    width: 32px;
    height: 32px;
    border-radius: 999px;
    overflow: hidden;
    background: rgba(0, 0, 0, 0.08);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.card-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.card-avatar-fallback {
    font-size: 12px;
    font-weight: 600;
    color: rgba(0, 0, 0, 0.6);
}

.card-contact-name {
    font-size: 14px;
    color: rgba(0, 0, 0, 0.7);
    font-weight: 600;
}

.ticker {
    margin-top: 28px;
    overflow: hidden;
    border-radius: 0;
    border: none;
    background: transparent;
    -webkit-mask-image: linear-gradient(
        to right,
        transparent 0%,
        #000 12%,
        #000 88%,
        transparent 100%
    );
    mask-image: linear-gradient(
        to right,
        transparent 0%,
        #000 12%,
        #000 88%,
        transparent 100%
    );
}

.ticker-track {
    display: inline-flex;
    gap: 72px;
    padding: 14px 40px;
    white-space: nowrap;
    animation: ticker-scroll 20s linear infinite;
}

.ticker-item {
    font-size: 14px;
    font-weight: 600;
    color: rgba(0, 0, 0, 0.6);
}

.ticker-logo {
    height: 58px;
    max-width: 120px;
    width: auto;
    display: block;
    object-fit: contain;
}

.app-download .store-logos {
    margin-top: 24px;
    background: transparent;
    border-radius: 0;
    padding: 22px var(--pad-right);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 16px;
    text-align: center;
    width: 100%;
    border: none;
}

.app-download .store-logos img {
    display: block;
    height: 44px;
    width: auto;
    max-width: 44vw;
}

 .other-solutions-grid {
    margin-top: 28px;
}

.solution-card {
    text-decoration: none;
    color: inherit;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
    text-align: center;
    min-height: 220px;
    transition: box-shadow 0.2s ease, transform 0.2s ease;
}

.solution-card:hover {
    box-shadow: 16px 16px 20px rgba(0, 0, 0, 0.08);
    transform: translateY(-2px);
}

.solution-logo {
    height: 84px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: visible;
    padding-top: 8px;
    padding-bottom: 16px;
}

.solution-logo img {
    height: 52px;
    width: auto;
    max-width: 180px;
    display: block;
    object-fit: contain;
}

.solution-logo img.vendr-logo {
    height: 50px;
    padding-bottom: 6px;
    box-sizing: content-box;
    overflow: visible;
}

.solution-text {
    font-size: 16px;
    color: var(--muted);
}

.about-zookr .section-header {
    text-align: center;
}

.about-content {
    display: flex;
    flex-wrap: nowrap;
    gap: 32px;
    align-items: flex-start;
    justify-content: center;
    border: 1px solid rgba(0, 0, 0, 0.08);
    border-radius: 20px;
    padding: 24px;
    background: #fff;
}

.about-photo {
    display: flex;
    align-items: center;
    justify-content: center;
    flex: 0 0 40%;
}

.about-photo img {
    max-width: 100%;
    height: auto;
    display: block;
}

.about-text {
    font-size: 17px;
    color: var(--text);
    display: flex;
    flex-direction: column;
    gap: 18px;
    flex: 1 1 60%;
}

.about-bmc {
    display: inline-flex;
    align-items: center;
}

.about-bmc img {
    height: 60px;
    width: 217px;
    display: block;
}

.about-text p {
    margin: 0;
    color: var(--muted);
}

.about-text strong {
    color: var(--text);
}

[data-reveal] {
    opacity: 0;
    transform: translateY(18px);
    transition: opacity 0.8s ease, transform 0.8s ease;
}

[data-reveal].is-visible {
    opacity: 1;
    transform: translateY(0);
}

@keyframes ticker-scroll {
    from {
        transform: translateX(0);
    }
    to {
        transform: translateX(-50%);
    }
}


@media (prefers-color-scheme: dark) {
    .app-download .store-logos {
        background: #ffffff;
    }
}

@media (max-width: 768px) {
    .home-header {
        padding: 22px 20px 12px 20px;
    }

    .hero-stage-inner {
        padding: 25vh 20px 40px;
        align-items: flex-start;
    }

    .hero {
        grid-template-columns: 1fr;
    }

    .stats,
    .value-grid {
        grid-template-columns: 1fr;
    }


    .home-logo {
        height: 70px;
        transform: translateY(2px);
    }

    .content p {
        font-size: 16px;
    }

    .about-content {
        flex-direction: column;
        align-items: center;
    }

    .about-photo {
        width: 100%;
        flex: 1 1 100%;
    }

    .about-text {
        text-align: left;
    }
}
</style>

