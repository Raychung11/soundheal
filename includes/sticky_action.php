<?php
/**
 * Mobile-only sticky bottom action bar — sits above the mobile bottom nav.
 *
 *   A page sets $stickyAction before requiring this include (which must
 *   happen BEFORE require footer.php, so the bar renders in the DOM
 *   before mobile_nav.php and the spacer chain stays clean).
 *
 *     $stickyAction = [
 *       'label'  => 'Reserve · RM 65',
 *       'href'   => '/public/events.php',
 *       // OR submit an existing form by its id:
 *       'submit' => 'bookForm',
 *       'meta'   => '1 credit will be redeemed',   // optional sub-line
 *       'style'  => 'primary',                     // 'primary' | 'secondary'
 *     ];
 *
 *   Positioned by inline CSS so it always clears the iPhone safe-area
 *   below the nav. Hidden on md+ (desktop uses the in-page CTA).
 */
if (empty($stickyAction) || !is_array($stickyAction)) return;
$sa     = $stickyAction;
$label  = (string) ($sa['label']  ?? 'Continue');
$href   = (string) ($sa['href']   ?? '');
$submit = (string) ($sa['submit'] ?? '');
$meta   = (string) ($sa['meta']   ?? '');
$style  = (string) ($sa['style']  ?? 'primary');
$btnCls = $style === 'secondary'
    ? 'border border-gold-500/50 text-gold-400 bg-navy-950/60 hover:bg-gold-500/10'
    : 'bg-gold-500 text-navy-950 hover:bg-gold-400';
?>
<div class="md:hidden fixed inset-x-0 z-40 bg-navy-950/95 backdrop-blur border-t border-white/10 px-4 py-3"
     style="bottom: calc(64px + env(safe-area-inset-bottom));">
  <?php if ($meta !== ''): ?>
    <p class="text-[11px] text-beige-100/55 text-center mb-2"><?= e($meta) ?></p>
  <?php endif; ?>
  <?php if ($submit !== ''): ?>
    <button type="submit" form="<?= e($submit) ?>"
            class="w-full py-3.5 rounded-full font-medium transition <?= $btnCls ?>"><?= e($label) ?></button>
  <?php else: ?>
    <a href="<?= url($href) ?>"
       class="block text-center py-3.5 rounded-full font-medium transition <?= $btnCls ?>"><?= e($label) ?></a>
  <?php endif; ?>
</div>
<div class="md:hidden h-20" aria-hidden="true"></div>
