import './bootstrap';
import './flash-messages';
import './product-feedback';
import Alpine from 'alpinejs';

window.Alpine = Alpine;

// A reactive store keeps all toggle icons and accessible labels synchronized.
Alpine.store('theme', window.FarSellTheme);
window.FarSellTheme = Alpine.store('theme');
Alpine.magic('theme', () => Alpine.store('theme'));

// ── Carousel Alpine component ─────────────────────────────────────────────────
Alpine.data('carousel', (total = 1, autoplay = 5000) => ({
    current: 0,
    total,
    timer: null,
    paused: false,
    userPaused: false,
    hovering: false,
    focused: false,
    touching: false,
    pageHidden: false,
    reducedMotion: false,
    touchStart: null,
    touchAxis: null,
    suppressClickUntil: 0,
    motionQuery: null,
    onMotionChange: null,
    onVisibilityChange: null,

    init() {
        try {
            this.userPaused = localStorage.getItem('farsell.carousel.paused') === '1';
        } catch (_) {}
        this.motionQuery = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.reducedMotion = this.motionQuery.matches;
        this.pageHidden = document.hidden;
        this.hovering = window.matchMedia('(hover: hover)').matches && this.$el.matches(':hover');
        this.focused = this.$el.contains(document.activeElement);
        this.onMotionChange = (event) => {
            this.reducedMotion = event.matches;
            this.resetTimer();
        };
        this.onVisibilityChange = () => {
            this.pageHidden = document.hidden;
            if (this.pageHidden) {
                this.touchStart = null;
                this.touching = false;
            }
            this.resetTimer();
        };
        this.motionQuery.addEventListener('change', this.onMotionChange);
        document.addEventListener('visibilitychange', this.onVisibilityChange);
        this.resetTimer();
    },
    destroy() {
        clearTimeout(this.timer);
        this.motionQuery?.removeEventListener('change', this.onMotionChange);
        document.removeEventListener('visibilitychange', this.onVisibilityChange);
    },
    resetTimer() {
        clearTimeout(this.timer);
        this.timer = null;
        this.paused = this.userPaused || this.reducedMotion || this.hovering
            || this.focused || this.touching || this.pageHidden;
        if (this.total > 1 && autoplay > 0 && !this.paused) {
            this.timer = setTimeout(() => {
                this.current = (this.current + 1) % this.total;
                this.resetTimer();
            }, autoplay);
        }
    },
    next() {
        this.goTo(this.current + 1);
    },
    prev() {
        this.goTo(this.current - 1);
    },
    goTo(index) {
        if (this.total === 0 || !Number.isInteger(index)) {
            return;
        }
        this.current = ((index % this.total) + this.total) % this.total;
        this.resetTimer();
    },
    isActive(index) {
        return this.current === index;
    },
    pause(reason = 'hovering') {
        this[reason] = true;
        this.resetTimer();
    },
    play(reason = 'hovering') {
        this[reason] = false;
        this.resetTimer();
    },
    togglePlay() {
        this.userPaused = !this.userPaused;
        try {
            localStorage.setItem('farsell.carousel.paused', this.userPaused ? '1' : '0');
        } catch (_) {}
        this.resetTimer();
    },
    onFocusOut() {
        this.$nextTick(() => {
            this.focused = this.$el.contains(document.activeElement);
            this.resetTimer();
        });
    },
    onKeydown(event) {
        if (event.altKey || event.ctrlKey || event.metaKey || event.shiftKey
            || event.target.closest('input, textarea, select, [contenteditable]:not([contenteditable="false"])')) {
            return;
        }
        const destinations = {
            ArrowLeft: this.current - 1,
            ArrowRight: this.current + 1,
            Home: 0,
            End: this.total - 1,
        };
        if (Object.prototype.hasOwnProperty.call(destinations, event.key) && this.total > 1) {
            event.preventDefault();
            this.goTo(destinations[event.key]);
        }
    },
    onTouchStart(event) {
        this.touchStart = event.touches.length === 1 ? {
            x: event.touches[0].clientX,
            y: event.touches[0].clientY,
            id: event.touches[0].identifier,
        } : null;
        this.touchAxis = null;
        this.pause('touching');
    },
    onTouchMove(event) {
        if (event.touches.length !== 1) {
            this.touchStart = null;
            return;
        }
        if (!this.touchStart || this.touchAxis) {
            return;
        }
        const horizontal = Math.abs(event.touches[0].clientX - this.touchStart.x);
        const vertical = Math.abs(event.touches[0].clientY - this.touchStart.y);
        if (Math.max(horizontal, vertical) >= 10) {
            this.touchAxis = horizontal > vertical ? 'horizontal' : 'vertical';
        }
    },
    onTouchEnd(event) {
        const touch = Array.from(event.changedTouches).find((item) => item.identifier === this.touchStart?.id);
        if (this.touchStart && touch) {
            const horizontal = this.touchStart.x - touch.clientX;
            const vertical = this.touchStart.y - touch.clientY;
            if (this.touchAxis !== 'vertical' && Math.abs(horizontal) >= 40
                && Math.abs(horizontal) > Math.abs(vertical)) {
                horizontal > 0 ? this.next() : this.prev();
                this.suppressClickUntil = Date.now() + 500;
            }
        }
        this.onTouchCancel();
    },
    onTouchCancel() {
        this.touchStart = null;
        this.touchAxis = null;
        this.play('touching');
    },
    onClick(event) {
        if (Date.now() < this.suppressClickUntil) {
            event.preventDefault();
            event.stopPropagation();
        }
    },
}));

// ── Countdown Alpine component ────────────────────────────────────────────────
Alpine.data('countdown', (endTime) => ({
    display: '00:00:00',
    timer: null,

    init() {
        this.tick();
        this.timer = setInterval(() => this.tick(), 1000);
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    tick() {
        const remaining = Math.max(0, endTime - Math.floor(Date.now() / 1000));
        if (remaining === 0) {
            this.display = 'Ended';
            clearInterval(this.timer);
            this.timer = null;
            return;
        }
        const h = String(Math.floor(remaining / 3600)).padStart(2, '0');
        const m = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        this.display = `${h}:${m}:${s}`;
    },
}));

Alpine.data('chatThread', (conversationId, userId, endpoint, lastId) => ({
    userId,
    endpoint,
    lastId,
    incoming: [],
    timer: null,
    start() {
        this.$nextTick(() => { this.$refs.feed.scrollTop = this.$refs.feed.scrollHeight; });
        this.timer = setInterval(() => this.poll(), 4000);
    },
    stop() { if (this.timer) clearInterval(this.timer); },
    async poll() {
        try {
            const response = await fetch(`${this.endpoint}?after=${this.lastId}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const messages = await response.json();
            if (!messages.length) return;
            this.incoming.push(...messages);
            this.lastId = messages[messages.length - 1].id;
            this.$nextTick(() => { this.$refs.feed.scrollTop = this.$refs.feed.scrollHeight; });
        } catch (_) {}
    },
}));

Alpine.data('productOptions', (variants = [], optionNames = []) => ({
    qty: 1,
    variants,
    optionNames,
    choices: Object.fromEntries(optionNames.map((name) => [name, ''])),
    valuesFor(name) {
        return [...new Set(this.variants.map((variant) => variant.options[name]).filter(Boolean))];
    },
    isAvailable(name, value) {
        return this.variants.some((variant) => variant.options[name] === value
            && this.optionNames.every((other) => other === name || !this.choices[other] || variant.options[other] === this.choices[other]));
    },
    clearUnavailable(name) {
        for (const other of this.optionNames) {
            if (other !== name && this.choices[other] && !this.isAvailable(other, this.choices[other])) {
                this.choices[other] = '';
            }
        }
    },
    selectedVariant() {
        if (!this.optionNames.length || this.optionNames.some((name) => !this.choices[name])) return null;
        return this.variants.find((variant) => this.optionNames.every((name) => variant.options[name] === this.choices[name])) ?? null;
    },
}));

Alpine.data('quantityStepper', (initial = 1, max = 99, min = 1) => ({
    qty: initial,
    max,
    min,
    decrease() { this.qty = Math.max(this.min, Number(this.qty || this.min) - 1); },
    increase() { this.qty = Math.min(this.max, Number(this.qty || 1) + 1); },
}));

// ── Saved item / wishlist heart toggle ────────────────────────────────────────
Alpine.data('savedItem', (endpoint, initialSaved) => ({
    saved: initialSaved,
    pending: false,
    error: '',
    async toggle() {
        if (this.pending) return;
        this.error = '';
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        if (!csrfToken) {
            this.error = 'Refresh the page and try again.';
            return;
        }
        this.pending = true;
        const method = this.saved ? 'DELETE' : 'POST';
        try {
            const res = await fetch(endpoint, {
                method,
                headers: {
                    'X-CSRF-TOKEN': csrfToken,
                    Accept: 'application/json',
                },
            });
            if (res.ok) {
                const data = await res.json();
                this.saved = data.saved;
            } else {
                this.error = res.status === 401 || res.status === 419 ? 'Sign in again to save this item.' : 'Could not save. Please try again.';
            }
        } catch (_) {
            this.error = 'Check your connection and try again.';
        } finally {
            this.pending = false;
        }
    },
}));

Alpine.data('marketplaceSearch', (initialQuery = '') => ({
    query: initialQuery,
    focused: false,
    recent: [],
    init() {
        try {
            const stored = JSON.parse(localStorage.getItem('farsell.searches') || '[]');
            this.recent = Array.isArray(stored) ? stored.filter((item) => typeof item === 'string' && item.length <= 120).slice(0, 5) : [];
        } catch (_) {}
    },
    remember() {
        const query = this.query.trim();
        if (!query) return;
        this.recent = [query, ...this.recent.filter((item) => item !== query)].slice(0, 5);
        try { localStorage.setItem('farsell.searches', JSON.stringify(this.recent)); } catch (_) {}
    },
    clearRecent() {
        this.recent = [];
        try { localStorage.removeItem('farsell.searches'); } catch (_) {}
    },
    choose(query) {
        this.query = query;
        this.remember();
        this.$nextTick(() => this.$refs.searchForm.requestSubmit());
    },
}));

Alpine.data('chatDock', (conversationsUrl, chatBaseUrl, canChat, initialUnread = 0, userId = 0) => ({
    open: false,
    canChat,
    userId,
    conversationsUrl,
    chatBaseUrl,
    conversations: [],
    unreadTotal: Number(initialUnread || 0),
    selected: null,
    selectedId: null,
    searchTerm: '',
    messages: [],
    lastId: 0,
    draft: '',
    timer: null,
    listTimer: null,
    loading: false,
    sending: false,
    init() {
        try {
            this.open = sessionStorage.getItem('farsell.chatDock.open') === '1';
            this.selectedId = Number(sessionStorage.getItem('farsell.chatDock.conversation') || 0) || null;
        } catch (_) {}
        if (this.open && this.canChat) {
            this.loadConversations().then(() => {
                const existing = this.conversations.find((item) => item.id === this.selectedId);
                if (existing) this.selectConversation(existing);
                else this.startListPolling();
            });
        }
    },
    persist() {
        try {
            sessionStorage.setItem('farsell.chatDock.open', this.open ? '1' : '0');
            sessionStorage.setItem('farsell.chatDock.conversation', this.selected?.id || '');
        } catch (_) {}
    },
    async toggle() {
        this.open = !this.open;
        this.persist();
        if (!this.open) {
            this.stopPolling();
            return;
        }
        if (!this.canChat) return;
        await this.loadConversations();
        const selected = this.conversations.find((item) => item.id === this.selectedId);
        if (selected) await this.selectConversation(selected);
        else this.startListPolling();
    },
    async loadConversations() {
        try {
            const response = await fetch(this.conversationsUrl, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            this.conversations = await response.json();
            this.unreadTotal = this.conversations.reduce((total, item) => total + Number(item.unread || 0), 0);
        } catch (_) {}
    },
    async selectConversation(conversation) {
        this.stopPolling();
        this.selected = conversation;
        this.selectedId = conversation.id;
        this.messages = [];
        this.lastId = 0;
        this.persist();
        await this.loadMessages(true);
        this.timer = setInterval(() => this.pollMessages(), 4000);
        await this.loadConversations();
        this.$nextTick(() => this.scrollToBottom());
    },
    async loadMessages(initial = false) {
        if (!this.selected) return;
        try {
            const after = initial ? 0 : this.lastId;
            const response = await fetch(`${this.chatBaseUrl}/${this.selected.id}/messages?after=${after}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            if (!Array.isArray(data) || !data.length) return;
            this.messages.push(...data);
            this.lastId = data[data.length - 1].id;
            this.$nextTick(() => this.scrollToBottom());
        } catch (_) {}
    },
    async pollMessages() {
        const previousId = this.lastId;
        await this.loadMessages(false);
        if (this.lastId !== previousId) await this.loadConversations();
    },
    async sendMessage() {
        const body = this.draft.trim();
        if (!body || !this.selected || this.sending) return;
        this.sending = true;
        try {
            const response = await fetch(`${this.chatBaseUrl}/${this.selected.id}/messages`, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                },
                body: new URLSearchParams({ body }),
            });
            if (!response.ok) return;
            const message = await response.json();
            this.messages.push(message);
            this.lastId = message.id;
            this.draft = '';
            await this.loadConversations();
            this.$nextTick(() => this.scrollToBottom());
        } catch (_) {} finally {
            this.sending = false;
        }
    },
    backToList() {
        this.stopPolling();
        this.selected = null;
        this.selectedId = null;
        this.messages = [];
        this.persist();
        this.startListPolling();
    },
    close() {
        this.open = false;
        this.stopPolling();
        this.persist();
    },
    stopPolling() {
        if (this.timer) clearInterval(this.timer);
        if (this.listTimer) clearInterval(this.listTimer);
        this.timer = null;
        this.listTimer = null;
    },
    startListPolling() {
        if (!this.open || !this.canChat || this.listTimer) return;
        this.listTimer = setInterval(() => this.loadConversations(), 10000);
    },
    scrollToBottom() {
        if (this.$refs.feed) this.$refs.feed.scrollTop = this.$refs.feed.scrollHeight;
    },
}));

Alpine.start();
