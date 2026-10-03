<?php
/**
 * Aria's floating chat widget.
 *
 *   Rendered from includes/footer.php on every page unless:
 *     - the admin has disabled the widget (setting aria_widget_enabled = 0)
 *     - the current script has opted out via $skipAriaWidget = true
 *     - the script lives under /admin (admin pages always skip)
 *
 *   Anonymous-friendly: hits /api/ai_chat.php which already creates an
 *   ai_conversations row with user_id = NULL when there's no session.
 */

// Bail early if disabled globally, on admin, or the page opted out.
if (isset($skipAriaWidget) && $skipAriaWidget) return;
if (!(bool) setting('aria_widget_enabled', true)) return;

$script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (str_contains($script, '/admin/')) return;

$ariaName     = trim((string) setting('ai_persona_name', '')) ?: 'Aria';
$ariaGreeting = (string) setting('aria_widget_greeting',
    'Hi — I\'m ' . $ariaName . '. Ask me about sessions, pricing, or anything else.');
$csrfToken    = function_exists('csrf_token') ? csrf_token() : '';
?>
<!-- Sits above the mobile bottom-tab nav (64px) + any page-level
     sticky action bar (~68px). On desktop drops back to a normal
     bottom-right offset. Icon-only on mobile so it doesn't crowd
     other buttons; the "Chat with <name>" label reappears at md+. -->
<div x-data="ariaWidget()" x-cloak
     class="aria-widget-shell fixed right-4 md:right-5 z-50 flex flex-col items-end pointer-events-none">

  <!-- Chat panel -->
  <div x-show="open" x-transition.opacity
       class="pointer-events-auto w-[92vw] sm:w-[380px] h-[70vh] sm:h-[560px] mb-3 rounded-3xl bg-navy-900 border border-white/10 shadow-[0_30px_80px_-20px_rgba(0,0,0,0.7)] flex flex-col overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-white/5 bg-navy-950/70">
      <div class="flex items-center gap-3">
        <span class="h-9 w-9 rounded-full bg-gold-500/20 text-gold-400 flex items-center justify-center font-serif"><?= e(mb_substr($ariaName, 0, 1)) ?></span>
        <div>
          <p class="font-serif text-gold-400 leading-none"><?= e($ariaName) ?></p>
          <p class="text-[11px] text-beige-100/45 mt-1">A soft companion · usually replies instantly</p>
        </div>
      </div>
      <button @click="open = false" class="text-beige-100/50 hover:text-gold-400 p-1" aria-label="Close chat">
        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 5l10 10M15 5L5 15"/></svg>
      </button>
    </div>

    <div class="flex-1 overflow-y-auto p-5 space-y-4" x-ref="scroll">
      <template x-for="m in messages" :key="m.id">
        <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
          <div :class="m.role === 'user' ? 'inline-block bg-gold-500 text-navy-950' : 'inline-block bg-navy-950 text-beige-100/90 border border-white/5'"
               class="rounded-2xl px-4 py-3 max-w-[88%] text-sm leading-relaxed aria-msg"
               x-html="renderMarkdown(m.content)"></div>
        </div>
      </template>
      <div x-show="loading" class="text-beige-100/40 text-sm italic"><?= e($ariaName) ?> is listening…</div>
    </div>

    <form @submit.prevent="send" class="border-t border-white/5 p-3 flex gap-2 bg-navy-950/50">
      <input x-model="draft" type="text" placeholder="Type a message…"
             class="flex-1 rounded-full bg-navy-900 border border-white/5 px-4 py-2.5 text-sm focus:border-gold-500/50 focus:outline-none">
      <button :disabled="loading || !draft.trim()" class="px-4 py-2.5 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition disabled:opacity-50">Send</button>
    </form>
  </div>

  <!-- Floating launcher — icon-only pill on mobile so it doesn't collide
       with page-level sticky CTAs (e.g. booking's Continue-to-pay bar);
       expands to "Chat with <name>" at md+. -->
  <button @click="toggle"
          x-show="!open"
          x-transition.opacity
          aria-label="Chat with <?= e($ariaName) ?>"
          class="pointer-events-auto inline-flex items-center rounded-full bg-gold-500 text-navy-950 shadow-[0_15px_40px_-15px_rgba(202,164,99,0.55)] hover:bg-gold-400 transition
                 p-1 md:gap-2.5 md:pl-2 md:pr-5 md:py-2">
    <span class="h-9 w-9 md:h-8 md:w-8 rounded-full bg-navy-950 text-gold-400 flex items-center justify-center font-serif"><?= e(mb_substr($ariaName, 0, 1)) ?></span>
    <span class="hidden md:inline text-sm font-medium">Chat with <?= e($ariaName) ?></span>
  </button>
</div>

<style>
  /* Mobile position: sit above the bottom-tab nav (64px) plus any
     page-level sticky action bar (~68px) plus the iOS safe area, so
     the widget never overlaps a Reserve / Continue-to-pay button. */
  .aria-widget-shell { bottom: calc(9.5rem + env(safe-area-inset-bottom)); }
  @media (min-width: 768px) {
    /* Desktop has no sticky action bar / bottom-tab, so drop back to a
       normal offset. */
    .aria-widget-shell { bottom: 1.25rem; }
  }

  /* Make links inside Aria's bubbles obvious and accessible without overriding her tone. */
  .aria-msg a { color: #d4af37; text-decoration: underline; text-underline-offset: 3px; word-break: break-word; }
  .aria-msg a:hover { color: #e7c860; }
  .aria-msg strong { color: #f5ecd6; }
  .aria-msg em { font-style: italic; opacity: 0.95; }
</style>

<script>
function ariaWidget() {
  return {
    open: false,
    draft: '',
    loading: false,
    counter: 1,
    sessionToken: null,
    messages: [
      { id: 0, role: 'assistant', content: <?= json_encode($ariaGreeting, JSON_UNESCAPED_UNICODE) ?> }
    ],

    toggle() {
      this.open = !this.open;
      if (this.open) this.scroll();
    },

    async send() {
      if (!this.draft.trim() || this.loading) return;
      const text = this.draft.trim();
      this.messages.push({ id: this.counter++, role: 'user', content: text });
      this.draft = '';
      this.loading = true;
      this.scroll();
      try {
        const headers = { 'Content-Type': 'application/json' };
        const csrf = <?= json_encode($csrfToken) ?>;
        if (csrf) headers['X-CSRF-Token'] = csrf;
        const res = await fetch(<?= json_encode(url('/api/ai_chat.php')) ?>, {
          method: 'POST',
          headers,
          body: JSON.stringify({ message: text, session_token: this.sessionToken })
        });
        const data = await res.json();
        if (data.session_token) this.sessionToken = data.session_token;
        this.messages.push({
          id: this.counter++,
          role: 'assistant',
          content: data.reply || data.error || 'I am here, gently. Try again in a moment.'
        });
      } catch (err) {
        this.messages.push({ id: this.counter++, role: 'assistant', content: 'I lost the thread for a moment. Please try again.' });
      } finally {
        this.loading = false;
        this.scroll();
      }
    },

    scroll() {
      this.$nextTick(() => {
        if (this.$refs.scroll) this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight;
      });
    },

    // Tiny safe markdown renderer: escape everything, then re-enable a
    // small set of tokens. Bare URLs are auto-linkified. No HTML
    // attributes flow from the model into the DOM.
    renderMarkdown(text) {
      if (!text) return '';
      let s = String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');

      // [text](url) — only http(s) or relative paths.
      s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/g,
        (_, label, href) => `<a href="${href}" target="${href.startsWith('/') ? '_self' : '_blank'}" rel="noopener">${label}</a>`);

      // Bare URLs (skip ones already inside an href).
      s = s.replace(/(^|[^"=])(https?:\/\/[^\s<]+)/g,
        (_, lead, url) => `${lead}<a href="${url}" target="_blank" rel="noopener">${url}</a>`);

      // **bold**
      s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
      // *italic* (kept loose; safe because everything is already escaped)
      s = s.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');

      // Newlines → <br>
      s = s.replace(/\n/g, '<br>');
      return s;
    }
  };
}
</script>
