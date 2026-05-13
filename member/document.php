<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$id    = (int) input('id', 0);
$token = trim((string) input('t', ''));

if ($id <= 0) {
    http_response_code(400);
    exit('Missing document id.');
}

$stmt = db()->prepare("SELECT * FROM invoices WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$doc = $stmt->fetch();
if (!$doc) {
    http_response_code(404);
    exit('Document not found.');
}

// Access: either the document's access_token (used in emailed links)
// or the logged-in customer / staff.
$tokenOk = $token !== '' && hash_equals((string) $doc['access_token'], $token);
$userOk  = is_logged_in() && (
    (int) $doc['user_id'] === current_user_id()
    || has_role('admin', 'staff')
);
if (!$tokenOk && !$userOk) {
    http_response_code(403);
    exit('You do not have permission to view this document.');
}

$lineItems = json_decode((string) ($doc['line_items'] ?? ''), true) ?: [];
$customer  = json_decode((string) ($doc['customer_snapshot'] ?? ''), true) ?: [];
$company   = json_decode((string) ($doc['company_snapshot']  ?? ''), true) ?: [];

$isReceipt = $doc['doc_type'] === 'receipt';
$docLabel  = $isReceipt ? 'Receipt' : 'Invoice';
$statusLabel = match ($doc['status']) {
    'paid'     => 'Paid',
    'due'      => 'Amount due',
    'refunded' => 'Refunded',
    'void'     => 'Void',
    default    => ucfirst((string) $doc['status']),
};
$currency = (string) $doc['currency'];

// Optional: link to the matching invoice/receipt if it exists.
$paired = null;
if ($isReceipt && !empty($doc['invoice_id'])) {
    $p = db()->prepare("SELECT id, doc_number, access_token FROM invoices WHERE id = :id");
    $p->execute([':id' => $doc['invoice_id']]);
    $paired = $p->fetch() ?: null;
} elseif (!$isReceipt) {
    $p = db()->prepare("SELECT id, doc_number, access_token FROM invoices WHERE doc_type='receipt' AND invoice_id = :id ORDER BY id DESC LIMIT 1");
    $p->execute([':id' => $doc['id']]);
    $paired = $p->fetch() ?: null;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($docLabel . ' · ' . ($doc['doc_number'] ?: '—')) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    :root {
      --ink: #1a1d2b;
      --muted: #6b7180;
      --hairline: #e7e6e1;
      --paper: #ffffff;
      --warm: #faf8f4;
      --gold: #b08746;
    }
    * { box-sizing: border-box; }
    html, body { background: var(--warm); margin: 0; padding: 0; color: var(--ink); font-family: 'Inter', system-ui, sans-serif; font-size: 14px; line-height: 1.55; }
    .toolbar { position: sticky; top: 0; background: #0a1027; color: #e7d2a3; padding: 12px 16px; display: flex; gap: 12px; justify-content: center; align-items: center; z-index: 10; }
    .toolbar button, .toolbar a {
      background: #c9a46a; color: #0a1027; padding: 8px 16px; border-radius: 999px;
      border: 0; font-weight: 500; font-size: 13px; cursor: pointer; text-decoration: none;
    }
    .toolbar a.ghost { background: transparent; color: #e7d2a3; border: 1px solid rgba(255,255,255,0.2); }
    .page {
      max-width: 800px;
      margin: 28px auto;
      background: var(--paper);
      border: 1px solid var(--hairline);
      border-radius: 12px;
      padding: 56px 56px 48px;
      box-shadow: 0 30px 70px -40px rgba(0,0,0,0.25);
    }
    h1, h2, h3 { font-family: 'Cormorant Garamond', serif; font-weight: 600; margin: 0; color: var(--ink); }
    .header {
      display: grid; grid-template-columns: 1fr auto; gap: 24px; align-items: start; padding-bottom: 32px;
      border-bottom: 1px solid var(--hairline);
    }
    .brand { font-family: 'Cormorant Garamond', serif; font-size: 32px; color: var(--gold); letter-spacing: 0.5px; }
    .tagline { text-transform: uppercase; letter-spacing: 0.32em; color: var(--muted); font-size: 10px; margin-top: 4px; }
    .doc-title { text-align: right; }
    .doc-title h1 { font-size: 30px; letter-spacing: 0.04em; }
    .doc-meta { text-align: right; color: var(--muted); font-size: 12px; margin-top: 6px; line-height: 1.7; }
    .doc-number { font-family: 'Inter'; font-weight: 600; color: var(--ink); }
    .pill { display: inline-block; padding: 4px 10px; border-radius: 999px; font-size: 10px; letter-spacing: 0.25em; text-transform: uppercase; }
    .pill.paid { background: rgba(176, 135, 70, 0.12); color: var(--gold); }
    .pill.due  { background: #f1eee7; color: var(--ink); }
    .pill.refunded { background: #fbe9e6; color: #b14628; }
    .pill.void { background: #f1eee7; color: var(--muted); }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-top: 32px; }
    .label { text-transform: uppercase; letter-spacing: 0.28em; font-size: 10px; color: var(--muted); margin-bottom: 8px; }
    .party { line-height: 1.65; }
    .party strong { display: block; font-weight: 500; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 28px; }
    table.items thead th {
      text-align: left; text-transform: uppercase; letter-spacing: 0.28em; font-size: 10px;
      color: var(--muted); font-weight: 500; padding: 12px 0; border-bottom: 1px solid var(--hairline);
    }
    table.items thead th.right { text-align: right; }
    table.items tbody td { padding: 16px 0; border-bottom: 1px solid var(--hairline); vertical-align: top; }
    table.items tbody td.right { text-align: right; }
    table.items .desc-sub { color: var(--muted); font-size: 12px; margin-top: 2px; }
    .totals { margin-top: 24px; display: grid; grid-template-columns: 1fr auto; gap: 6px 32px; }
    .totals .label-line { color: var(--muted); }
    .totals .value { text-align: right; }
    .totals .total {
      font-family: 'Cormorant Garamond', serif; font-size: 26px; color: var(--ink); padding-top: 12px;
      border-top: 1px solid var(--ink);
    }
    .totals .total-label {
      font-family: 'Cormorant Garamond', serif; font-size: 18px; color: var(--ink); padding-top: 12px;
      border-top: 1px solid var(--ink);
    }
    .meta-row { display: flex; flex-wrap: wrap; gap: 12px 28px; padding: 18px 0; border-top: 1px solid var(--hairline); margin-top: 30px; color: var(--muted); font-size: 12px; }
    .footer { margin-top: 28px; padding-top: 18px; border-top: 1px solid var(--hairline); color: var(--muted); font-size: 11px; line-height: 1.7; }
    @media print {
      .toolbar { display: none; }
      html, body { background: #fff; }
      .page { box-shadow: none; border: 0; border-radius: 0; margin: 0; padding: 18mm; max-width: none; }
      @page { size: A4; margin: 0; }
    }
  </style>
</head>
<body>

<div class="toolbar">
  <button onclick="window.print()">Print / Save as PDF</button>
  <?php if ($paired): ?>
    <a class="ghost" href="<?= url('/member/document.php?id=' . (int) $paired['id'] . '&t=' . urlencode((string) $paired['access_token'])) ?>">
      View paired <?= $isReceipt ? 'invoice' : 'receipt' ?> · <?= e($paired['doc_number']) ?>
    </a>
  <?php endif; ?>
  <?php if (is_logged_in()): ?>
    <a class="ghost" href="<?= url(has_role('admin','staff') ? '/admin/invoices.php' : '/member/invoices.php') ?>">All documents</a>
  <?php endif; ?>
</div>

<div class="page">
  <header class="header">
    <div>
      <div class="brand"><?= e($company['brand'] ?? brand_name()) ?></div>
      <?php if (!empty($company['legal_name'])): ?>
        <div style="color: var(--muted); font-size: 12px; margin-top: 4px;"><?= e($company['legal_name']) ?>
          <?php if (!empty($company['registration'])): ?> · Reg. <?= e($company['registration']) ?><?php endif; ?>
        </div>
      <?php endif; ?>
      <div class="tagline" style="margin-top: 14px;"><?= e((string) setting('company_tagline', 'Sound healing sanctuary')) ?></div>
    </div>
    <div class="doc-title">
      <h1><?= e($docLabel) ?></h1>
      <div class="doc-meta">
        <span class="doc-number"><?= e($doc['doc_number'] ?? '—') ?></span><br>
        Issued <?= e(format_datetime((string)$doc['issued_at'], 'd M Y')) ?><br>
        <?php if ($doc['paid_at']): ?>
          Paid <?= e(format_datetime((string)$doc['paid_at'], 'd M Y')) ?><br>
        <?php endif; ?>
        <span class="pill <?= e($doc['status']) ?>"><?= e($statusLabel) ?></span>
      </div>
    </div>
  </header>

  <div class="grid-2">
    <div>
      <p class="label">From</p>
      <div class="party">
        <strong><?= e($company['legal_name'] ?? ($company['brand'] ?? brand_name())) ?></strong>
        <?php foreach (($company['address'] ?? []) as $line): ?>
          <div><?= e($line) ?></div>
        <?php endforeach; ?>
        <?php if (!empty($company['phone'])): ?><div><?= e($company['phone']) ?></div><?php endif; ?>
        <?php if (!empty($company['email'])): ?><div><?= e($company['email']) ?></div><?php endif; ?>
      </div>
    </div>
    <div>
      <p class="label">Billed to</p>
      <div class="party">
        <strong><?= e($customer['name'] ?? '') ?></strong>
        <?php if (!empty($customer['email'])): ?><div><?= e($customer['email']) ?></div><?php endif; ?>
        <?php if (!empty($customer['phone'])): ?><div><?= e($customer['phone']) ?></div><?php endif; ?>
      </div>
    </div>
  </div>

  <table class="items">
    <thead>
      <tr>
        <th>Description</th>
        <th class="right" style="width: 60px;">Qty</th>
        <th class="right" style="width: 120px;">Unit price</th>
        <th class="right" style="width: 120px;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($lineItems as $line): ?>
        <tr>
          <td>
            <?= e((string) ($line['description'] ?? '')) ?>
            <?php if (!empty($line['subtext'])): ?>
              <div class="desc-sub"><?= e((string) $line['subtext']) ?></div>
            <?php endif; ?>
          </td>
          <td class="right"><?= (int) ($line['quantity'] ?? 1) ?></td>
          <td class="right"><?= e(format_money((float)($line['unit_price'] ?? 0), $currency)) ?></td>
          <td class="right"><?= e(format_money((float)($line['amount'] ?? 0), $currency)) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="totals">
    <span class="label-line">Subtotal</span>
    <span class="value"><?= e(format_money((float) $doc['subtotal'], $currency)) ?></span>
    <?php if ((float) $doc['tax'] > 0): ?>
      <span class="label-line">Tax</span>
      <span class="value"><?= e(format_money((float) $doc['tax'], $currency)) ?></span>
    <?php endif; ?>
    <span class="total-label"><?= $isReceipt ? 'Total paid' : 'Amount due' ?></span>
    <span class="total"><?= e(format_money((float) $doc['total'], $currency)) ?></span>
  </div>

  <div class="meta-row">
    <span><strong>Payment method:</strong> Billplz</span>
    <?php if ($isReceipt && !empty($doc['payment_id'])): ?>
      <?php
        $pm = db()->prepare("SELECT gateway_bill_id FROM payments WHERE id = :id");
        $pm->execute([':id' => (int) $doc['payment_id']]);
        $billId = (string) ($pm->fetchColumn() ?: '');
      ?>
      <?php if ($billId !== ''): ?>
        <span><strong>Billplz reference:</strong> <?= e($billId) ?></span>
      <?php endif; ?>
    <?php endif; ?>
    <?php if (!empty($doc['reference_id'])): ?>
      <span><strong>Booking / membership #:</strong> <?= (int) $doc['reference_id'] ?></span>
    <?php endif; ?>
  </div>

  <div class="footer">
    <?php if (!$isReceipt): ?>
      <p>Please complete payment via the original checkout link. If you have already paid, the matching receipt will be issued automatically once the payment is confirmed.</p>
    <?php endif; ?>
    <p>This is a computer-generated document and does not require a signature. Questions? Please email <a href="mailto:<?= e($company['email'] ?? setting('company_email', '')) ?>" style="color: var(--gold);"><?= e($company['email'] ?? setting('company_email', '')) ?></a>.</p>
    <?php if (!empty($company['registration'])): ?>
      <p style="color: var(--muted); margin-top: 12px;">Issued by <?= e($company['legal_name'] ?? ($company['brand'] ?? '')) ?>, Reg. <?= e($company['registration']) ?>.</p>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
