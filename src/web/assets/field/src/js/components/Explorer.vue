<template>
    <modal ref="modal" modal-class="vp-explorer-modal" :show-header="false" @click-outside="onCancelModal">
        <template #body>
            <div v-if="loadingSources" class="vp-no-videos">
                <div class="vp-loading vp-loading-lg"></div>
            </div>

            <div v-else-if="sourcesError" class="vp-no-videos error" style="word-break: break-word;">
                <div v-html="sourcesError"></div>
            </div>

            <div v-else class="vp-explorer">
                <div class="sidebar">
                    <div class="vp-sidebar-select">
                        <div class="select fullwidth">
                            <select v-model="currentSourceHandle">
                                <option
                                    v-for="(source, sourceKey) in sources"
                                    :key="`source-${sourceKey}`"
                                    :value="source.handle"
                                >
                                    {{ source.name }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <nav>
                        <ul v-if="currentSource">
                            <template v-for="(section, sectionKey) in currentSource.sections" :key="`section-${sectionKey}`">
                                <li class="heading">
                                    <span>{{ section.name }}</span>
                                </li>

                                <li v-for="(collection, collectionKey) in section.collections" :key="`collection-${sectionKey}-${collectionKey}`">
                                    <a
                                        href="#"
                                        :class="{ sel: isCollectionSelected(collection) }"
                                        @click.prevent="onCollectionClick(collection)"
                                    >
                                        <component :is="collection.icon" v-if="collection.icon" class="vp-sidebar-icon" />
                                        {{ collection.name }}
                                    </a>
                                </li>
                            </template>
                        </ul>
                    </nav>
                </div>

                <div ref="main" class="main" @scroll="onScroll">
                    <div v-if="currentSource" class="vp-videos-search-wrapper">
                        <input
                            v-model="query"
                            type="search"
                            class="text fullwidth"
                            :placeholder="t('video-picker', 'Search {source} videos…', { source: currentSource.name })"
                            @input="debouncedSearch"
                            @keyup.enter="search"
                        >
                    </div>

                    <div class="vp-videos-wrapper">
                        <div v-if="loadingVideos" class="vp-no-videos">
                            <div class="vp-loading vp-loading-lg"></div>
                        </div>

                        <div v-else-if="videosError" class="error" style="word-break: break-word;">
                            <div v-html="videosError"></div>
                        </div>

                        <template v-else>
                            <videos :videos="videos" />

                            <div v-if="nextPage" class="vp-videos-more">
                                <template v-if="!loadingMore">
                                    <button class="btn" @click="fetchMoreVideos()">{{ t('video-picker', 'Load More') }}</button>
                                </template>

                                <template v-else>
                                    <div class="vp-loading" style="height: 2rem;"></div>
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <template #footer>
            <div class="buttons left">
                <button class="btn vp-videos-refresh-btn" role="button" :title="t('video-picker', 'Refresh')" @click.prevent="refreshSource">
                    <Rotate />
                </button>
            </div>

            <div class="buttons right">
                <button class="btn" role="button" @click.prevent="onCancelModal">{{ t('app', 'Cancel') }}</button>
                <button class="btn submit" :class="{ 'disabled': !canSelect }" role="button" @click.prevent="onSelect">{{ t('video-picker', 'Select') }}</button>
            </div>
        </template>
    </modal>
</template>

<script>
import { computed } from 'vue';
import { debounce } from 'lodash-es';

import Videos from '@components/Videos.vue';
import Modal from '@components/Modal.vue';
import ThumbUp from '@icons/ThumbUp.vue';
import Folder from '@icons/Folder.vue';
import Layout from '@icons/Layout.vue';
import VideoCamera from '@icons/VideoCamera.vue';
import List from '@icons/List.vue';
import Rotate from '@icons/Rotate.vue';

import { getErrorMessage } from '@utils/forms';

export default {
    name: 'Explorer',

    components: {
        Videos,
        Modal,
        ThumbUp,
        Folder,
        Layout,
        VideoCamera,
        List,
        Rotate,
    },

    provide() {
        return {
            currentVideo: computed(() => {
                return this.currentVideo;
            }),
        };
    },

    props: {
        video: {
            type: Object,
            default() { return {}; },
        },
    },

    data() {
        return {
            loadingSources: false,
            loadingVideos: false,
            loadingMore: false,
            nextPage: null,
            sourcesError: null,
            videosError: null,
            query: '',
            sources: [],
            videos: [],
            currentSource: null,
            currentCollection: null,
            currentVideo: null,
        };
    },

    computed: {
        settings() {
            return this.$root.settings;
        },

        canSelect() {
            return this.currentVideo ? true : false;
        },

        currentSourceHandle: {
            get() {
                return this.currentSource ? this.currentSource.handle : null;
            },

            set(value) {
                const newSource = this.sources.find((source) => { return source.handle === value; });

                if (newSource) {
                    this.currentSource = newSource;
                    this.setCollection(this.currentSource?.sections[0]?.collections[0] ?? null);

                    this.reset();
                    this.fetchVideos();
                }
            },
        },

        debouncedSearch() {
            return debounce(() => {
                this.search();
            }, 1000);
        },

        debouncedFetchVideos() {
            return debounce(() => {
                this.fetchVideos();
            }, 400);
        },
    },

    created() {
        this.$events.on('currentVideo', this.onCurrentVideo);
        this.$events.on('selectVideo', this.onSelectVideo);

        this.currentVideo = this.video;
    },

    mounted() {
        this.$nextTick(() => {
            // Open the modal programatically, to ensure transition is smooth
            this.$refs.modal.open();

            // Fetch the explorer content
            this.fetchSources();
        });
    },

    unmounted() {
        this.$events.off('currentVideo', this.onCurrentVideo);
        this.$events.off('selectVideo', this.onSelectVideo);
    },

    methods: {
        closeModal() {
            // Close the modal programatically, to ensure transition is smooth
            this.$refs.modal.close();
        },

        onCancelModal() {
            this.closeModal();
        },

        reset() {
            this.query = '';
            this.nextPage = null;
            this.videos = [];
        },

        onCurrentVideo(video) {
            this.currentVideo = video;
        },

        onSelectVideo(video) {
            this.onSelect();
        },

        refreshSource() {
            this.fetchSources(true);
        },

        fetchSources(refresh) {
            this.loadingSources = true;
            this.sourcesError = null;

            const data = {
                fieldId: this.settings.fieldId,
            };

            if (refresh) {
                data.refresh = true;
            }

            Craft.sendActionRequest('POST', 'video-picker/videos/get-sources', { data })
                .then((response) => {
                    this.sources = response.data;

                    if (this.sources.length) {
                        // eslint-disable-next-line
                        this.currentSource = this.sources[0];
                        this.setCollection(this.currentSource?.sections[0]?.collections[0] ?? null);

                        this.fetchVideos();
                    }
                })
                .catch((error) => {
                    const info = getErrorMessage(error);

                    this.sourcesError = `<strong>${info.heading}</strong><br><small>${info.text}<br>${info.trace}</small>`;
                })
                .finally(() => {
                    this.loadingSources = false;
                });
        },

        fetchVideos() {
            this.loadingVideos = true;
            this.videosError = null;

            const data = {
                source: this.currentSource.handle,
                method: this.currentCollection?.method ?? null,
                options: this.currentCollection?.options ?? {},
            };

            Craft.sendActionRequest('POST', 'video-picker/videos/get-videos', { data })
                .then((response) => {
                    this.videos = response.data.videos;
                    this.nextPage = response.data.nextPage;
                })
                .catch((error) => {
                    const info = getErrorMessage(error);

                    this.videosError = `<strong>${info.heading}</strong><br><small>${info.text}<br>${info.trace}</small>`;
                })
                .finally(() => {
                    this.loadingVideos = false;
                });
        },

        fetchMoreVideos() {
            this.loadingMore = true;
            this.videosError = null;

            const data = {
                source: this.currentSource.handle,
                method: this.currentCollection?.method ?? null,
                options: this.currentCollection?.options ?? {},
            };

            data.options.nextPage = this.nextPage;

            Craft.sendActionRequest('POST', 'video-picker/videos/get-videos', { data })
                .then((response) => {
                    this.videos = this.videos.concat(response.data.videos);
                    this.nextPage = response.data.nextPage;
                })
                .catch((error) => {
                    const info = getErrorMessage(error);

                    this.videosError = `<strong>${info.heading}</strong><br><small>${info.text}<br>${info.trace}</small>`;
                })
                .finally(() => {
                    this.loadingMore = false;
                });
        },

        searchVideos() {
            this.loadingVideos = true;
            this.videosError = null;

            const data = {
                source: this.currentSource.handle,
                method: 'search',
                options: this.currentCollection?.options ?? {},
            };

            data.options.q = this.query;

            Craft.sendActionRequest('POST', 'video-picker/videos/get-videos', { data })
                .then((response) => {
                    this.videos = response.data.videos;
                    this.nextPage = response.data.nextPage;
                })
                .catch((error) => {
                    const info = getErrorMessage(error);

                    this.videosError = `<strong>${info.heading}</strong><br><small>${info.text}<br>${info.trace}</small>`;
                })
                .finally(() => {
                    this.loadingVideos = false;
                });
        },

        setCollection(collection) {
            this.currentCollection = collection;

            // Typecast to ensure correct reactivity
            if (Array.isArray(this.currentCollection.options)) {
                this.currentCollection.options = {};
            }
        },

        isCollectionSelected(collection) {
            // Compare the collection as JSON strings
            if (JSON.stringify(collection) === JSON.stringify(this.currentCollection)) {
                return true;
            }

            return false;
        },

        onCollectionClick(collection) {
            this.setCollection(collection);

            this.debouncedFetchVideos();
        },

        onScroll() {
            this.maybeLoadMore();
        },

        maybeLoadMore() {
            if (!this.nextPage) {
                return false;
            }

            if (!this.canLoadMore()) {
                return false;
            }

            if (this.loadingMore) {
                return false;
            }

            this.fetchMoreVideos();
        },

        canLoadMore() {
            const { scrollHeight } = this.$refs.main;
            const { scrollTop } = this.$refs.main;
            const height = this.$refs.main.clientHeight;

            return (scrollHeight - scrollTop <= height + 15);
        },

        search() {
            this.debouncedSearch.cancel();

            this.searchVideos();
        },

        onSelect() {
            this.$events.emit('selectedVideo', this.currentVideo);

            this.closeModal();
        },
    },
};

</script>

<style lang="scss">

.vp-explorer-modal .vp-modal-body {
    overflow: hidden;
}

.vp-explorer {
    display: flex;
    height: 100%;
    position: relative;

    .vp-sidebar-select {
        margin-left: 0.5rem;
        margin-right: 0.5rem;
    }

    .sidebar .heading {
        margin: 14px 24px 2px;
        margin-left: 0.75rem;
        margin-right: 0.75rem;
    }

    .sidebar nav li a {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }

    .sidebar nav li:not(.has-subnav) > a.active-drop-target,
    .sidebar nav li:not(.has-subnav) > a.sel {
        background-color: #cdd8e4 !important;
        color: var(--text-color);
    }

    .vp-sidebar-icon {
        --tw-text-opacity: 1;
        color: rgb(59 130 246 / var(--tw-text-opacity));
        width: 1.25rem;
        height: 1.25rem;
        margin-right: 0.5rem;
    }

    .sidebar {
        overflow: auto;
    }

    .main {
        flex: 1;
        padding: 14px;
        position: relative;
        overflow: auto;
    }
}

.vp-videos-refresh-btn svg {
    width: 20px;
    height: 20px;
    fill: #3f4d5a;
    padding: 3px;
}

.vp-videos-wrapper {
    padding: 1rem 0;
}

.vp-videos-more {
    margin-top: 1rem;
}

.vp-no-videos {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: #596673;
}


// ==========================================================================
// Loading
// ==========================================================================

@keyframes loading {
    0% {
        transform: rotate(0)
    } 100% {
        transform: rotate(360deg)
    }
}

.vp-loading {
    position: relative;
    pointer-events: none;
    color: transparent !important;
    min-height: 1rem;

    &::after {
        position: absolute;
        display: block;
        height: 1rem;
        width: 1rem;
        margin-top: -0.65rem;
        margin-left: -0.65rem;
        border-width: 2px;
        border-style: solid;
        border-radius: 9999px;
        border-color: #E5422B;
        animation: loading 0.5s infinite linear;
        border-right-color: transparent;
        border-top-color: transparent;
        content: "";
        left: 50%;
        top: 50%;
        z-index: 1;
    }
}

.vp-loading.vp-loading-lg {
    min-height: 2rem;

    &::after {
        height: 2rem;
        width: 2rem;
        margin-top: -1rem;
        margin-left: -1rem;
    }
}

.vp-loading.vp-loading-sm {
    min-height: 0.75rem;

    &::after {
        height: 0.75rem;
        width: 0.75rem;
        margin-top: -0.5rem;
        margin-left: -0.375rem;
    }
}

.vp-loading.vp-loading-tiny {
    min-height: 0.5rem;

    &::after {
        height: 0.5rem;
        width: 0.5rem;
        margin-top: -6px;
        margin-left: -6px;
    }
}

</style>
