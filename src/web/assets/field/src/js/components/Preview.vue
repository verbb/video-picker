<template>
    <modal ref="modal" modal-class="vp-preview-modal" :show-header="false" :show-footer="false" @click-outside="onCancelModal">
        <template #body>
            <div class="vp-no-videos">
                <div class="vp-loading vp-loading-lg"></div>
            </div>

            <div v-html="video.embedHtml"></div>
        </template>
    </modal>
</template>

<script>
import Modal from '@components/Modal.vue';

export default {
    name: 'Preview',

    components: {
        Modal,
    },

    props: {
        video: {
            type: Object,
            default() { return {}; },
        },
    },

    mounted() {
        this.$nextTick(() => {
            // Open the modal programatically, to ensure transition is smooth
            this.$refs.modal.open();
        });
    },

    methods: {
        closeModal() {
            // Close the modal programatically, to ensure transition is smooth
            this.$refs.modal.close();
        },

        onCancelModal() {
            this.closeModal();
        },
    },
};

</script>

<style lang="scss">

.vp-preview-modal .vp-modal-body {
    background: #000;
}

.vp-preview-modal iframe {
    position: absolute;
    height: 100%;
    width: 100%;
}

</style>
