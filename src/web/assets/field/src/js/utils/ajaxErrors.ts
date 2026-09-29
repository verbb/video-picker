/**
 * Port of `video-picker-before/.../utils/forms.js` `getErrorMessage`.
 * Craft AJAX failures expose statusText / message / file:line / JS stack in
 * slightly different shapes — normalise so UI can render one HTML blob.
 */

export type AjaxErrorInfo = {
    heading: string;
    text: string;
    trace: string;
};

const get = (obj: unknown, path: string, fallback: unknown = undefined): unknown => {
    if (obj == null || typeof obj !== 'object') {
        return fallback;
    }

    const parts = path.split('.');
    let cur: unknown = obj;

    for (const part of parts) {
        if (cur == null || typeof cur !== 'object' || !(part in (cur as object))) {
            return fallback;
        }

        cur = (cur as Record<string, unknown>)[part];
    }

    return cur ?? fallback;
};

const nl2br = (str: string): string => str.replace(/\n/g, '<br>');

export const getErrorMessage = (error: unknown): AjaxErrorInfo => {
    const content: AjaxErrorInfo = {
        heading: '',
        text: '',
        trace: '',
    };

    content.heading = String(get(error, 'response.statusText', 'An error has occurred'));

    const message = get(error, 'response.data.message');
    const dataError = get(error, 'response.data.error');

    if (message != null) {
        content.text = String(message);
    } else if (dataError != null) {
        content.text = String(dataError);
    } else {
        content.text = String(error);
    }

    const file1 = String(get(error, 'response.data.file', '') || '');
    const line1 = String(get(error, 'response.data.line', '') || '');

    if (file1 && line1) {
        content.trace = nl2br(`${file1}:${line1}`);
    }

    const file2 = String(get(error, 'response.data.trace.0.file', '') || '');
    const line2 = String(get(error, 'response.data.trace.0.line', '') || '');

    if (file2 && line2) {
        content.trace += nl2br(`<br>${file2}:${line2}`);
    }

    const jsStack = String(get(error, 'stack', '') || '');

    if (jsStack && !content.trace.length) {
        content.trace += nl2br(jsStack);
    }

    return content;
};

/** HTML fragment matching BEFORE's `<strong>…</strong><br><small>…</small>` error markup. */
export const formatErrorHtml = (error: unknown): string => {
    const info = getErrorMessage(error);

    return `<strong>${info.heading}</strong><br><small>${info.text}<br>${info.trace}</small>`;
};
