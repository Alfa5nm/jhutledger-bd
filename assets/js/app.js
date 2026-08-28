(() => {
    const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    document.documentElement.classList.add("motion-ready");
    const toggle = document.querySelector(".nav-toggle");
    const navigation = document.querySelector("#primary-navigation");
    const setNavigation = (open) => {
        document.body.classList.toggle("nav-open", open);
        toggle?.setAttribute("aria-expanded", String(open));
        toggle?.setAttribute("aria-label", open ? "Close navigation" : "Open navigation");
    };
    toggle?.addEventListener("click", () => setNavigation(!document.body.classList.contains("nav-open")));
    navigation?.addEventListener("click", (event) => {
        if (event.target.closest("a")) setNavigation(false);
    });
    document.addEventListener("keydown", (event) => {
        if (event.key === "Escape") {
            setNavigation(false);
            toggle?.focus();
        }
    });
    document.addEventListener("click", (event) => {
        if (document.body.classList.contains("nav-open") && !event.target.closest(".nav-inner")) setNavigation(false);
    });

    document.querySelectorAll("[data-listing-form]").forEach((form) => {
        const channel = form.querySelector('[name="listing_type"]');
        const batch = form.querySelector('[name="batch_id"]');
        const quantity = form.querySelector('[name="listed_quantity"]');
        const summary = form.querySelector("[data-batch-summary]");
        const submit = form.querySelector("[data-listing-submit]");
        const permanentChannel = form.querySelector('input[type="hidden"][name="listing_type"]');
        const permanentBatch = form.querySelector('input[type="hidden"][name="batch_id"]');

        const selectedChannel = () => permanentChannel?.value || channel?.value || "B2B";
        const updateChannel = () => {
            const current = selectedChannel();
            form.querySelectorAll("[data-channel-panel]").forEach((panel) => {
                const active = panel.dataset.channelPanel === current;
                panel.hidden = !active;
                panel.querySelectorAll("input, select, textarea").forEach((field) => {
                    field.disabled = !active;
                    field.required = active;
                });
            });
            if (submit && !form.querySelector('[name="listing_id"]')?.value)
                submit.textContent = `Publish ${current} listing`;
        };
        const updateBatch = () => {
            const batchValue = permanentBatch?.value || batch?.value;
            const option = Array.from(batch?.options || []).find((item) => item.value === batchValue);
            if (!option?.dataset.available) {
                if (summary) summary.hidden = true;
                return;
            }
            const available = Number(option.dataset.available),
                allocated = Number(option.dataset.allocated || 0);
            const editCurrent = Number(form.dataset.editCurrent || 0),
                capacity = Math.max(0, available - allocated + editCurrent);
            const unit = option.dataset.unit || "units";
            form.querySelectorAll("[data-batch-unit]").forEach((label) => {
                label.textContent = unit;
            });
            if (quantity) quantity.max = String(capacity);
            if (summary) {
                summary.hidden = false;
                summary.querySelector("[data-batch-summary-title]").textContent =
                    `Batch #${option.value} · ${option.dataset.material}`;
                summary.querySelector("[data-batch-summary-copy]").textContent =
                    `${available.toFixed(2)} ${unit} available · ${allocated.toFixed(2)} ${unit} currently allocated · ${capacity.toFixed(2)} ${unit} available for this listing`;
            }
        };
        channel?.addEventListener("change", updateChannel);
        batch?.addEventListener("change", updateBatch);
        updateChannel();
        updateBatch();
    });

    document.querySelectorAll("form").forEach((form) =>
        form.addEventListener("submit", (event) => {
            if (!form.checkValidity()) return;
            setTimeout(() => {
                if (!event.defaultPrevented)
                    form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((button) => {
                        button.disabled = true;
                        if (button.tagName === "BUTTON") button.textContent = "Working…";
                    });
            }, 0);
        }),
    );

    const revealItems = document.querySelectorAll(
        ".hero-copy > *, .material-board, .intro-grid > *, .role-item, .ledger-grid > *, main .panel, main .stat-card, main .exception-card",
    );
    revealItems.forEach((item, index) => {
        item.classList.add("reveal");
        item.style.setProperty("--reveal-delay", `${Math.min(index % 4, 3) * 70}ms`);
    });
    if (reduceMotion || !("IntersectionObserver" in window))
        revealItems.forEach((item) => item.classList.add("is-visible"));
    else {
        const observer = new IntersectionObserver(
            (entries) =>
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        observer.unobserve(entry.target);
                    }
                }),
            { threshold: 0.1, rootMargin: "0px 0px -25px" },
        );
        revealItems.forEach((item) => observer.observe(item));
    }

    if (!reduceMotion)
        document.querySelectorAll("[data-count]").forEach((element) => {
            const value = element.textContent.trim();
            if (!/^\d+$/.test(value)) return;
            const target = Number(value),
                started = performance.now();
            const tick = (now) => {
                const progress = Math.min(1, (now - started) / 550);
                element.textContent = String(Math.round(target * progress));
                if (progress < 1) requestAnimationFrame(tick);
            };
            requestAnimationFrame(tick);
        });

    const hero = document.querySelector(".hero");
    if (hero && !reduceMotion && window.matchMedia("(pointer: fine)").matches) {
        let frame = 0,
            targetX = 0,
            targetY = 0;
        const paint = () => {
            hero.style.setProperty("--mouse-x", targetX.toFixed(3));
            hero.style.setProperty("--mouse-y", targetY.toFixed(3));
            frame = 0;
        };
        hero.addEventListener("pointermove", (event) => {
            const bounds = hero.getBoundingClientRect();
            targetX = ((event.clientX - bounds.left) / bounds.width - 0.5) * 2;
            targetY = ((event.clientY - bounds.top) / bounds.height - 0.5) * 2;
            if (!frame) frame = requestAnimationFrame(paint);
        });
        hero.addEventListener("pointerleave", () => {
            targetX = targetY = 0;
            if (!frame) frame = requestAnimationFrame(paint);
        });
    }
})();
