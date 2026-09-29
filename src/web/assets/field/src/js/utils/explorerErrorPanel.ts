import { type AjaxErrorInfo, getErrorMessage } from './ajaxErrors.js';

export function toExplorerError(error: unknown): AjaxErrorInfo {
    if (
        error &&
        typeof error === 'object' &&
        'heading' in error &&
        'text' in error &&
        'trace' in error
    ) {
        return error as AjaxErrorInfo;
    }

    return getErrorMessage(error);
}

/** Source-connect-style error panel for the explorer main area (all text error red). */
export function createExplorerErrorPanel(info: AjaxErrorInfo): HTMLElement {
    const wrap = document.createElement('div');
    wrap.className = 'vp-explorer-error vp-centered';
    wrap.setAttribute('role', 'alert');

    const stack = document.createElement('div');
    stack.className = 'vp-explorer-error__stack';

    const iconWrap = document.createElement('div');
    iconWrap.className = 'vp-explorer-error__icon';
    const icon = document.createElement('pk-icon');
    icon.setAttribute('icon', 'triangle-exclamation');
    iconWrap.appendChild(icon);

    const heading = document.createElement('h3');
    heading.className = 'vp-explorer-error__heading';
    heading.textContent = info.heading;

    const message = document.createElement('p');
    message.className = 'vp-explorer-error__message';
    message.textContent = info.text;

    stack.append(iconWrap, heading, message);

    if (info.trace) {
        const trace = document.createElement('div');
        trace.className = 'vp-explorer-error__trace';
        trace.innerHTML = info.trace;
        stack.appendChild(trace);
    }

    wrap.appendChild(stack);

    return wrap;
}
