// Public site behaviour. Everything here is progressive enhancement:
// the site works fully with JavaScript disabled.

document.documentElement.classList.add('js');

// Mobile navigation (a <details> disclosure): close on link click or Escape.
const mobileNav = document.querySelector('[data-mobile-nav]');

if (mobileNav) {
    const summary = mobileNav.querySelector('summary');

    mobileNav.addEventListener('toggle', () => {
        summary.setAttribute('aria-label', mobileNav.open ? 'Close menu' : 'Open menu');
    });

    mobileNav.querySelectorAll('a').forEach((link) => {
        link.addEventListener('click', () => { mobileNav.open = false; });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && mobileNav.open) {
            mobileNav.open = false;
            summary.focus();
        }
    });

    window.matchMedia('(min-width: 1280px)').addEventListener('change', (event) => {
        if (event.matches) {
            mobileNav.open = false;
        }
    });
}

// Live TV player, loaded only on the Live TV page.
const livePlayer = document.querySelector('[data-live-player]');

if (livePlayer) {
    import('./live-player.js').then(({ startLivePlayer }) => startLivePlayer(livePlayer));
}

// Gentle reveal of sections as they scroll into view.
const revealables = document.querySelectorAll('[data-reveal]');
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (revealables.length && 'IntersectionObserver' in window && !reduceMotion) {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { rootMargin: '0px 0px -8% 0px', threshold: 0.08 });

    revealables.forEach((el) => observer.observe(el));
} else {
    revealables.forEach((el) => el.classList.add('is-visible'));
}
