import { dashboardSnapshot, offsetDate, snapshotDate } from './dashboard-data';
import seller from './seller-data';
import admin from './admin-data';
import rider from './rider-data';

const fixtures = { seller, admin, rider };

export function registerDemoDashboards(Alpine) {
    Alpine.data('demoDashboard', role => ({
        snapshotDate,
        earliest: offsetDate(snapshotDate, -89),
        preset: '7',
        start: '',
        end: '',
        period: '',
        error: '',
        fullscreenError: '',
        presenting: false,
        fullscreen: false,
        snapshot: null,
        init() {
            this.selectPreset('7');
        },
        selectPreset(preset) {
            this.preset = preset;
            this.error = '';
            if (preset === 'custom') return;
            this.start = offsetDate(snapshotDate, 1 - Number(preset));
            this.end = snapshotDate;
            this.applyRange();
        },
        applyRange() {
            if (!this.start || !this.end || this.start < this.earliest || this.start > this.end || this.end > snapshotDate) {
                this.error = 'Choose an ordered date range within the 90-day demo window.';
                return;
            }
            this.error = '';
            this.period = `${this.start} to ${this.end} (Philippine time)`;
            this.snapshot = dashboardSnapshot(fixtures[role], this.start, this.end);
        },
        syncFullscreen() {
            this.fullscreen = document.fullscreenElement === this.$root;
        },
        async toggleFullscreen() {
            this.fullscreenError = '';
            try {
                if (this.fullscreen) await document.exitFullscreen();
                else if (this.$root.requestFullscreen) await this.$root.requestFullscreen();
                else this.fullscreenError = 'Fullscreen is unavailable in this browser. Presentation mode is still available.';
            } catch {
                this.fullscreenError = 'Fullscreen could not open. Presentation mode is still available.';
            }
        },
    }));
}
