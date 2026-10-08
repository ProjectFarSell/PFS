import './bootstrap';
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
    listTimer: null,
    paused: false,
    touchStartX: 0,

    init() {
        if (this.total === 0) return; // guard: empty carousel — no errors
        if (autoplay > 0 && !this.paused) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    destroy() {
        if (this.timer) clearInterval(this.timer);
    },
    next() {
        if (this.total === 0) return;
        this.current = (this.current + 1) % this.total;
    },
    prev() {
        if (this.total === 0) return;
        this.current = (this.current - 1 + this.total) % this.total;
    },
    goTo(index) {
        if (this.total === 0) return;
        this.current = index;
        if (this.timer) {
            clearInterval(this.timer);
            if (autoplay > 0 && !this.paused) {
                this.timer = setInterval(() => this.next(), autoplay);
            }
        }
    },
    isActive(index) {
        return this.current === index;
    },
    pause() {
        this.paused = true;
        if (this.timer) { clearInterval(this.timer); this.timer = null; }
    },
    play() {
        this.paused = false;
        if (autoplay > 0 && !this.timer) {
            this.timer = setInterval(() => this.next(), autoplay);
        }
    },
    togglePlay() {
        this.paused ? this.play() : this.pause();
    },
    onTouchStart(e) {
        this.touchStartX = e.changedTouches[0].clientX;
    },
    onTouchEnd(e) {
        const delta = this.touchStartX - e.changedTouches[0].clientX;
        if (Math.abs(delta) >= 50) {
            delta > 0 ? this.next() : this.prev();
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
