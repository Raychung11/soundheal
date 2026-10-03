<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'New invoice';

$errors = [];
$prefill = [
    'bill_to_name'      => '',
    'bill_to_attention' => '',
    'bill_to_address'   => '',
    'bill_to_email'     => '',
    'bill_to_phone'     => '',
    'bill_to_tax_id'    => '',
    'notes'             => '',
    'tax'               => 0.00,
    'items'             => [
        ['description' => '', 'quantity' => 1, 'unit_price' => 0.00],
    ],
];

if (is_post()) {
    csrf_verify();

    $prefill['bill_to_name']      = trim((string) input('bill_to_name', ''));
    $prefill['bill_to_attention'] = trim((string) input('bill_to_attention', ''));
    $prefill['bill_to_address']   = trim((string) input('bill_to_address', ''));
    $prefill['bill_to_email']     = trim((string) input('bill_to_email', ''));
    $prefill['bill_to_phone']     = trim((string) input('bill_to_phone', ''));
    $prefill['bill_to_tax_id']    = trim((string) input('bill_to_tax_id', ''));
    $prefill['notes']             = trim((string) input('notes', ''));
    $prefill['tax']               = max(0.0, (float) input('tax', 0));

    if ($prefill['bill_to_name'] === '') {
        $errors[] = 'Bill-to company name is required.';
    }
    if ($prefill['bill_to_email'] !== '' && !filter_var($prefill['bill_to_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Bill-to email doesn\'t look valid.';
    }

    // Line items — arrive as items[i][description|quantity|unit_price].
    // Drop rows with no description AND no amount so a stray blank
    // row doesn't inflate the total.
    $rawItems = (array) ($_POST['items'] ?? []);
    $lineItems = [];
    foreach ($rawItems as $li) {
        if (!is_array($li)) continue;
        $desc = trim((string) ($li['description'] ?? ''));
        $qty  = max(0, (float) ($li['quantity'] ?? 0));
        $unit = max(0.0, (float) ($li['unit_price'] ?? 0));
        if ($desc === '' && $qty === 0.0 && $unit === 0.0) continue;
        if ($desc === '') { $errors[] = 'Every line item needs a description.'; continue; }
        if ($qty <= 0)    { $errors[] = "Quantity on '$desc' must be greater than zero."; continue; }
        $lineItems[] = [
            'description' => $desc,
            'quantity'    => $qty,
            'unit_price'  => $unit,
            'amount'      => round($qty * $unit, 2),
        ];
    }
    $prefill['items'] = $lineItems ?: $prefill['items'];

    if (!$lineItems) {
        $errors[] = 'Add at least one line item.';
    }

    if (!$errors) {
        $billTo = array_filter([
            'name'      => $prefill['bill_to_name'],
            'attention' => $prefill['bill_to_attention'] ?: null,
            'address'   => $prefill['bill_to_address'] ?: null,
            'email'     => $prefill['bill_to_email'] ?: null,
            'phone'     => $prefill['bill_to_phone'] ?: null,
            'tax_id'    => $prefill['bill_to_tax_id'] ?: null,
        ], fn($v) => $v !== null);

        try {
            $invoiceId = issue_manual_invoice(
                $billTo,
                $lineItems,
                (int) current_user_id(),
                (float) $prefill['tax'],
                'MYR',
                $prefill['notes'] ?: null
            );
            flash('invoice', 'Manual invoice created.', 'success');
            redirect('/admin/invoices.php');
        } catch (Throwable $e) {
            $errors[] = 'Could not create invoice: ' . $e->getMessage();
        }
    }
}

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">New invoice</h1>
    <p class="text-beige-100/60 mt-1 text-sm">B2B billing for speaker fees, corporate sessions, sponsorships — any invoice that isn't tied to a member booking.</p>
  </div>
  <a href="<?= url('/admin/invoices.php') ?>" class="text-xs text-beige-100/60 hover:text-gold-400">← All invoices</a>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" class="mt-8 space-y-8 max-w-4xl">
  <?= csrf_field() ?>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-4">
    <div>
      <h2 class="font-serif text-xl text-gold-400">Bill to</h2>
      <p class="text-[11px] text-beige-100/45 mt-1">Snapshot at issue time — the printed invoice always shows these details, even if the company later changes address.</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block sm:col-span-2">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Company name</span>
        <input name="bill_to_name" required maxlength="200"
               value="<?= e($prefill['bill_to_name']) ?>"
               placeholder="e.g. Hospital Pantai Indah Sdn Bhd"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Attention <span class="text-beige-100/30">(optional)</span></span>
        <input name="bill_to_attention" maxlength="160"
               value="<?= e($prefill['bill_to_attention']) ?>"
               placeholder="e.g. Samiha Aziz"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Tax / registration ID <span class="text-beige-100/30">(optional)</span></span>
        <input name="bill_to_tax_id" maxlength="80"
               value="<?= e($prefill['bill_to_tax_id']) ?>"
               placeholder="e.g. SST no. / BRN"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block sm:col-span-2">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Address</span>
        <textarea name="bill_to_address" rows="3"
                  placeholder="Jalan Perubatan 1, Pandan Indah&#10;55100 Kuala Lumpur&#10;Malaysia"
                  class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e($prefill['bill_to_address']) ?></textarea>
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Email <span class="text-beige-100/30">(optional)</span></span>
        <input name="bill_to_email" type="email" maxlength="180"
               value="<?= e($prefill['bill_to_email']) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Phone <span class="text-beige-100/30">(optional)</span></span>
        <input name="bill_to_phone" maxlength="60"
               value="<?= e($prefill['bill_to_phone']) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
    </div>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-4"
           x-data="{
             items: <?= htmlspecialchars(json_encode(array_values(array_map(fn($i) => [
                 'description' => (string) ($i['description'] ?? ''),
                 'quantity'    => (float)  ($i['quantity']    ?? 1),
                 'unit_price'  => (float)  ($i['unit_price']  ?? 0),
             ], $prefill['items']))), ENT_QUOTES, 'UTF-8') ?>,
             tax: <?= (float) $prefill['tax'] ?>,
             add() { this.items.push({ description: '', quantity: 1, unit_price: 0 }); },
             remove(i) { this.items.splice(i, 1); if (!this.items.length) this.add(); },
             lineTotal(i) { return (Number(i.quantity) || 0) * (Number(i.unit_price) || 0); },
             get subtotal() { return this.items.reduce((s, i) => s + this.lineTotal(i), 0); },
             get grandTotal() { return this.subtotal + (Number(this.tax) || 0); },
             fmt(n) { return 'MYR ' + Number(n || 0).toFixed(2); }
           }">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h2 class="font-serif text-xl text-gold-400">Line items</h2>
        <p class="text-[11px] text-beige-100/45 mt-1">e.g. "Speaker fee — Gong Bath Wellness Morning · 10 Jul 2026".</p>
      </div>
      <button type="button" @click="add()" class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 text-sm">+ Add line</button>
    </div>

    <div class="space-y-3">
      <template x-for="(item, i) in items" :key="i">
        <div class="grid grid-cols-12 gap-3 items-start rounded-2xl border border-white/10 bg-navy-950/40 p-4">
          <label class="block col-span-12 sm:col-span-6">
            <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Description</span>
            <input :name="`items[${i}][description]`" x-model="item.description"
                   placeholder="Speaker fee — Gong Bath session"
                   class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5">
          </label>
          <label class="block col-span-4 sm:col-span-2">
            <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Qty</span>
            <input :name="`items[${i}][quantity]`" x-model.number="item.quantity" type="number" step="1" min="1"
                   class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-right">
          </label>
          <label class="block col-span-4 sm:col-span-2">
            <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Unit price</span>
            <input :name="`items[${i}][unit_price]`" x-model.number="item.unit_price" type="number" step="0.01" min="0"
                   class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-right">
          </label>
          <div class="col-span-4 sm:col-span-2 text-right">
            <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Line total</p>
            <p class="mt-1 font-serif text-lg text-gold-400" x-text="fmt(lineTotal(item))"></p>
            <button type="button" @click="remove(i)" x-show="items.length > 1"
                    class="mt-2 text-[10px] uppercase tracking-widest text-red-300/70 hover:text-red-300">Remove</button>
          </div>
        </div>
      </template>
    </div>

    <div class="grid sm:grid-cols-2 gap-6 pt-2 items-end">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Tax (MYR)</span>
        <input name="tax" type="number" step="0.01" min="0"
               x-model.number="tax"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-right">
        <span class="text-[11px] text-beige-100/40 mt-1 block">SST or similar — leave 0 if none.</span>
      </label>
      <div class="rounded-2xl border border-gold-500/30 bg-gold-500/5 p-5 text-right">
        <p class="text-[11px] uppercase tracking-widest text-gold-400/80">Total</p>
        <p class="mt-1 font-serif text-3xl text-gold-400" x-text="fmt(grandTotal)"></p>
        <p class="text-[11px] text-beige-100/50 mt-1">Subtotal <span x-text="fmt(subtotal)"></span></p>
      </div>
    </div>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Notes <span class="text-beige-100/30">(optional)</span></span>
      <textarea name="notes" rows="4"
                placeholder="Bank transfer to Maybank … / DuitNow …"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e($prefill['notes']) ?></textarea>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Prints under the total on the invoice — great for payment instructions or reference numbers.</span>
    </label>
  </section>

  <div class="flex items-center gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Create invoice</button>
    <a href="<?= url('/admin/invoices.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
