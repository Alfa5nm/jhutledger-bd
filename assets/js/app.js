(() => {
    const root = document.documentElement;
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    root.classList.add('motion-ready');

    const revealItems = document.querySelectorAll(
        '.hero-copy > *, .material-board, .intro-grid > *, .role-item, .ledger-grid > *'
    );

    revealItems.forEach((item, index) => {
        item.classList.add('reveal');
        item.style.setProperty('--reveal-delay', `${Math.min(index % 4, 3) * 90}ms`);
    });

    if (reduceMotion || !('IntersectionObserver' in window)) {
        revealItems.forEach((item) => item.classList.add('is-visible'));
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) return;
            entry.target.classList.add('is-visible');
            observer.unobserve(entry.target);
        });
    }, { threshold: 0.14, rootMargin: '0px 0px -35px' });

    revealItems.forEach((item) => observer.observe(item));

    const hero = document.querySelector('.hero');
    const finePointer = window.matchMedia('(pointer: fine)').matches;
    if (!hero || !finePointer) return;

    let frame = 0;
    let targetX = 0;
    let targetY = 0;

    const paint = () => {
        hero.style.setProperty('--mouse-x', targetX.toFixed(3));
        hero.style.setProperty('--mouse-y', targetY.toFixed(3));
        frame = 0;
    };

    hero.addEventListener('pointermove', (event) => {
        const bounds = hero.getBoundingClientRect();
        targetX = ((event.clientX - bounds.left) / bounds.width - 0.5) * 2;
        targetY = ((event.clientY - bounds.top) / bounds.height - 0.5) * 2;
        if (!frame) frame = window.requestAnimationFrame(paint);
    });

    hero.addEventListener('pointerleave', () => {
        targetX = 0;
        targetY = 0;
        if (!frame) frame = window.requestAnimationFrame(paint);
    });
})();
