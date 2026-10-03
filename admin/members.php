<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Members';

$q       = trim((string) input('q', ''));
$missing = input('missing_phone') !== null;

$where  = ["1=1"];
$params = [];
if ($q !== '') {
    // LIKE across the fields an admin actually types when looking
    // someone up — name, email, phone (any partial digits).
    $where[] = "(u.full_name LIKE :q OR u.email LIKE :q OR u.phone LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($missing) {
    // Quick filter: "show me every member without a phone number"
    // — most useful for chasing down Google-registered members who
    // haven't added their number yet.
    $where[] = "(u.phone IS NULL OR u.phone = '')";
}

$sql = "SELECT u.id, u.full_name, u.email, u.phone, u.status, u.created_at,
               r.name AS role,
               (SELECT p.name FROM memberships m JOIN membership_plans p ON p.id = m.plan_id
                 WHERE m.user_id = u.id AND m.status = 'active' LIMIT 1) AS active_plan
          FROM users u
          JOIN roles r ON r.id = u.role_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY u.created_at DESC
         LIMIT 500";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$members = $stmt->fetchAll();

// CSV export. Same filter as the on-screen list so what the admin
// searched for is what they get in the file.
if (input('export') === 'csv') {
    $filename = 'members-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    // UTF-8 BOM so Excel opens Chinese names cleanly.
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Name', 'Email', 'Phone', 'Role', 'Plan', 'Status', 'Joined']);
    foreach ($members as $m) {
        fputcsv($out, [
            $m['full_name'],
            $m['email'],
            (string) ($m['phone'] ?? ''),
            $m['role'],
            $m['active_plan'] ?? '',
            $m['status'],
            format_datetime((string) $m['created_at'], 'Y-m-d'),
        ]);
    }
    fclose($out);
    exit;
}

// Quick summary numbers for the header.
$totalMembers = (int) db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$withPhone    = (int) db()->query("SELECT COUNT(*) FROM users WHERE phone IS NOT NULL AND phone <> ''")->fetchColumn();

$exportUrl = '/admin/members.php?export=csv'
           . ($q !== '' ? '&q=' . urlencode($q) : '')
           . ($missing ? '&missing_phone=1' : '');

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-start justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Members</h1>
    <p class="text-beige-100/60 mt-1 text-sm"><?= (int) $totalMembers ?> total · <?= (int) $withPhone ?> with phone on file.</p>
  </div>
  <div class="flex items-center gap-2 flex-wrap">
    <form method="get" class="flex items-center gap-2">
      <?php if ($missing): ?><input type="hidden" name="missing_phone" value="1"><?php endif; ?>
      <input name="q" value="<?= e($q) ?>" placeholder="Name, email, or phone"
             class="rounded-full bg-navy-900 border border-white/10 px-4 py-2 text-sm w-56 focus:border-gold-500/50 focus:outline-none">
      <button class="text-xs text-beige-100/70 hover:text-gold-400 px-2">Search</button>
    </form>
    <a href="<?= url('/admin/members.php' . ($missing ? '' : '?missing_phone=1')) ?>"
       class="px-3 py-1.5 rounded-full text-xs border transition
              <?= $missing ? 'border-gold-500/40 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-white/25' ?>">
      Missing phone
    </a>
    <a href="<?= url($exportUrl) ?>"
       class="px-3.5 py-1.5 rounded-full text-xs bg-gold-500 text-navy-950 hover:bg-gold-400 transition font-medium whitespace-nowrap">
      ↓ Export CSV
    </a>
  </div>
</div>

<div class="mt-6 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Role</th>
        <th>Plan</th>
        <th>Status</th>
        <th class="whitespace-nowrap">Joined</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php if (!$members): ?>
        <tr><td colspan="7" class="px-4 py-6 text-center text-beige-100/50">No members match.</td></tr>
      <?php else: foreach ($members as $m):
        $phone = trim((string) ($m['phone'] ?? ''));
        // wa.me expects digits-only, no leading +.
        $waDigits = $phone !== '' ? preg_replace('/\D/', '', $phone) : '';
      ?>
        <tr>
          <td class="px-4 py-3 text-beige-100"><?= e((string) $m['full_name']) ?></td>
          <td>
            <a href="mailto:<?= e((string) $m['email']) ?>" class="text-beige-100/85 hover:text-gold-400"><?= e((string) $m['email']) ?></a>
          </td>
          <td>
            <?php if ($phone !== ''): ?>
              <div class="flex items-center gap-2">
                <a href="tel:<?= e($phone) ?>"
                   class="text-beige-100/85 hover:text-gold-400 font-mono text-[13px]"
                   title="Call"><?= e($phone) ?></a>
                <?php if ($waDigits !== ''): ?>
                  <a href="https://wa.me/<?= e($waDigits) ?>" target="_blank" rel="noopener"
                     aria-label="Open in WhatsApp" title="Open in WhatsApp"
                     class="text-emerald-400/80 hover:text-emerald-300 transition">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="currentColor">
                      <path d="M20.52 3.48A11.87 11.87 0 0 0 12.02 0C5.48 0 .17 5.3.17 11.83c0 2.08.55 4.11 1.6 5.9L0 24l6.44-1.7a11.86 11.86 0 0 0 5.58 1.42h.01c6.53 0 11.84-5.3 11.84-11.83a11.76 11.76 0 0 0-3.35-8.41Zm-8.5 18.2h-.01a9.86 9.86 0 0 1-5.02-1.37l-.36-.21-3.82 1 1.02-3.72-.24-.38a9.83 9.83 0 0 1-1.5-5.19c0-5.44 4.43-9.86 9.86-9.86 2.63 0 5.1 1.03 6.96 2.89a9.78 9.78 0 0 1 2.88 6.98c0 5.43-4.43 9.86-9.77 9.86Zm5.42-7.38c-.3-.15-1.75-.86-2.03-.96-.27-.1-.47-.15-.66.15-.2.3-.76.96-.94 1.16-.17.2-.35.22-.65.07-.3-.15-1.25-.46-2.38-1.47-.88-.78-1.47-1.75-1.64-2.05-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.66-1.6-.91-2.19-.24-.57-.48-.5-.66-.51-.17-.01-.37-.01-.57-.01-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.06 2.87 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.7.63.71.23 1.35.2 1.86.12.57-.08 1.75-.71 2-1.4.24-.7.24-1.29.17-1.41-.07-.13-.27-.2-.57-.35Z"/>
                    </svg>
                  </a>
                <?php endif; ?>
              </div>
            <?php else: ?>
              <span class="text-[11px] text-beige-100/35 italic">no phone on file</span>
            <?php endif; ?>
          </td>
          <td class="text-beige-100/70"><?= e((string) $m['role']) ?></td>
          <td class="text-beige-100/70"><?= e((string) ($m['active_plan'] ?? '—')) ?></td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $m['status'] === 'active' ? 'bg-emerald-500/10 text-emerald-300' : 'bg-white/5 text-beige-100/50' ?>"><?= e((string) $m['status']) ?></span>
          </td>
          <td class="text-beige-100/60 whitespace-nowrap"><?= e(format_datetime((string) $m['created_at'], 'd M Y')) ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
