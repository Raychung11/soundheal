<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Wellness Journey';
$skipAriaWidget = true; // Aria already lives on this page; no floating widget needed.
$user = current_user();
$mood = trim((string) input('mood', ''));
$mood = preg_replace('/[^a-z]/', '', strtolower($mood));
require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-3xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Wellness journey</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4">Talk with Aria</h1>
  <p class="mt-3 text-beige-100/60">A soft companion. Tell her how you are arriving today.</p>

  <div x-data="ariaChat()" class="mt-10 border border-white/5 rounded-3xl bg-navy-900/40 overflow-hidden flex flex-col h-[60vh]">
    <div class="flex-1 overflow-y-auto p-6 space-y-4" x-ref="scroll">
      <template x-for="m in messages" :key="m.id">
        <div :class="m.role === 'user' ? 'text-right' : 'text-left'">
          <div :class="m.role === 'user' ? 'inline-block bg-gold-500 text-navy-950' : 'inline-block bg-navy-950 text-beige-100/90 border border-white/5'"
               class="rounded-2xl px-4 py-3 max-w-[85%] text-sm leading-relaxed aria-msg"
               x-html="renderMarkdown(m.content)"></div>
        </div>
      </template>
      <div x-show="loading" class="text-beige-100/40 text-sm italic">Aria is listening…</div>
    </div>

    <form @submit.prevent="send" class="border-t border-white/5 p-4 flex gap-3">
      <input x-model="draft" type="text" placeholder="How are you arriving today?"
             class="flex-1 rounded-full bg-navy-950 border border-white/5 px-4 py-3 text-sm focus:border-gold-500/50 focus:outline-none">
      <button :disabled="loading || !draft.trim()" class="px-5 py-3 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition disabled:opacity-50">Send</button>
    </form>
  </div>

  <p class="mt-6 text-xs text-beige-100/40 text-center italic">This is not medical advice. Please consult qualified professionals for medical or mental health concerns.</p>
</section>

<script>
function ariaChat() {
  const initialMood = <?= json_encode($mood) ?>;
  const opening = initialMood
    ? `Thank you for naming that — \"${initialMood}\". Take a slow breath in… and out. Tell me a little more about how that's showing up for you today?`
    : "Welcome back, <?= e(addslashes($user['full_name'])) ?>. Take a slow breath in… and out. How are you arriving today?";
  return {
    messages: [{ id: 0, role: 'assistant', content: opening }],
    draft: initialMood ? `I'm feeling ${initialMood} today.` : '',
    loading: false,
    counter: 1,
    async send() {
      if (!this.draft.trim()) return;
      const userMsg = { id: this.counter++, role: 'user', content: this.draft };
      this.messages.push(userMsg);
      const text = this.draft;
      this.draft = '';
      this.loading = true;
      this.scroll();
      try {
        const res = await fetch('<?= url('/api/ai_chat.php') ?>', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= e(csrf_token()) ?>' },
          body: JSON.stringify({ message: text })
        });
        const data = await res.json();
        this.messages.push({ id: this.counter++, role: 'assistant', content: data.reply || 'I am here, gently. Try again in a moment.' });
      } catch (e) {
        this.messages.push({ id: this.counter++, role: 'assistant', content: 'I lost the thread for a moment. Please try again.' });
      } finally {
        this.loading = false;
        this.scroll();
      }
    },
    scroll() {
      this.$nextTick(() => { this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight; });
    },
    renderMarkdown(text) {
      if (!text) return '';
      let s = String(text)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
      s = s.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+|\/[^\s)]*)\)/g,
        (_, label, href) => `<a href="${href}" target="${href.startsWith('/') ? '_self' : '_blank'}" rel="noopener">${label}</a>`);
      s = s.replace(/(^|[^"=])(https?:\/\/[^\s<]+)/g,
        (_, lead, url) => `${lead}<a href="${url}" target="_blank" rel="noopener">${url}</a>`);
      s = s.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
      s = s.replace(/(^|[^*])\*([^*\n]+)\*(?!\*)/g, '$1<em>$2</em>');
      return s.replace(/\n/g, '<br>');
    }
  }
}
</script>
<style>
  .aria-msg a { color: #d4af37; text-decoration: underline; text-underline-offset: 3px; word-break: break-word; }
  .aria-msg a:hover { color: #e7c860; }
  .aria-msg strong { color: #f5ecd6; }
  .aria-msg em { font-style: italic; opacity: 0.95; }
</style>
<?php require __DIR__ . '/../includes/footer.php'; ?>
