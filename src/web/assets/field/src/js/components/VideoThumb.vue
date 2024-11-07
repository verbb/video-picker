<template>
    <div class="vp-video-thumb" :class="{ 'is-selected': selected }">
        <div class="vp-video-thumb-image-container">
            <div class="vp-video-thumb-image" :style="'background-image: url(' + url + ')'"></div>
        </div>

        <div class="vp-video-thumb-duration">
            {{ duration }}
        </div>

        <div class="vp-video-thumb-play" @click.prevent="$emit('playVideo')">
            <play />
        </div>
    </div>
</template>

<script>
import Play from '@icons/Play.vue';

export default {
    name: 'VideoThumb',

    components: {
        Play,
    },

    props: {
        url: {
            type: String,
            default: null,
        },

        duration: {
            type: String,
            default: null,
        },

        selected: {
            type: Boolean,
            default: false,
        },
    },

    emits: ['playVideo'],
};

</script>

<style lang="scss">

.vp-video-thumb-image {
    height: 100%;
    width: 100%;
    background-size: cover;
    background-position: center;
}

.vp-video-thumb-image-container {
    position: relative;
    aspect-ratio: 16 / 9;
}

.vp-video-thumb {
    position: relative;
    display: block;
    overflow: hidden;
    border-radius: 0.25rem;
    border-width: 0px;
    border-color: transparent;
    box-shadow: #fff 0 0 0 3px;

    &.is-selected,
    .vp-video-card:hover & {
        box-shadow: rgba(239, 68, 68, 0.8) 0 0 0 3px;
        transition: box-shadow 0.2s ease;
    }
}

.vp-video-thumb-duration {
    position: absolute;
    bottom: 0.25rem;
    right: 0.25rem;
    display: block;
    border-radius: 0.125rem;
    background-color: rgb(0 0 0 / 0.8);
    padding-left: 4px;
    padding-right: 4px;
    padding-top: 3px;
    padding-bottom: 3px;
    font-size: 0.75rem;
    font-weight: 700;
    line-height: .75rem;
    color: #fff;
}

.vp-video-thumb-play {
    position: absolute;
    top: 50%;
    left: 50%;
    fill: #000;
    border: 2px #fff solid;
    background: #fff;
    border-radius: 50%;
    opacity: 0;
    height: 2.5rem;
    width: 2.5rem;
    transform: translate(-50%, -50%);
    transition: opacity 0.2s ease;

    &:hover {
        cursor: pointer;
        opacity: 0.7 !important;
    }

    .vp-video-thumb:hover & {
        opacity: 0.5;
    }
}

</style>
