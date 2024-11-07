<template>
    <div v-if="videos.length" class="vp-video-cards">
        <template v-for="(video, videoKey) in videos" :key="`video-${videoKey}`">
            <video-card :video="video" />
        </template>
    </div>

    <div v-else>
        <span class="vp-hint-text">{{ t('video-picker', 'No videos available.') }}</span>
    </div>
</template>

<script>
import VideoCard from '@components/VideoCard.vue';

export default {
    name: 'Videos',

    components: {
        VideoCard,
    },

    props: {
        videos: {
            type: Array,
            required: true,
            default: () => { return []; },
        },
    },
};

</script>

<style lang="scss">

.vp-video-cards {
    display: grid;
    width: 100%;
    gap: 1rem;
    grid-template-columns: repeat(3, minmax(0, 1fr));
}

.vp-hint-text {
    color: #7b8793;
}

</style>
