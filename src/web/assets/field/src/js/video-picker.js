// CSS needs to be imported here as it's treated as a module
import '@/scss/style.scss';

// Accept HMR as per: https://vitejs.dev/guide/api-hmr.html
if (import.meta.hot) {
    import.meta.hot.accept();
}

//
// Start Vue Apps
//

if (typeof Craft.VideoPicker === typeof undefined) {
    Craft.VideoPicker = {};
}

import { createVueApp } from './config';

import VideoPickerInput from './components/VideoPickerInput.vue';

const VIDEO_PICKER_INPUT_SELECTOR = '[data-video-picker-auto-mount="input"], .vp-input-component';
const mountedRoots = new WeakSet();

const parseJsonDataAttr = (root, attrName, fallback = null) => {
    const raw = root?.getAttribute(attrName);

    if (!raw) {
        return fallback;
    }

    try {
        return JSON.parse(raw);
    } catch (e) {
        return fallback;
    }
};

const mountInputRoot = (root) => {
    if (!root || mountedRoots.has(root)) {
        return;
    }

    const settings = parseJsonDataAttr(root, 'data-settings', {});

    const app = createVueApp({
        components: {
            VideoPickerInput,
        },

        data() {
            return {
                settings,
            };
        },
    });

    app.mount(root);
    mountedRoots.add(root);
};

const rootsForSelector = (scope, selector) => {
    if (!scope) {
        return [];
    }

    const roots = [];

    if (scope.matches && scope.matches(selector)) {
        roots.push(scope);
    }

    roots.push(...scope.querySelectorAll(selector));

    return roots;
};

Craft.VideoPicker.mountAll = (scope = document) => {
    rootsForSelector(scope, VIDEO_PICKER_INPUT_SELECTOR).forEach((root) => {
        mountInputRoot(root);
    });
};

Craft.VideoPicker.startAutoMountObserver = () => {
    if (Craft.VideoPicker.__autoMountObserverStarted) {
        return;
    }

    Craft.VideoPicker.__autoMountObserverStarted = true;

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType !== Node.ELEMENT_NODE) {
                    return;
                }

                Craft.VideoPicker.mountAll(node);
            });
        });
    });

    observer.observe(document.body, {
        childList: true,
        subtree: true,
    });
};

Craft.VideoPicker.Input = Garnish.Base.extend({
    init(settings) {
        const root = document.querySelector(`#${settings.inputId}-field ${VIDEO_PICKER_INPUT_SELECTOR}`);
        mountInputRoot(root);
    },
});
$(document).ready(() => {
    Craft.VideoPicker.mountAll(document);
    Craft.VideoPicker.startAutoMountObserver();
});
