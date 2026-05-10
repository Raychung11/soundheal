<?php
if (!defined('SOUNDHEAL_BOOTED')) {
    require_once __DIR__ . '/bootstrap.php';
}
$pageTitle = $pageTitle ?? config('app.name');
$pageDescription = $pageDescription ?? 'A calm, premium wellness operating system. Sound healing, breathwork and mindful experiences.';

// Marketing tracking — admin pages set $skipTracking before requiring header.
if (empty($skipTracking)) {
    track_view();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(config('app.name')) ?></title>
    <meta name="description" content="<?= e($pageDescription) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
      tailwind.config = {
        theme: {
          extend: {
            colors: {
              navy:   { 950: '#0a1027', 900: '#0f172a', 800: '#1a2240' },
              gold:   { 500: '#c9a46a', 400: '#d8b97e', 300: '#e7d2a3' },
              beige:  { 100: '#f6efe5', 200: '#ece1cd' },
            },
            fontFamily: {
              serif: ['"Cormorant Garamond"', 'serif'],
              sans:  ['Inter', 'system-ui', 'sans-serif'],
            },
          }
        }
      }
    </script>
    <link rel="stylesheet" href="<?= asset('css/app.css') ?>">
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body class="bg-navy-950 text-beige-100 font-sans antialiased min-h-screen flex flex-col">
<?php require __DIR__ . '/navbar.php'; ?>
<main class="flex-1">
<?php
$flash = flash_render();
if ($flash !== '') {
    echo '<div class="max-w-5xl mx-auto px-4 mt-6">' . $flash . '</div>';
}
