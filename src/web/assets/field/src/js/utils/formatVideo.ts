/**
 * Play-count + relative-date formatters ported from VideoPickerInput.vue computed props.
 * Kept pure so the input preview card and any future surfaces share one contract.
 */

export const formatPlays = (value: number | null | undefined): string => {
    const num = value ?? 0;

    if (num >= 1_000_000_000) {
        const short = (num / 1_000_000_000).toFixed(1).replace(/\.0$/, '');

        return Craft.t('video-picker', '{num}B plays', { num: short });
    }

    if (num >= 1_000_000) {
        const short = (num / 1_000_000).toFixed(1).replace(/\.0$/, '');

        return Craft.t('video-picker', '{num}M plays', { num: short });
    }

    if (num >= 1_000) {
        const short = (num / 1_000).toFixed(1).replace(/\.0$/, '');

        return Craft.t('video-picker', '{num}K plays', { num: short });
    }

    return Craft.t('video-picker', '{num} plays', { num });
};

export const formatTimeAgo = (dateRaw: string | null | undefined): string => {
    if (!dateRaw) {
        return '';
    }

    const date = new Date(dateRaw);
    const now = new Date();
    const diffInSeconds = Math.floor((now.getTime() - date.getTime()) / 1000);

    const timeIntervals = [
        { label: 'year', seconds: 31536000 },
        { label: 'month', seconds: 2592000 },
        { label: 'day', seconds: 86400 },
        { label: 'hour', seconds: 3600 },
        { label: 'minute', seconds: 60 },
        { label: 'second', seconds: 1 },
    ] as const;

    for (const interval of timeIntervals) {
        const count = Math.floor(diffInSeconds / interval.seconds);

        if (count < 1) {
            continue;
        }

        // Craft ICU messages match the Vue originals so translations stay shared.
        switch (interval.label) {
            case 'year':
                return Craft.t('video-picker', '{num, number} {num, plural, =1{year} other{years}} ago', { num: count });
            case 'month':
                return Craft.t('video-picker', '{num, number} {num, plural, =1{month} other{months}} ago', { num: count });
            case 'day':
                return Craft.t('video-picker', '{num, number} {num, plural, =1{day} other{days}} ago', { num: count });
            case 'hour':
                return Craft.t('video-picker', '{num, number} {num, plural, =1{hour} other{hours}} ago', { num: count });
            case 'minute':
                return Craft.t('video-picker', '{num, number} {num, plural, =1{minute} other{minutes}} ago', { num: count });
            case 'second':
                return Craft.t('video-picker', '{num, number} {num, plural, =1{second} other{seconds}} ago', { num: count });
        }
    }

    return Craft.t('video-picker', 'just now');
};

/** Tiny debounce — avoids pulling lodash solely for Explorer search / collection clicks. */
export const debounce = <T extends (...args: never[]) => void>(fn: T, wait: number): T & { cancel: () => void } => {
    let timer: ReturnType<typeof setTimeout> | null = null;

    const wrapped = ((...args: never[]) => {
        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => {
            timer = null;
            fn(...args);
        }, wait);
    }) as T & { cancel: () => void };

    wrapped.cancel = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    };

    return wrapped;
};
