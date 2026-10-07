export function dialogBackdrop(close) {
    let startedOutside = false;

    function isOutside(event) {
        if (event.target !== event.currentTarget) return false;
        const bounds = event.currentTarget.getBoundingClientRect();
        return event.clientX < bounds.left || event.clientX > bounds.right
            || event.clientY < bounds.top || event.clientY > bounds.bottom;
    }

    return {
        pointerdown(event) {
            startedOutside = event.button === 0 && isOutside(event);
        },
        click(event) {
            const shouldClose = startedOutside && isOutside(event);
            startedOutside = false;
            if (shouldClose) close();
        },
    };
}
