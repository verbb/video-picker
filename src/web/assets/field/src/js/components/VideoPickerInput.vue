<template>
    <div class="vp-input-container">
        <div class="vp-input-wrapper">
            <input
                v-model="videoUrl"
                :name="settings.inputName"
                class="text fullwidth"
                :placeholder="t('video-picker', 'Enter a video URL')"
                @input="fetchVideo()"
            >

            <button class="vp-explorer-btn" @click.prevent="openExplorer">
                {{ t('video-picker', 'Browse videos…') }}
            </button>
        </div>

        <div class="vp-single-video-container">
            <template v-if="loadingVideo">
                <div class="vp-loading" style="width: 1.5rem; height: 1.5rem;"></div>
            </template>

            <div v-else-if="errors.length" class="error vp-single-video-errors">
                <div v-for="(error, errorKey) in errors" :key="errorKey">
                    <div v-html="error"></div>
                </div>
            </div>

            <template v-else-if="currentVideo">
                <div class="vp-single-video-thumb">
                    <video-thumb
                        :url="currentVideo.thumbnail"
                        :duration="currentVideo.duration"
                        @play-video="$events.emit('playVideo', currentVideo)"
                    />
                </div>

                <div class="vp-single-video-meta">
                    <h3 class="vp-single-video-title">
                        <a :href="currentVideo.url" target="_blank" rel="noopener noreferrer">
                            {{ currentVideo.title }}
                        </a>
                    </h3>

                    <div class="vp-single-video-meta-details">
                        <a :href="currentVideo.authorUrl" class="vp-single-video-meta-author" target="_blank" rel="noopener noreferrer">{{ currentVideo.authorName }}</a>
                        <span> • </span>
                        <span class="vp-single-video-meta-plays">{{ formattedPlays }}</span>
                        <span> • </span>
                        <span class="vp-single-video-meta-date">{{ timeAgo }}</span>
                    </div>

                    <div class="vp-single-video-meta-description">
                        {{ currentVideo.description }}
                    </div>

                    <div class="vp-single-video-buttons">
                        <a class="vp-single-video-refresh" :title="t('video-picker', 'Refresh')" @click.prevent="fetchVideo(true)">
                            <Rotate />
                        </a>

                        <a class="vp-single-video-remove" :title="t('video-picker', 'Remove')" @click.prevent="removeVideo">
                            <Remove />
                        </a>
                    </div>
                </div>
            </template>
        </div>

        <explorer v-if="showExplorer" :video="currentVideo" @closed="onExplorerClosed" />
        <preview v-if="showPreview" :video="previewVideo" @closed="onPreviewClosed" />
    </div>
</template>

<script>
import { isPlainObject } from 'lodash-es';

import Explorer from '@components/Explorer.vue';
import Preview from '@components/Preview.vue';
import VideoThumb from '@components/VideoThumb.vue';

import Rotate from '@icons/Rotate.vue';
import Remove from '@icons/Remove.vue';

import { clone } from '@utils/object';
import { getErrorMessage } from '@utils/forms';

export default {
    name: 'VideoPickerInput',

    components: {
        Explorer,
        Preview,
        VideoThumb,
        Rotate,
        Remove,
    },

    data() {
        return {
            loadingVideo: false,
            videoError: null,
            videoUrl: null,
            showExplorer: false,
            showPreview: false,
            previewVideo: null,
            currentVideo: null,
        };
    },

    computed: {
        settings() {
            return this.$root.settings;
        },

        formattedPlays() {
            const value = this.currentVideo.plays;

            if (value >= 1000000000) {
                const num = (value / 1000000000).toFixed(1).replace(/\.0$/, '');

                return Craft.t('video-picker', '{num}B plays', { num });
            }

            if (value >= 1000000) {
                const num = (value / 1000000).toFixed(1).replace(/\.0$/, '');

                return Craft.t('video-picker', '{num}M plays', { num });
            }

            if (value >= 1000) {
                const num = (value / 1000).toFixed(1).replace(/\.0$/, '');

                return Craft.t('video-picker', '{num}K plays', { num });
            }

            return Craft.t('video-picker', '{num} plays', { num: value });
        },

        timeAgo() {
            if (this.currentVideo && this.currentVideo.date) {
                const date = new Date(this.currentVideo.date);
                const now = new Date();
                const diffInSeconds = Math.floor((now - date) / 1000);

                const timeIntervals = [
                    { label: 'year', seconds: 31536000 },
                    { label: 'month', seconds: 2592000 },
                    { label: 'day', seconds: 86400 },
                    { label: 'hour', seconds: 3600 },
                    { label: 'minute', seconds: 60 },
                    { label: 'second', seconds: 1 },
                ];

                for (const interval of timeIntervals) {
                    const count = Math.floor(diffInSeconds / interval.seconds);

                    if (count >= 1) {
                        if (interval.label === 'year') {
                            return Craft.t('video-picker', '{num, number} {num, plural, =1{year} other{years}} ago', { num: count });
                        }

                        if (interval.label === 'month') {
                            return Craft.t('video-picker', '{num, number} {num, plural, =1{month} other{months}} ago', { num: count });
                        }

                        if (interval.label === 'day') {
                            return Craft.t('video-picker', '{num, number} {num, plural, =1{day} other{days}} ago', { num: count });
                        }

                        if (interval.label === 'hour') {
                            return Craft.t('video-picker', '{num, number} {num, plural, =1{hour} other{hours}} ago', { num: count });
                        }

                        if (interval.label === 'minute') {
                            return Craft.t('video-picker', '{num, number} {num, plural, =1{minute} other{minutes}} ago', { num: count });
                        }

                        if (interval.label === 'second') {
                            return Craft.t('video-picker', '{num, number} {num, plural, =1{second} other{seconds}} ago', { num: count });
                        }
                    }
                }

                return Craft.t('video-picker', 'just now');
            }

            return '';
        },


        errors() {
            const allErrors = [];

            if (this.videoError) {
                allErrors.push(this.videoError);
            }

            if (this.currentVideo && isPlainObject(this.currentVideo.errors)) {
                Object.entries(this.currentVideo.errors).forEach(([errorsKey, errors]) => {
                    errors.forEach((error) => {
                        allErrors.push(error);
                    });
                });
            }

            return allErrors;
        },
    },

    created() {
        this.currentVideo = this.settings.value;
        this.videoUrl = this.currentVideo?.url ?? null;

        this.$events.on('playVideo', this.onPlayVideo);
        this.$events.on('selectedVideo', this.onSelectedVideo);
    },

    unmounted() {
        this.$events.off('playVideo', this.onPlayVideo);
        this.$events.off('selectedVideo', this.onSelectedVideo);
    },

    methods: {
        openExplorer() {
            this.showExplorer = true;
        },

        onExplorerClosed() {
            this.showExplorer = false;
        },

        openPreview() {
            this.showPreview = true;
        },

        onPreviewClosed() {
            this.showPreview = false;
        },

        removeVideo() {
            this.currentVideo = null;
            this.videoUrl = null;
        },

        onPlayVideo(video) {
            this.previewVideo = video;
            this.showPreview = true;
        },

        onSelectedVideo(video) {
            this.currentVideo = video;
            this.videoUrl = video.url;
        },

        fetchVideo(refresh) {
            this.loadingVideo = true;
            this.currentVideo = null;

            if (!this.videoUrl) {
                this.loadingVideo = false;

                return;
            }

            const data = {
                url: this.videoUrl,
            };

            if (refresh) {
                data.refresh = true;
            }

            Craft.sendActionRequest('POST', 'video-picker/videos/get-video', { data })
                .then((response) => {
                    if (response.data.error) {
                        this.videoError = response.data.error;
                    } else {
                        this.currentVideo = response.data;
                    }
                })
                .catch((error) => {
                    const info = getErrorMessage(error);

                    this.videoError = `<strong>${info.heading}</strong><br><small>${info.text}<br>${info.trace}</small>`;
                })
                .finally(() => {
                    this.loadingVideo = false;
                });
        },
    },
};

</script>

<style lang="scss">

.vp-input-wrapper {
    position: relative;
}

.vp-explorer-btn {
    position: absolute;
    right: 1rem;
    top: 50%;
    color: #1f5fea;
    font-size: 0.75rem;
    line-height: 1rem;
    cursor: pointer;
    transform: translateY(-50%);

    &:hover {
        text-decoration-line: underline;
    }
}

.vp-single-video-container {
    margin-top: 1rem;
    align-items: flex-start;
    flex-wrap: nowrap;
    display: flex;
    position: relative;
}

.vp-single-video-thumb {
    flex: 1;
    max-width: 12rem;
}

.vp-single-video-meta {
    flex: 1;
    max-width: 24rem;
    min-width: 0;
    margin-left: 0.75rem;
}

.vp-single-video-title {
    font-size: 15px;
    font-weight: 500;
    line-height: 1.3;
    margin: 0;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;

    a {
        color: inherit;
    }
}

.vp-single-video-meta-details {
    margin-top: 4px;
    font-size: 12px;
    color: #606060;
}

.vp-single-video-meta-author {
    color: #606060;

    &:hover {
        color: #0f0f0f;
    }
}

.vp-single-video-meta-description {
    color: #606060;
    margin-top: 8px;
    font-size: 12px;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vp-single-video-buttons {
    position: absolute;
    top: 0;
    right: -5px;
    display: flex;

    a {
        width: 23px;
        height: 23px;
        display: block;
        padding: 5px;
        border-radius: 4px;
        fill: #596775;
        background: #fff;

        svg {
            width: 100%;
            height: 100%;
        }

        &:hover {
            background: #e5ecf6;
        }
    }
}

.vp-single-video-errors {
    word-break: break-word;
}

</style>
