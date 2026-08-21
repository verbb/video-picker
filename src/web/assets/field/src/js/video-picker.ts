import '../css/video-picker.css';

import { allDefined } from '@verbb/plugin-kit-web/plugin-kit';

import { VIDEO_PICKER_PK_COMPONENTS } from './videoPickerPkComponents.js';
import { VideoPickerInput } from './input/VideoPickerInput';

const INPUT_SELECTOR = '[data-video-picker-auto-mount="input"], .vp-input-component';

const mountedInputs = new WeakSet<Element>();

const mountInput = (root: Element): void => {
    if (!(root instanceof HTMLElement) || mountedInputs.has(root)) {
        return;
    }

    new VideoPickerInput(root).init();
    mountedInputs.add(root);
};

const mountAll = (scope: ParentNode = document): void => {
    if (scope instanceof HTMLElement && scope.matches(INPUT_SELECTOR)) {
        mountInput(scope);
    }

    scope.querySelectorAll(INPUT_SELECTOR).forEach(mountInput);
};

const startObserver = (): void => {
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    mountAll(node as HTMLElement);
                }
            });
        });
    });

    observer.observe(document.body, { childList: true, subtree: true });
};

Craft.VideoPicker = Craft.VideoPicker || {};
Craft.VideoPicker.mountAll = mountAll;
Craft.VideoPicker.startAutoMountObserver = (): void => {
    if (Craft.VideoPicker.__autoMountObserverStarted) {
        return;
    }

    Craft.VideoPicker.__autoMountObserverStarted = true;
    startObserver();
};

const pkMatch = (tag: string): boolean => tag.startsWith('pk-');

const bootstrap = async (): Promise<void> => {
    await allDefined({ match: pkMatch, additionalElements: [...VIDEO_PICKER_PK_COMPONENTS] });

    Craft.VideoPicker.mountAll();
    Craft.VideoPicker.startAutoMountObserver();
};

void bootstrap();
