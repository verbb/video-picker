/**
 * Video Picker source edit — connect row (`<pk-connect>` / `<pk-connect-oauth>`).
 */
import './safeCustomElementDefine.js';
import '@verbb/plugin-kit-web/plugin-kit.css';
import '@verbb/plugin-kit-web/styles/connect/pk-connect.css';
import { registerCpConnectKit } from '@verbb/plugin-kit-web/connect/register-cp-connect.js';

void registerCpConnectKit();
