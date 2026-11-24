/**
 * CP entry loads Plugin Kit elements that other plugins may also define.
 * Same multi-plugin redefine guard as the field and source connect entries.
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
