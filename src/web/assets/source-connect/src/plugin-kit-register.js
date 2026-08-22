/**
 * Minimal Plugin Kit registration for source Connect / error dialog only.
 */
import '@verbb/plugin-kit-web/plugin-kit.css';

import { PkButton } from '@verbb/plugin-kit-web/components/button/pk-button.js';
import { PkDialog } from '@verbb/plugin-kit-web/components/dialog/pk-dialog.js';
import { PkIcon } from '@verbb/plugin-kit-web/components/icon/pk-icon.js';
import { PkStatus } from '@verbb/plugin-kit-web/components/status/pk-status.js';

import {
    chevronRight,
    registerIcons,
    triangleExclamation,
    xmark,
} from '@verbb/plugin-kit-icons';

registerIcons({
    chevronRight,
    triangleExclamation,
    xmark,
});

/** Constructors whose modules run `@customElement` — keep reachable so Rollup can't DCE them. */
const SOURCE_CONNECT_PK_CTORS = [PkButton, PkDialog, PkIcon, PkStatus];

const SOURCE_CONNECT_PK_COMPONENTS = ['pk-icon', 'pk-button', 'pk-dialog', 'pk-status'];

export async function registerSourceConnectKit() {
    for (const Ctor of SOURCE_CONNECT_PK_CTORS) {
        if (typeof Ctor !== 'function') {
            throw new Error('Source Connect Plugin Kit constructor missing from bundle');
        }
    }

    await Promise.all(
        SOURCE_CONNECT_PK_COMPONENTS.map((tag) => customElements.whenDefined(tag)),
    );
}

await registerSourceConnectKit();
