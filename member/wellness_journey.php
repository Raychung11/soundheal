<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Wellness Journey';
$user = current_user();
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
               class="rounded-2xl px-4 py-3 max-w-[85%] text-sm whitespace-pre-line" x-text="m.content"></div>
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
  return {
    messages: [{ id: 0, role: 'assistant', content: "Welcome back, <?= e($user['full_name']) ?>. Take a slow breath in… and out. How are you arriving today?" }],
    draft: '',
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
    }
  }
}
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
