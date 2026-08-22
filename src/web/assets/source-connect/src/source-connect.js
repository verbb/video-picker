// ==========================================================================
// Video Picker CP — sources settings (jQuery + Plugin Kit web components)
// ==========================================================================

if (typeof Craft.VideoPicker === 'undefined') {
    Craft.VideoPicker = {};
}

(function($) {

/**
 * Mirrors `@verbb/plugin-kit-core` `getErrorMessage()` for Craft AJAX failures.
 */
Craft.VideoPicker.getErrorMessage = function(error, maxTraceLines) {
    maxTraceLines = maxTraceLines || 5;

    const nl2br = (str) => String(str).replace(/\n/g, '<br>');

    const get = (obj, path, fallback) => {
        if (obj == null || typeof obj !== 'object') {
            return fallback;
        }

        const parts = path.split('.');
        let cur = obj;

        for (let i = 0; i < parts.length; i++) {
            if (cur == null || typeof cur !== 'object' || !(parts[i] in cur)) {
                return fallback;
            }

            cur = cur[parts[i]];
        }

        return cur !== undefined ? cur : fallback;
    };

    const getHeading = () => {
        const statusText = get(error, 'response.statusText', '');

        if (statusText) {
            return String(statusText);
        }

        const message = String(get(error, 'message', '') || '');

        if (message.indexOf('Network Error') !== -1) {
            return 'Network Error';
        }

        if (message.indexOf('timeout') !== -1) {
            return 'Request Timeout';
        }

        return 'An error has occurred';
    };

    const formatErrorText = (value) => {
        if (value == null || value === '') {
            return '';
        }

        if (typeof value === 'string') {
            return value;
        }

        if (typeof value === 'number' || typeof value === 'boolean') {
            return String(value);
        }

        if (typeof value === 'object') {
            if (typeof value.message === 'string') {
                return value.message;
            }

            if (typeof value.error === 'string') {
                return value.error;
            }
        }

        return '';
    };

    const getText = () => {
        const message = formatErrorText(get(error, 'response.data.message'));

        if (message) {
            return message;
        }

        const dataError = formatErrorText(get(error, 'response.data.error'));

        if (dataError) {
            return dataError;
        }

        if (error && error.message) {
            return formatErrorText(error.message);
        }

        return '';
    };

    const traces = [];
    const file1 = get(error, 'response.data.file', '');
    const line1 = get(error, 'response.data.line', '');

    if (file1 && line1) {
        traces.push(String(file1) + ':' + String(line1));
    }

    const traceArray = get(error, 'response.data.trace', []) || [];

    for (let i = 0; i < Math.min(maxTraceLines, traceArray.length); i++) {
        const item = traceArray[i];

        if (item && item.file && item.line) {
            traces.push(String(item.file) + ':' + String(item.line));
        }
    }

    const stack = get(error, 'stack', '');

    if (stack && !traces.length) {
        traces.push(String(stack));
    }

    const traceAsString = traces.map(nl2br).join('<br>');

    return {
        heading: getHeading(),
        text: getText(),
        trace: traceAsString,
        traceAsString: traceAsString,
    };
};

Craft.VideoPicker.resolveConnectError = function(sourceError, settings) {
    if (sourceError == null || sourceError === '') {
        return {
            heading: settings.errorHeadingLabel,
            text: settings.genericErrorLabel,
            traceAsString: '',
        };
    }

    if (typeof sourceError === 'string') {
        return {
            heading: settings.errorHeadingLabel,
            text: sourceError,
            traceAsString: '',
        };
    }

    // Logical failures from Craft.sendActionRequest `.then()` use `{ data }`, not `{ response.data }`.
    if (sourceError && sourceError.data && !sourceError.response) {
        sourceError = {
            response: {
                data: sourceError.data,
                statusText: settings.errorHeadingLabel,
            },
        };
    }

    const parsed = Craft.VideoPicker.getErrorMessage(sourceError || {});

    if (parsed.text) {
        return parsed;
    }

    return {
        heading: settings.errorHeadingLabel,
        text: settings.genericErrorLabel,
        traceAsString: '',
    };
};

/**
 * Credentials source Connect / Refresh (Formie IntegrationConnect pattern, WC + jQuery).
 */
Craft.VideoPicker.SourceConnect = Garnish.Base.extend({
    $host: null,
    $status: null,
    $pkStatus: null,
    $button: null,
    $dialog: null,
    settings: null,
    initialSnapshot: '',
    isDirty: false,
    loading: false,
    status: 'disconnected',
    showDetails: false,

    init(host, settings) {
        this.$host = $(host);
        this.settings = settings;
        this.$status = this.$host.find('.vp-connect-status');
        this.$pkStatus = this.$host.find('pk-status.vp-connect-status-icon');
        this.$button = this.$host.find('.vp-connect-refresh')[0];
        this.status = settings.status || 'disconnected';

        if (!this.$button) {
            return;
        }

        this.initialSnapshot = JSON.stringify(this._serializeForm());
        this._syncButtonLabel();
        this._ensureDialog();

        this.addListener(this.$button, 'click', '_onRefreshClick');
        this.addListener($('#main-form').find('input, select, textarea'), 'input', '_onFormChange');
        this.addListener($('#main-form').find('.lightswitch'), 'change', '_onFormChange');
    },

    _serializeForm() {
        const values = {};
        const $form = $('#main-form');

        if (!$form.length) {
            return values;
        }

        $form.find('input, select, textarea').each(function() {
            const name = this.getAttribute('name');

            if (name) {
                values[name] = this.value;
            }
        });

        return values;
    },

    _buildPayload() {
        const values = this._serializeForm();
        const payload = {
            sourceId: values.sourceId || this.settings.sourceId,
            type: values.type || this.settings.type,
        };

        if (typeof Craft !== 'undefined' && Craft.csrfTokenName && values[Craft.csrfTokenName]) {
            payload[Craft.csrfTokenName] = values[Craft.csrfTokenName];
        }

        Object.keys(values).forEach((key) => {
            if (payload.type && key.startsWith('types[' + payload.type + ']')) {
                payload[key] = values[key];
            }
        });

        ['name', 'handle', 'enabled'].forEach((key) => {
            if (values[key] !== undefined) {
                payload[key] = values[key];
            }
        });

        return payload;
    },

    _onFormChange() {
        this.isDirty = JSON.stringify(this._serializeForm()) !== this.initialSnapshot;

        if (this.isDirty) {
            this._setDirtyWarning();
        }
    },

    _setDirtyWarning() {
        this.$host.html(
            '<div class="heading"><span class="warning with-icon">' +
            Craft.escapeHtml(this.settings.saveToConnectLabel) +
            '</span></div>'
        );
    },

    _syncButtonLabel() {
        if (!this.$button) {
            return;
        }

        this.$button.textContent = this.status === 'connected'
            ? this.settings.refreshLabel
            : this.settings.connectLabel;
    },

    _pkStatusForState(status) {
        if (status === 'connected') {
            return 'on';
        }

        if (status === 'error') {
            return 'off';
        }

        return 'disabled';
    },

    _syncStatusLabelStyle(status) {
        this.$status.toggleClass('light', status !== 'connected' && status !== 'error');
    },

    _setPkStatus(status) {
        if (!this.$pkStatus || !this.$pkStatus.length) {
            return;
        }

        this.$pkStatus[0].status = this._pkStatusForState(status);
    },

    _setStatus(status) {
        this.status = status;

        if (status === 'connected') {
            this.$status.text(this.settings.connectedLabel);
        } else if (status === 'error') {
            this.$status.text(this.settings.errorLabel);
        } else if (status === 'connecting') {
            this.$status.text(this.settings.connectingLabel);
        } else {
            this.$status.text(this.settings.notConnectedLabel);
        }

        this._setPkStatus(status);
        this._syncStatusLabelStyle(status);
        this._syncButtonLabel();
    },

    _setLoading(loading) {
        this.loading = loading;

        if (!this.$button) {
            return;
        }

        this.$button.loading = loading;
        this.$button.disabled = loading;
    },

    _ensureDialog() {
        if (this.$dialog) {
            return;
        }

        const dialog = document.createElement('pk-dialog');
        dialog.className = 'vp-source-connect-dialog';
        dialog.withoutHeader = true;
        dialog.open = false;

        dialog.innerHTML = [
            '<pk-button slot="trigger" type="button" variant="none" size="none" icon class="vp-source-connect-dialog__close" data-dialog="close" aria-label="' + Craft.escapeHtml(Craft.t('app', 'Close')) + '">',
            '<pk-icon icon="xmark"></pk-icon>',
            '</pk-button>',
            '<div class="vp-connection-error">',
            '<div class="vp-connection-error__stack">',
            '<div class="vp-connection-error__icon"><pk-icon icon="triangle-exclamation"></pk-icon></div>',
            '<h3 class="vp-connection-error__heading"></h3>',
            '<p class="vp-connection-error__message"></p>',
            '<div class="vp-connection-error__details hidden">',
            '<button type="button" class="vp-connection-error__details-toggle">',
            '<pk-icon icon="chevron-right"></pk-icon>',
            '<span class="vp-connection-error__details-label">' + Craft.escapeHtml(Craft.t('video-picker', 'Show details')) + '</span>',
            '</button>',
            '<div class="vp-connection-error__trace hidden"></div>',
            '</div>',
            '</div>',
            '</div>',
        ].join('');

        document.body.appendChild(dialog);

        this.$dialog = $(dialog);
        this.$dialogHeading = this.$dialog.find('.vp-connection-error__heading');
        this.$dialogMessage = this.$dialog.find('.vp-connection-error__message');
        this.$dialogDetails = this.$dialog.find('.vp-connection-error__details');
        this.$dialogTrace = this.$dialog.find('.vp-connection-error__trace');
        this.$dialogDetailsToggle = this.$dialog.find('.vp-connection-error__details-toggle');

        this.addListener(this.$dialogDetailsToggle, 'click', () => {
            this.showDetails = !this.showDetails;
            this.$dialogTrace.toggleClass('hidden', !this.showDetails);
            this.$dialogDetailsToggle.find('pk-icon').toggleClass('is-open', this.showDetails);
            this.$dialogDetailsToggle.find('.vp-connection-error__details-label').text(
                this.showDetails
                    ? Craft.t('video-picker', 'Hide details')
                    : Craft.t('video-picker', 'Show details')
            );
        });

        dialog.addEventListener('pk-open-change', (event) => {
            const open = Boolean(event.detail && event.detail.open);
            this.$host.toggleClass('vp-source-connect-modal-open', open);

            if (!open) {
                this.showDetails = false;
                this.$dialogTrace.addClass('hidden');
                this.$dialogDetailsToggle.find('pk-icon').removeClass('is-open');
                this.$dialogDetailsToggle.find('.vp-connection-error__details-label').text(
                    Craft.t('video-picker', 'Show details')
                );
            }
        });
    },

    _onRefreshClick(ev) {
        ev.preventDefault();

        if (this.isDirty || this.loading) {
            return;
        }

        this._setLoading(true);
        this._setStatus('connecting');
        this.$dialog[0].open = false;

        Craft.sendActionRequest('POST', 'video-picker/sources/check-connection', {
            data: this._buildPayload(),
        })
            .then((response) => {
                this._setLoading(false);

                if (response?.data?.success) {
                    this._setStatus('connected');
                    return;
                }

                if (response?.data?.message || response?.data?.success === false) {
                    this._setStatus('error');
                    this._showErrorModal(
                        Craft.VideoPicker.resolveConnectError(
                            response?.data?.message || null,
                            this.settings
                        )
                    );
                }
            })
            .catch((error) => {
                this._setLoading(false);
                this._setStatus('error');
                this._showErrorModal(
                    Craft.VideoPicker.resolveConnectError(error, this.settings)
                );
            });
    },

    _showErrorModal(error) {
        this._ensureDialog();

        this.$dialogHeading.text(error.heading || this.settings.errorHeadingLabel);
        this.$dialogMessage.text(error.text || this.settings.genericErrorLabel);

        const trace = error.traceAsString || error.trace || '';

        if (trace) {
            this.$dialogDetails.removeClass('hidden');
            this.$dialogTrace.html(trace).addClass('hidden');
        } else {
            this.$dialogDetails.addClass('hidden');
        }

        this.showDetails = false;
        this.$dialog[0].open = true;
    },
});

})(jQuery);
