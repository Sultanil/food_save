<?php // includes/tailwind_config.php ?>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style type="text/tailwindcss">
  @theme {
    --color-brand: #22c55e;
    --color-brand-dark: #16a34a;
    --color-brand-light: #4ade80;
    
    --color-admin: #3b82f6;
    --color-admin-dark: #2563eb;
    --color-admin-light: #60a5fa;
  }
  body { font-family: 'Poppins', sans-serif; }
</style>
<style>
  /* Standard plain CSS fallback rules */
  .bg-brand { background-color: #22c55e !important; }
  .hover\:bg-brand-dark:hover { background-color: #16a34a !important; }
  .text-brand { color: #22c55e !important; }
  .border-brand { border-color: #22c55e !important; }
</style>