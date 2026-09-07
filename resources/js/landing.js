/**
 * Landing page — scroll-reveal entrance animations.
 *
 * Elements marked `data-reveal` start slightly down + transparent (see
 * landing/page.css) and animate into place once they scroll into view.
 * Kept deliberately light: one IntersectionObserver, fires once per
 * element, and bows out entirely when the visitor prefers reduced motion
 * (the CSS leaves those elements fully visible, so nothing is hidden).
 */
function initReveal() {
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    const items = Array.from(document.querySelectorAll('[data-reveal]'));
    if (!items.length) return;

    // No observer support or motion turned off → show everything immediately.
    if (reduceMotion || !('IntersectionObserver' in window)) {
        items.forEach((el) => el.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    obs.unobserve(entry.target);
                }
            });
        },
        { rootMargin: '0px 0px -10% 0px', threshold: 0.1 },
    );

    items.forEach((el) => observer.observe(el));
}

/**
 * Count-up — numbers tagged `data-countup` tick from 0 to their value the
 * first time they scroll into view. Non-numeric values (e.g. "Zero") are
 * left untouched. Skipped entirely under reduced-motion.
 */
function animateCount(el) {
    const raw = el.textContent.trim();
    const match = raw.match(/^(\d[\d,]*(?:\.\d+)?)(.*)$/);
    if (!match) return; // not a number — leave it alone

    const clean = match[1].replace(/,/g, '');
    const target = parseFloat(clean);
    const suffix = match[2] || '';
    const decimals = (clean.split('.')[1] || '').length;
    const duration = 1100;
    let start = null;

    el.textContent = '0' + suffix;
    function tick(ts) {
        if (start === null) start = ts;
        const p = Math.min(1, (ts - start) / duration);
        const eased = 1 - Math.pow(1 - p, 3); // ease-out
        if (p < 1) {
            el.textContent = (target * eased).toFixed(decimals) + suffix;
            requestAnimationFrame(tick);
        } else {
            el.textContent = raw; // land exactly on the original text
        }
    }
    requestAnimationFrame(tick);
}

function initCountup() {
    const items = Array.from(document.querySelectorAll('[data-countup]'));
    if (!items.length) return;

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion || !('IntersectionObserver' in window)) return; // leave final values

    const observer = new IntersectionObserver(
        (entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    animateCount(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        },
        { threshold: 0.6 },
    );
    items.forEach((el) => observer.observe(el));
}

/**
 * Screenshot slider — scroll-snap track with prev/next buttons, mouse-drag,
 * edge fades that hide at the ends, and gentle autoplay (pauses on
 * hover/focus/drag, stops under reduced-motion).
 */
function setupSlider(root) {
    const track = root.querySelector('[data-slider-track]');
    if (!track) return;

    const step = () => {
        const slide = track.querySelector('.landing-slide');
        if (!slide) return track.clientWidth;
        const style = window.getComputedStyle(track);
        const gap = parseFloat(style.columnGap || style.gap || '0') || 0;
        return slide.getBoundingClientRect().width + gap;
    };

    // Hide the fade on whichever side has no more content to scroll to.
    const updateEdges = () => {
        const max = track.scrollWidth - track.clientWidth;
        track.classList.toggle('at-start', track.scrollLeft <= 2);
        track.classList.toggle('at-end', track.scrollLeft >= max - 2);
    };
    updateEdges();
    track.addEventListener('scroll', updateEdges, { passive: true });
    window.addEventListener('resize', updateEdges);

    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let timer = null;
    const advance = () => {
        const max = track.scrollWidth - track.clientWidth - 4;
        if (track.scrollLeft >= max) track.scrollTo({ left: 0, behavior: 'smooth' });
        else track.scrollBy({ left: step(), behavior: 'smooth' });
    };
    const start = () => { if (!reduce && !timer) timer = window.setInterval(advance, 4500); };
    const stop = () => { window.clearInterval(timer); timer = null; };

    const prev = root.querySelector('[data-slider-prev]');
    const next = root.querySelector('[data-slider-next]');
    if (prev) prev.addEventListener('click', () => track.scrollBy({ left: -step(), behavior: 'smooth' }));
    if (next) next.addEventListener('click', () => track.scrollBy({ left: step(), behavior: 'smooth' }));

    // Mouse drag-to-scroll (touch keeps native swipe). `moved` lets us
    // suppress the click that would otherwise open the lightbox after a drag.
    let down = false, startX = 0, startLeft = 0, moved = 0;
    track.addEventListener('pointerdown', (e) => {
        if (e.pointerType !== 'mouse') return;
        down = true; moved = 0;
        startX = e.clientX; startLeft = track.scrollLeft;
        track.classList.add('is-dragging');
        // NB: no setPointerCapture — it would retarget the click to the
        // track and stop the per-slide click (lightbox) from firing.
        stop();
    });
    track.addEventListener('pointermove', (e) => {
        if (!down) return;
        const dx = e.clientX - startX;
        moved = Math.max(moved, Math.abs(dx));
        track.scrollLeft = startLeft - dx;
    });
    const endDrag = () => {
        if (!down) return;
        down = false;
        track.classList.remove('is-dragging');
        start();
    };
    track.addEventListener('pointerup', endDrag);
    track.addEventListener('pointercancel', endDrag);
    track.addEventListener('pointerleave', endDrag);
    track.addEventListener('click', (e) => {
        if (moved > 6) { e.preventDefault(); e.stopPropagation(); moved = 0; }
    }, true);

    start();
    root.addEventListener('mouseenter', stop);
    root.addEventListener('mouseleave', start);
    root.addEventListener('focusin', stop);
    root.addEventListener('focusout', start);
}

function initSliders() {
    document.querySelectorAll('[data-slider]').forEach(setupSlider);
}

/**
 * Lightbox — clicking a slide opens it full-screen; arrows / keys / swipe
 * navigate the set. Pure DOM, no dependency.
 */
function initLightbox() {
    const box = document.querySelector('[data-lightbox]');
    const slides = Array.from(document.querySelectorAll('.landing-slide'));
    if (!box || !slides.length) return;

    const items = slides.map((s) => ({
        src: s.querySelector('.landing-slide-img')?.src || '',
        cap: s.querySelector('.landing-slide-cap')?.textContent.trim() || '',
    }));
    const imgEl = box.querySelector('[data-lb-img]');
    const capEl = box.querySelector('[data-lb-cap]');
    let idx = 0;

    const render = () => {
        imgEl.src = items[idx].src;
        imgEl.alt = items[idx].cap;
        if (capEl) capEl.textContent = items[idx].cap;
    };
    const open = (i) => {
        idx = i;
        render();
        box.hidden = false;
        requestAnimationFrame(() => box.classList.add('is-open'));
        document.body.style.overflow = 'hidden';
    };
    const close = () => {
        box.classList.remove('is-open');
        document.body.style.overflow = '';
        window.setTimeout(() => { box.hidden = true; }, 200);
    };
    const go = (d) => { idx = (idx + d + items.length) % items.length; render(); };

    slides.forEach((s, i) => s.addEventListener('click', () => open(i)));
    box.querySelector('[data-lb-close]')?.addEventListener('click', close);
    box.querySelector('[data-lb-prev]')?.addEventListener('click', (e) => { e.stopPropagation(); go(-1); });
    box.querySelector('[data-lb-next]')?.addEventListener('click', (e) => { e.stopPropagation(); go(1); });
    box.addEventListener('click', (e) => {
        if (!e.target.closest('.landing-lightbox-stage, .landing-lightbox-nav')) close();
    });
    document.addEventListener('keydown', (e) => {
        if (box.hidden) return;
        if (e.key === 'Escape') close();
        else if (e.key === 'ArrowLeft') go(-1);
        else if (e.key === 'ArrowRight') go(1);
    });
}

/** Back-to-top button — appears past a scroll threshold, smooth-scrolls up. */
function initToTop() {
    const btn = document.querySelector('[data-totop]');
    if (!btn) return;
    const sync = () => btn.classList.toggle('is-visible', window.scrollY > 600);
    sync();
    window.addEventListener('scroll', sync, { passive: true });
    btn.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));
}

function init() {
    initReveal();
    initCountup();
    initSliders();
    initLightbox();
    initToTop();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
} else {
    init();
}
