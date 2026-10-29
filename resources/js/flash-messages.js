function initializeFlashMessages() {
    const messages = document.querySelectorAll('[data-flash-message]');
    if (!messages.length) return;

    // Keep notifications outside scrolling panels and their stacking contexts.
    const stack = document.createElement('div');
    stack.className = 'flash-message-stack';
    document.body.append(stack);

    const header = document.querySelector('body > header');
    const positionStack = () => {
        stack.style.setProperty('--flash-header-height', `${header?.getBoundingClientRect().height ?? 0}px`);
    };
    positionStack();
    const headerObserver = header && typeof ResizeObserver !== 'undefined'
        ? new ResizeObserver(positionStack)
        : null;
    if (headerObserver) headerObserver.observe(header);

    const dismiss = (message) => {
        message.remove();
        if (!stack.childElementCount) {
            headerObserver?.disconnect();
            stack.remove();
        }
    };

    messages.forEach((message) => {
        stack.append(message);
        let timer;

        const pause = () => window.clearTimeout(timer);
        const schedule = () => {
            pause();
            if (message.contains(document.activeElement)) return;

            timer = window.setTimeout(() => {
                if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    dismiss(message);
                    return;
                }

                const fade = message.animate([{ opacity: 1 }, { opacity: 0 }], {
                    duration: 400,
                    easing: 'ease-out',
                    fill: 'forwards',
                });
                fade.onfinish = () => dismiss(message);
            }, 3000);
        };

        message.addEventListener('focusin', pause);
        message.addEventListener('focusout', () => window.setTimeout(schedule, 0));
        schedule();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeFlashMessages, { once: true });
} else {
    initializeFlashMessages();
}
