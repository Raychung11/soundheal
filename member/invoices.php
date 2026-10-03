<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Invoices & receipts';
$user = current_user();

$stmt = db()->prepare(
    "SELECT id, doc_type, doc_number, purpose, reference_id, total, currency, status,
            issued_at, paid_at, access_token
     FROM invoices
     WHERE user_id = :u
     ORDER BY issued_at DESC, id DESC
     LIMIT 200"
);
$stmt->execute([':u' => $user['id']]);
$rows = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-5xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Documents</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4">Invoices &amp; receipts</h1>
  <p class="mt-3 text-beige-100/60 text-sm">Every booking and membership generates an invoice. Once it's paid, a matching receipt is issued automatically.</p>

  <?php if (!$rows): ?>
    <div class="mt-12 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="text-beige-100/70">No documents yet.</p>
      <a href="<?= url('/public/events.php') ?>" class="mt-6 inline-block px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">Browse sessions</a>
    </div>
  <?php else: ?>
    <div class="mt-10 border border-white/5 rounded-3xl bg-navy-900/40 overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
          <tr>
            <th class="px-4 py-3">Number</th>
            <th>Type</th>
            <th>Issued</th>
            <th>For</th>
            <th class="text-right">Amount</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($rows as $r): ?>
            <tr>
              <td class="px-4 py-3 font-mono text-beige-100"><?= e($r['doc_number'] ?? '—') ?></td>
              <td class="capitalize"><?= e($r['doc_type']) ?></td>
              <td><?= e(format_datetime($r['issued_at'], 'd M Y')) ?></td>
              <td class="capitalize text-beige-100/65"><?= e($r['purpose']) ?> #<?= (int) $r['reference_id'] ?></td>
              <td class="text-right"><?= e(format_money((float) $r['total'], (string) $r['currency'])) ?></td>
              <td>
                <span class="text-xs px-2 py-1 rounded-full <?= $r['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/65' ?>">
                  <?= e($r['status']) ?>
                </span>
              </td>
              <td class="text-right pr-4">
                <a href="<?= url('/member/document.php?id=' . (int) $r['id']) ?>" class="text-gold-400/85 hover:text-gold-300 text-sm">View →</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
