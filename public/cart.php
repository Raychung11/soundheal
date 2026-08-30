<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Cart';

$formErrors = [];
$shipInput  = $_SESSION['cart_ship_to'] ?? [];

// Simple GET remove link (idempotent, cart-only mutation).
if (($rid = (int) input('remove', 0)) > 0) {
    cart_remove($rid);
    redirect('/public/cart.php');
}

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    if ($action === 'update') {
        $qtys = $_POST['qty'] ?? [];
        if (is_array($qtys)) {
            foreach ($qtys as $pid => $q) {
                cart_set((int) $pid, (int) $q);
            }
        }
        flash('cart', 'Cart updated.', 'success');
        redirect('/public/cart.php');
    }

    if ($action === 'remove') {
        $pid = (int) input('id', 0);
        if ($pid > 0) cart_remove($pid);
        redirect('/public/cart.php');
    }

    if ($action === 'clear') {
        cart_clear();
        redirect('/public/cart.php');
    }

    if ($action === 'checkout') {
        require_login('/public/login.php');
        $user = current_user();

        $shipInput = [
            'name'          => trim((string) input('ship_name', $user['full_name'] ?? '')),
            'phone'         => trim((string) input('ship_phone', $user['phone'] ?? '')),
            'address_line1' => trim((string) input('ship_address_line1', '')),
            'address_line2' => trim((string) input('ship_address_line2', '')),
            'city'          => trim((string) input('ship_city', '')),
            'postcode'      => trim((string) input('ship_postcode', '')),
            'country'       => trim((string) input('ship_country', 'Malaysia')),
            'notes'         => trim((string) input('ship_notes', '')),
        ];
        $_SESSION['cart_ship_to'] = $shipInput;

        if ($shipInput['name'] === '')          $formErrors[] = 'Please give a delivery name.';
        if ($shipInput['phone'] === '')         $formErrors[] = 'Please give a contact number so we can update you on shipping.';
        if ($shipInput['address_line1'] === '') $formErrors[] = 'Address is required.';
        if ($shipInput['city'] === '')          $formErrors[] = 'City is required.';

        $cart = cart_hydrate();
        if (empty($cart['items'])) {
            $formErrors[] = 'Your cart is empty.';
        }

        if (!$formErrors) {
            $orderId = checkout_create_order((int) $user['id'], $shipInput);
            if ($orderId <= 0) {
                $formErrors[] = 'Could not create your order. Please try again in a moment.';
            } else {
                // Cart clears only after Billplz redirects the user
                // through settle. Handing the order id to billplz_create
                // sends them onward to pay.
                unset($_SESSION['cart_ship_to']);
                cart_clear();
                redirect('/api/billplz_create.php?purpose=order&ref=' . $orderId);
            }
        }
    }
}

$cart = cart_hydrate();
$user = current_user();

require __DIR__ . '/../includes/header.php';
?>

<section class="max-w-5xl mx-auto px-6 pt-12 pb-24">
  <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Cart</p>
  <h1 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4">Your basket</h1>

  <?php if ($f = flash('cart')): ?>
    <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
  <?php endif; ?>

  <?php if (!$cart['items']): ?>
    <div class="mt-10 border border-white/5 rounded-3xl p-10 bg-navy-900/40 text-center">
      <p class="text-beige-100/70">Your cart is empty.</p>
      <a href="<?= url('/public/shop.php') ?>" class="mt-6 inline-block px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Browse the shop</a>
    </div>
  <?php else: ?>
    <form method="post" class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <table class="w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
          <tr>
            <th class="py-2">Item</th>
            <th class="w-24">Qty</th>
            <th class="text-right">Amount</th>
            <th></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($cart['items'] as $it): ?>
            <tr>
              <td class="py-4">
                <div class="flex items-center gap-4">
                  <?php if (!empty($it['cover_image'])): ?>
                    <img src="<?= e(media_src((string)$it['cover_image'])) ?>" alt="" class="w-16 h-16 object-cover rounded-xl">
                  <?php else: ?>
                    <div class="w-16 h-16 rounded-xl bg-white/5"></div>
                  <?php endif; ?>
                  <div>
                    <p class="text-beige-100"><?= e((string)$it['title']) ?></p>
                    <p class="text-[11px] text-beige-100/50 mt-0.5"><?= e(format_money((float)$it['price'])) ?> each</p>
                    <?php if ((int)$it['is_preorder']): ?>
                      <p class="text-[11px] text-amber-300 mt-0.5">Pre-order<?= $it['preorder_eta'] ? ' · ' . e((string)$it['preorder_eta']) : '' ?></p>
                    <?php endif; ?>
                  </div>
                </div>
              </td>
              <td>
                <input name="qty[<?= (int)$it['id'] ?>]" type="number" min="0" max="99" value="<?= (int)$it['qty'] ?>"
                       class="w-20 rounded-xl bg-navy-950 border border-white/10 px-3 py-2 text-beige-100 focus:border-gold-500/50 focus:outline-none">
              </td>
              <td class="text-right text-beige-100"><?= e(format_money((float)$it['line_total'])) ?></td>
              <td class="text-right pl-4">
                <a href="<?= url('/public/cart.php?remove=' . (int)$it['id']) ?>"
                   class="text-xs text-beige-100/50 hover:text-red-300"
                   title="Remove">×</a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="text-beige-100 text-base">
            <td colspan="2" class="pt-6 text-right text-beige-100/60">Subtotal</td>
            <td class="pt-6 text-right"><?= e(format_money((float)$cart['subtotal'])) ?></td>
            <td></td>
          </tr>
        </tfoot>
      </table>
      <div class="mt-6 flex flex-wrap gap-3">
        <button class="px-5 py-2 rounded-full border border-white/10 text-sm text-beige-100/85 hover:border-gold-500/50 hover:text-gold-400">Update quantities</button>
        <a href="<?= url('/public/shop.php') ?>" class="px-5 py-2 rounded-full border border-white/10 text-sm text-beige-100/85 hover:border-gold-500/50 hover:text-gold-400">Keep shopping</a>
      </div>
    </form>

    <?php if ($cart['has_preorder']): ?>
      <div class="mt-6 border border-amber-500/30 bg-amber-500/5 rounded-2xl px-5 py-4 text-sm text-amber-200">
        Your basket contains a pre-order item. Payment is taken now to reserve your piece; we'll dispatch the moment it's finished. Live-stock items ship together with the pre-order (or sooner on request).
      </div>
    <?php endif; ?>

    <form method="post" class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="checkout">
      <h2 class="font-serif text-2xl text-gold-400">Delivery</h2>

      <?php if ($formErrors): ?>
        <div class="mt-4 border border-red-400/40 bg-red-500/5 text-red-200 rounded-2xl px-5 py-4 text-sm">
          <?php foreach ($formErrors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!$user): ?>
        <div class="mt-4 border border-white/10 rounded-2xl px-5 py-4 text-sm text-beige-100/80">
          Please <a href="<?= url('/public/login.php') ?>" class="text-gold-400 hover:text-gold-300">sign in</a> or
          <a href="<?= url('/public/register.php') ?>" class="text-gold-400 hover:text-gold-300">create an account</a> to check out.
        </div>
      <?php endif; ?>

      <div class="grid sm:grid-cols-2 gap-4 mt-4">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Recipient name</span>
          <input name="ship_name" required value="<?= e((string)($shipInput['name'] ?? ($user['full_name'] ?? ''))) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Contact number</span>
          <input name="ship_phone" required value="<?= e((string)($shipInput['phone'] ?? ($user['phone'] ?? ''))) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
      </div>

      <label class="block mt-4">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Address line 1</span>
        <input name="ship_address_line1" required value="<?= e((string)($shipInput['address_line1'] ?? '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block mt-4">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Address line 2 (optional)</span>
        <input name="ship_address_line2" value="<?= e((string)($shipInput['address_line2'] ?? '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>

      <div class="grid sm:grid-cols-3 gap-4 mt-4">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Postcode</span>
          <input name="ship_postcode" value="<?= e((string)($shipInput['postcode'] ?? '')) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block sm:col-span-1">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">City</span>
          <input name="ship_city" required value="<?= e((string)($shipInput['city'] ?? '')) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Country</span>
          <input name="ship_country" value="<?= e((string)($shipInput['country'] ?? 'Malaysia')) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
      </div>

      <label class="block mt-4">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Notes for the courier (optional)</span>
        <textarea name="ship_notes" rows="2" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e((string)($shipInput['notes'] ?? '')) ?></textarea>
      </label>

      <div class="mt-6 flex items-center justify-between gap-4">
        <p class="text-beige-100/70 text-sm">Total to pay <span class="text-beige-100 font-serif text-xl ml-2"><?= e(format_money((float)$cart['subtotal'])) ?></span></p>
        <button class="px-8 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Pay with Billplz →</button>
      </div>
      <p class="text-[11px] text-beige-100/40 mt-3">Shipping will be quoted separately for now — we'll message you before dispatch if postage exceeds courier-standard.</p>
    </form>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
