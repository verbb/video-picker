/**
 * CP shell styles (sources index, settings panes, provider badges).
 */
import '../scss/video-picker.scss';
// Plugin Kit's stylesheet loads with the page, in <head>, so its FOUCE rules hide pk-* elements until their components load.
import '@verbb/plugin-kit-web/plugin-kit.css';

// Plugin Kit loads only on pages that use it, such as the Sources page's empty state. The guard lets another plugin's
// bundle on the same page define the same elements first.
if (document.querySelector('pk-state-panel')) {
    void import('./safeCustomElementDefine.js').then(() => import('@verbb/plugin-kit-web/components/state-panel/pk-state-panel.js'));
}
