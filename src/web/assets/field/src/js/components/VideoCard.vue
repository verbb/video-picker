<template>
    <div
        class="vp-video-card"
        @click="selectVideo(video)"
        @dblclick="useVideo(video)"
    >
        <video-thumb
            :selected="isVideoSelected"
            :url="video.thumbnail"
            :duration="video.duration"
            @play-video="play(video)"
        />

        <div class="vp-video-card-container">
            <div v-if="video.private" class="vp-icon-private">
                <lock />
            </div>

            <div class="vp-video-card-text">
                {{ video.title }}
            </div>
        </div>
    </div>
</template>

<script>
import VideoThumb from '@components/VideoThumb.vue';
import Lock from '@icons/Lock.vue';

export default {
    name: 'VideoCard',

    components: {
        VideoThumb,
        Lock,
    },

    inject: ['currentVideo'],

    props: {
        video: {
            type: Object,
            required: true,
        },
    },

    computed: {
        isVideoSelected() {
            if (!this.currentVideo) {
                return false;
            }

            return this.currentVideo.id === this.video.id;
        },
    },

    methods: {
        selectVideo(video) {
            this.$events.emit('currentVideo', video);
        },

        useVideo(video) {
            this.$events.emit('selectVideo', video);
        },

        play(video) {
            this.$events.emit('playVideo', video);
        },
    },
};

</script>

<style lang="scss">

.vp-video-card-container {
    display: flex;
    flex-wrap: nowrap;
    flex-direction: row;
    align-items: center;
    margin-top: 0.5rem;
}

.vp-video-card-text {
    flex: 1 1 0%;
    overflow: hidden;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
}

.vp-icon-private {
    width: 1rem;
    height: 1rem;
    margin-right: 0.25rem;
}

</style>
