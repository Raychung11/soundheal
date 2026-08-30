<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

/**
 * Print-friendly A5 poster for a partner. Renders a standalone page
 * (no admin nav / no site header) so the browser's "Save as PDF" gives
 * a clean edge-to-edge artwork. Everything is styled in the print block
 * below so screen preview matches paper.
 *
 * URL: /admin/partner_poster.php?id=<partner-id>
 */

$id = (int) input('id', 0);
$partner = find_partner_by_id($id);
if (!$partner) {
    http_response_code(404);
    echo 'Partner not found.';
    exit;
}

$shareUrl   = partner_share_url($partner);
$qrImageUrl = partner_qr_image_url($partner, 640);
$promoCode  = trim((string) ($partner['first_visit_promo_code'] ?? ''));
$brand      = brand_name();
// Human-readable form of the same URL the QR encodes, so someone who
// can't scan can still type it and land on the real endpoint. Strips
// the leading scheme (host + path) since the "https://" is understood
// on a printed poster.
$shareUrlDisplay = (string) preg_replace('~^https?://~', '', $shareUrl);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Poster · <?= e($partner['name']) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<style>
  :root {
    --navy-950: #0b1220;
    --navy-900: #111c30;
    --gold-500: #c9a46a;
    --gold-400: #d9b988;
    --beige-100: #efe7d6;
  }
  * { box-sizing: border-box; }
  html, body { margin: 0; padding: 0; background: #2b2e3a; color: var(--beige-100);
    font-family: 'Inter', system-ui, sans-serif; }
  .toolbar {
    position: sticky; top: 0; z-index: 10;
    background: var(--navy-950); border-bottom: 1px solid rgba(255,255,255,0.05);
    padding: 12px 20px; display: flex; gap: 12px; align-items: center; flex-wrap: wrap;
  }
  .toolbar a, .toolbar button {
    display: inline-block; padding: 8px 16px; border-radius: 999px;
    text-decoration: none; font-size: 13px; cursor: pointer;
    background: transparent; border: 1px solid rgba(255,255,255,0.1);
    color: var(--beige-100);
  }
  .toolbar .primary { background: var(--gold-500); color: var(--navy-950); border-color: var(--gold-500); font-weight: 500; }
  .toolbar .muted { color: rgba(239,231,214,0.55); font-size: 12px; }
  .canvas { padding: 32px 20px; display: flex; justify-content: center; }
  .poster {
    width: 148mm; height: 210mm;   /* A5 portrait */
    background: linear-gradient(180deg, #0b1220 0%, #101a2e 100%);
    color: var(--beige-100);
    display: flex; flex-direction: column;
    padding: 18mm 14mm 14mm; position: relative;
    overflow: hidden;
    box-shadow: 0 40px 80px -20px rgba(0,0,0,0.6);
  }
  .poster::before {
    content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6mm;
    background: linear-gradient(90deg, transparent, var(--gold-500), transparent); opacity: 0.35;
  }
  .eyebrow { text-transform: uppercase; letter-spacing: 0.32em; font-size: 10px;
             color: var(--gold-400); margin: 0 0 4mm; }
  .brand   { font-family: 'Cormorant Garamond', serif; font-weight: 600; font-size: 20pt;
             letter-spacing: 0.02em; margin: 0; }
  .head    { font-family: 'Cormorant Garamond', serif; font-size: 30pt; line-height: 1.05;
             margin: 8mm 0 3mm; font-weight: 500; }
  .lede    { font-size: 12pt; line-height: 1.5; color: rgba(239,231,214,0.75); margin: 0 0 6mm; max-width: 110mm; }
  .qr-wrap {
    margin: auto 0; align-self: center;
    background: #fff; padding: 6mm; border-radius: 4mm;
    box-shadow: 0 10px 30px -10px rgba(201,164,106,0.35);
  }
  .qr-wrap img { display: block; width: 74mm; height: 74mm; }
  .promo   {
    margin: 4mm auto 0; padding: 3mm 6mm; border: 1px dashed var(--gold-500);
    border-radius: 3mm; align-self: center; text-align: center;
    background: rgba(201,164,106,0.08);
  }
  .promo .lbl { font-size: 8pt; text-transform: uppercase; letter-spacing: 0.24em;
                color: rgba(217,185,136,0.85); margin: 0 0 1mm; }
  .promo .code { font-family: 'Inter', monospace; font-size: 18pt; letter-spacing: 0.12em;
                 color: var(--gold-500); margin: 0; }
  .foot    { margin-top: auto; padding-top: 6mm; border-top: 1px solid rgba(255,255,255,0.08);
             font-size: 9pt; color: rgba(239,231,214,0.55); display: flex; justify-content: space-between; gap: 6mm; }
  .foot .partner { color: rgba(239,231,214,0.75); }
  .steps   { font-size: 10pt; line-height: 1.6; color: rgba(239,231,214,0.7); margin: 4mm 0 0; padding: 0; list-style: none; }
  .steps li { padding-left: 8mm; position: relative; margin-bottom: 2mm; }
  .steps li::before { content: ''; position: absolute; left: 0; top: 3.5mm;
                      width: 4mm; height: 1px; background: var(--gold-500); opacity: 0.6; }

  @media print {
    body { background: #fff; }
    .toolbar { display: none; }
    .canvas  { padding: 0; }
    .poster  {
      width: 148mm; height: 210mm;
      box-shadow: none;
      -webkit-print-color-adjust: exact;
      print-color-adjust: exact;
    }
    @page { size: A5 portrait; margin: 0; }
  }
</style>
</head>
<body>
  <div class="toolbar">
    <a class="primary" href="javascript:window.print()">Print or save as PDF</a>
    <a href="<?= e(url('/admin/partners.php?edit=' . (int) $partner['id'])) ?>">← Back to partner</a>
    <span class="muted">A5 portrait · trims to edge with borderless print</span>
    <span class="muted">·</span>
    <span class="muted">Scan URL: <?= e($shareUrl) ?></span>
  </div>

  <div class="canvas">
    <article class="poster">
      <p class="eyebrow">Community partner</p>
      <p class="brand"><?= e($brand) ?></p>

      <h1 class="head">A quiet hour of sound, on us.</h1>
      <p class="lede">
        Our friends at <strong><?= e($partner['name']) ?></strong> invite you to slow down with a live sound-bath session. Scan · book · exhale.
      </p>

      <ul class="steps">
        <li>Scan the code with your phone camera.</li>
        <li>Choose a date that fits — sessions run weekly.</li>
        <li>We hold your seat; pay online or on the day.</li>
      </ul>

      <div class="qr-wrap">
        <img src="<?= e($qrImageUrl) ?>" alt="Scan to book" width="640" height="640">
      </div>

      <?php if ($promoCode !== ''): ?>
        <div class="promo">
          <p class="lbl">First-visit code</p>
          <p class="code"><?= e($promoCode) ?></p>
        </div>
      <?php endif; ?>

      <div class="foot">
        <span>Or type <?= e($shareUrlDisplay) ?></span>
        <span class="partner">With <?= e($partner['name']) ?></span>
      </div>
    </article>
  </div>
</body>
</html>
