<template>
    <vue-final-modal
        :model-value="showModal"
        :z-index-fn="zIndexFn"
        :esc-to-close="true"
        :class="['vp-modal', modalClass]"
        content-class="vp-modal-wrap"
        overlay-class="vp-modal-overlay"
        content-transition="vfm-fade"
        overlay-transition="vfm-fade"
        :focus-trap="focusTrapOptions"
    >
        <header v-if="showHeader" id="modalTitle" class="vp-modal-header">
            <slot name="header"></slot>
        </header>

        <section id="modalDescription" class="vp-modal-body">
            <slot name="body"></slot>
        </section>

        <footer v-if="showFooter" class="vp-modal-footer">
            <slot name="footer"></slot>
        </footer>
    </vue-final-modal>
</template>

<script>
import { VueFinalModal } from 'vue-final-modal';

import 'vue-final-modal/style.css';

export default {
    name: 'Modal',

    components: {
        VueFinalModal,
    },

    props: {
        modalClass: {
            type: [String, Array],
            default: '',
        },

        showHeader: {
            type: Boolean,
            default: true,
        },

        showFooter: {
            type: Boolean,
            default: true,
        },
    },

    data() {
        return {
            showModal: false,

            focusTrapOptions: {
                allowOutsideClick: true,
            },
        };
    },

    methods: {
        open() {
            this.showModal = true;
        },

        zIndexFn({ index }) {
            return 100 + 2 * index;
        },

        close() {
            this.showModal = false;
        },
    },
};

</script>

<style lang="scss">

.vfm {
    position: fixed !important;
}

.vp-modal-overlay {
    background-color: rgba(123, 135, 147, 0.35) !important;
}

.vp-modal {
    display: flex;
    justify-content: center;
    align-items: center;

    // Fix some colour-banding issues with modal box-shadow which only happens
    // with `position: absolute`.
    position: fixed !important;
}

.vp-modal-wrap {
    position: relative;
    display: flex;
    flex-direction: column;
    margin: 1rem;
    max-height: 100%;
    border-radius: 5px;
    background-color: #fff;
    box-shadow: 0 25px 100px rgba(31, 41, 51, 0.5) !important;
    z-index: 100;
    overflow: hidden;

    width: 66%;
    height: 66%;
    min-width: 600px;
    min-height: 400px;
}

.vp-modal-header {
    width: 100%;
    background-color: #f3f7fc;
    box-shadow: inset 0 -1px 0 rgba(51, 64, 77, 0.1);
    padding: 10px 24px;
    display: flex;
    align-items: center;
    border-top-left-radius: 5px;
    border-top-right-radius: 5px;
}

.vp-modal-title {
    margin: 0;
    padding: 0;
    font-weight: 600;
    font-size: 15px;
    line-height: 30px;
}

.vp-dialog-close {
    width: 20px;
    height: 20px;
    margin-left: auto;
    cursor: pointer;
    border-radius: 4px;
    background-size: 20px 20px;
    background-repeat: no-repeat;
    transition: opacity 0.3s ease;
    background-image: url("data:image/svg+xml,%3Csvg aria-hidden='true' role='img' xmlns='http://www.w3.org/2000/svg' viewBox='0 0 320 512'%3E%3Cpath fill='%233f4d5a' d='M207.6 256l107.72-107.72c6.23-6.23 6.23-16.34 0-22.58l-25.03-25.03c-6.23-6.23-16.34-6.23-22.58 0L160 208.4 52.28 100.68c-6.23-6.23-16.34-6.23-22.58 0L4.68 125.7c-6.23 6.23-6.23 16.34 0 22.58L112.4 256 4.68 363.72c-6.23 6.23-6.23 16.34 0 22.58l25.03 25.03c6.23 6.23 16.34 6.23 22.58 0L160 303.6l107.72 107.72c6.23 6.23 16.34 6.23 22.58 0l25.03-25.03c6.23-6.23 6.23-16.34 0-22.58L207.6 256z'%3E%3C/path%3E%3C/svg%3E");
    opacity: 0.6;

    &:hover {
        opacity: 1;
    }
}

.vp-modal-body {
    height: 100%;
    position: relative;
    overflow: auto;
}

.vp-modal-content {
    padding: 24px;
}

.vp-modal-footer {
    width: 100%;
    background-color: #e4edf6;
    box-shadow: inset 0 1px 0 rgba(51, 64, 77, 0.1);
    padding: 10px 24px;
    border-bottom-left-radius: 5px;
    border-bottom-right-radius: 5px;

    & > .buttons {
        margin: 0;
    }
}

//
// Transitions
//

.vp-modal-enter-active,
.vp-modal-leave-active {
    transition: opacity 0.2s ease;
}

.vp-modal-enter-from,
.vp-modal-leave-to {
    opacity: 0;
}

</style>
