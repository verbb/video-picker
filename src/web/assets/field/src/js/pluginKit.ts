import '@verbb/plugin-kit-web/plugin-kit.css';

// Named deep imports — importing a component module runs its `@customElement`
// registration side effect. Referencing the classes from the registrar keeps the
// decorator modules from being tree-shaken.
import { PkButton } from '@verbb/plugin-kit-web/components/button/pk-button.js';
import { PkDialog } from '@verbb/plugin-kit-web/components/dialog/pk-dialog.js';
import { PkIcon } from '@verbb/plugin-kit-web/components/icon/pk-icon.js';
import { PkInput } from '@verbb/plugin-kit-web/components/input/pk-input.js';
import { PkOption } from '@verbb/plugin-kit-web/components/select/pk-option.js';
import { PkSelect } from '@verbb/plugin-kit-web/components/select/pk-select.js';
import { PkSpinner } from '@verbb/plugin-kit-web/components/spinner/pk-spinner.js';

// Opt-in glyphs for `<pk-icon icon="…">` (JS camelCase keys → kebab lookup names).
import {
    arrowUpRightFromSquare,
    arrowsRotate,
    chevronDown,
    download,
    eye,
    lock,
    magnifyingGlass,
    registerIcons,
    triangleExclamation,
    xmark,
} from '@verbb/plugin-kit-icons';

import { vpRefresh, vpRemove } from './icons/previewActions.js';
import {
    folder,
    layout,
    list,
    thumbUp,
    videoCamera,
} from './icons/sidebarCollectionIcons.js';
import { VIDEO_PICKER_PK_COMPONENTS } from './videoPickerPkComponents.js';

registerIcons({
    arrowUpRightFromSquare,
    arrowsRotate,
    chevronDown,
    download,
    eye,
    lock,
    magnifyingGlass,
    triangleExclamation,
    xmark,
    // Preview-card only — BEFORE’s heavier filled paths (see previewActions.ts).
    vpRefresh,
    vpRemove,
    // Explorer sidebar — BEFORE outline strokes (plugin-local, not FA / kit `list`).
    videoCamera,
    thumbUp,
    folder,
    layout,
    list,
});

/** Constructors whose modules run `@customElement` — must stay reachable so Rollup can't DCE them. */
const VIDEO_PICKER_PK_CTORS = [
    PkButton,
    PkDialog,
    PkIcon,
    PkInput,
    PkOption,
    PkSelect,
    PkSpinner,
] as const;

let registered = false;

/** Entry hook for the plugin-kit-register bundle. */
export async function registerVideoPickerPluginKit(): Promise<void> {
    if (registered) {
        return;
    }

    for (const Ctor of VIDEO_PICKER_PK_CTORS) {
        if (typeof Ctor !== 'function') {
            throw new Error('Video Picker Plugin Kit constructor missing from bundle');
        }
    }

    await Promise.all(VIDEO_PICKER_PK_COMPONENTS.map((tag) => customElements.whenDefined(tag)));
    registered = true;
}
