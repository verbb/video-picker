/**
 * Connect entry also runs `@customElement` for pk-connect / shared tags.
 * Same multi-plugin redefine guard as the field register entry.
 */
const registry = customElements;

if (!registry.__pkSafeDefine) {
    const nativeDefine = registry.define.bind(registry);

    registry.define = (name, constructor, options) => {
        if (registry.get(name)) {
            return;
        }

        nativeDefine(name, constructor, options);
    };

    registry.__pkSafeDefine = true;
}
