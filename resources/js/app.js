import Alpine from 'alpinejs';

window.Alpine = Alpine;

/**
 * Date fields: browsers let the year segment take up to 6 digits (e.g. 11/01/545151)
 * unless the input has a max. Give every <input type="date"> without one a
 * max of 9999-12-31 so the year stops at 4 digits. Done on focus, so it also
 * covers date inputs added later by Alpine (x-for rows, modals, etc.).
 */
document.addEventListener('focusin', (e) => {
    const el = e.target;
    if (el instanceof HTMLInputElement && el.type === 'date' && !el.max) {
        el.max = '9999-12-31';
    }
});

/**
 * Reflects a value into the URL's query string (?key=value) without
 * triggering a navigation. Used across tabbed/filtered pages (Roadblock
 * Management, Assessment Hub, Startup Roadblock/Archive, etc.) so that
 * whichever tab or filter is currently selected survives a page reload —
 * whether that's the admin hitting F5, or an auto-refresh timer — instead
 * of silently resetting back to that page's default view.
 */
window.setQueryParam = function (key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    window.history.replaceState({}, '', url);
};

/**
 * Flash-highlight: reads ?highlight=<id> from the URL, finds the element
 * carrying a matching data-highlight-id, scrolls it into view, and pulses
 * it with the .flash-highlight CSS animation (see resources/css/app.css) a
 * few times. Used by Risk Monitoring's indicator badge links so an admin
 * landing on Assessment Hub / Roadblocks / a startup's profile can
 * immediately spot the row or section they were sent to resolve. Retries
 * briefly since the target may start out hidden behind an Alpine x-show
 * (e.g. a tab that hasn't finished initializing yet). Cleans the query
 * param out of the URL afterward so a page refresh doesn't replay it.
 */
window.flashHighlightFromQuery = function (paramName = 'highlight') {
    const url = new URL(window.location);
    const targetId = url.searchParams.get(paramName);
    if (!targetId) return;

    let attempts = 0;
    const tryFlash = () => {
        const el = document.querySelector(`[data-highlight-id="${CSS.escape(targetId)}"]`);

        if (el && el.offsetParent !== null) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el.classList.add('flash-highlight');
            setTimeout(() => el.classList.remove('flash-highlight'), 3500);

            url.searchParams.delete(paramName);
            window.history.replaceState({}, '', url);
            return;
        }

        if (attempts < 20) {
            attempts += 1;
            setTimeout(tryFlash, 100);
        }
    };

    tryFlash();
};

document.addEventListener('DOMContentLoaded', () => window.flashHighlightFromQuery());

Alpine.store('navigation', {
    hasUnsavedChanges: false,
    showLeaveModal: false,
    nextUrl: null,
    // Set instead of nextUrl when the thing being guarded isn't a page
    // navigation — e.g. opening the Export Document modal while an
    // assessment draft is unsaved. The Leave modal in admin.blade.php
    // branches on this instead of always doing window.location.
    pendingAction: null,
});

Alpine.store('toast', {
    show: false,
    title: '',
    message: '',
    type: 'success',

    notify(type, title, message) {
        this.type = type;
        this.title = title;
        this.message = message;
        this.show = true;

        setTimeout(() => {
            this.show = false;
        }, 3000);
    },

    success(title, message) {
        this.notify('success', title, message);
    },

    error(title, message) {
        this.notify('error', title, message);
    },

    warning(title, message) {
        this.notify('warning', title, message);
    },

    info(title, message) {
        this.notify('info', title, message);
    },

    hide() {
        this.show = false;
    }
});

Alpine.start();