<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Check-in';
require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Door check-in</h1>
<p class="mt-2 text-beige-100/60">Scan or paste a ticket QR token to mark attendance.</p>

<div x-data="checkin()" class="mt-8 max-w-xl border border-white/5 rounded-2xl bg-navy-900/40 p-6">
  <form @submit.prevent="validate" class="flex gap-3">
    <input x-model="token" placeholder="Ticket QR token" autofocus
           class="flex-1 rounded-full bg-navy-950 border border-white/5 px-4 py-3 text-sm focus:border-gold-500/50 focus:outline-none">
    <button class="px-5 py-3 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition">Validate</button>
  </form>

  <template x-if="result">
    <div class="mt-6 p-5 rounded-2xl border"
         :class="result.ok ? 'border-gold-500/40 bg-navy-950/60' : 'border-red-400/40 bg-red-900/10'">
      <p x-text="result.message" class="font-serif text-xl"
         :class="result.ok ? 'text-gold-400' : 'text-red-300'"></p>
      <template x-if="result.ticket">
        <div class="mt-3 text-sm text-beige-100/80">
          <p><strong x-text="result.ticket.full_name"></strong></p>
          <p x-text="result.ticket.event_title" class="text-beige-100/60"></p>
          <p class="text-xs text-beige-100/40 mt-1" x-text="'Code: ' + result.ticket.ticket_code"></p>
        </div>
      </template>
    </div>
  </template>
</div>

<script>
function checkin() {
  return {
    token: '',
    result: null,
    async validate() {
      if (!this.token.trim()) return;
      const res = await fetch('<?= url('/api/qr_validate.php') ?>', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= e(csrf_token()) ?>' },
        body: JSON.stringify({ token: this.token.trim(), action: 'checkin' })
      });
      this.result = await res.json();
      this.token = '';
    }
  }
}
</script>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
