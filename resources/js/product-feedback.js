import Alpine from 'alpinejs';

Alpine.data('productReviewForm', (initialText = '', limits = {}) => ({
    text: initialText ?? '',
    limits,
    attachments: [],
    mediaError: '',

    addFiles(event) {
        const files = [...this.attachments.map((entry) => entry.file)];
        for (const file of event.target.files) {
            if (!files.some((existing) => existing.name === file.name && existing.size === file.size && existing.lastModified === file.lastModified)) {
                files.push(file);
            }
        }

        let photos = 0;
        let videos = 0;
        this.mediaError = '';
        for (const file of files) {
            const isPhoto = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
            const isVideo = ['video/mp4', 'video/webm'].includes(file.type);
            if (!isPhoto && !isVideo) {
                this.mediaError = 'Choose JPG, PNG, WebP photos or MP4/WebM videos.';
                break;
            }
            if (file.size > (isVideo ? this.limits.videoBytes : this.limits.photoBytes)) {
                this.mediaError = `Each ${isVideo ? 'video' : 'photo'} must be ${isVideo ? this.limits.videoLabel : this.limits.photoLabel} or smaller.`;
                break;
            }
            photos += Number(isPhoto);
            videos += Number(isVideo);
        }
        if (!this.mediaError && (photos > 3 || videos > 1)) {
            this.mediaError = 'Add up to three photos and one video per product.';
        }
        const otherFiles = [...this.$el.closest('form').querySelectorAll('input[type="file"]')]
            .filter((input) => input !== this.$refs.media)
            .flatMap((input) => [...input.files]);
        const allFiles = [...otherFiles, ...files];
        if (!this.mediaError && (allFiles.reduce((size, file) => size + file.size, 0) > this.limits.totalBytes || allFiles.length > this.limits.maxFiles)) {
            this.mediaError = `Keep this submission below ${this.limits.totalLabel} and ${this.limits.maxFiles} attachments in total.`;
        }
        if (this.mediaError) {
            this.syncFiles();
            return;
        }

        this.attachments.forEach((entry) => URL.revokeObjectURL(entry.url));
        this.attachments = files.map((file) => ({ file, url: URL.createObjectURL(file), video: file.type.startsWith('video/') }));
        this.syncFiles();
    },
    removeFile(index) {
        URL.revokeObjectURL(this.attachments[index].url);
        this.attachments.splice(index, 1);
        this.mediaError = '';
        this.syncFiles();
    },
    syncFiles() {
        const transfer = new DataTransfer();
        this.attachments.forEach((entry) => transfer.items.add(entry.file));
        this.$refs.media.files = transfer.files;
    },
    destroy() {
        this.attachments.forEach((entry) => URL.revokeObjectURL(entry.url));
    },
}));
