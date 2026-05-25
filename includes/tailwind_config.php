<?php // includes/tailwind_config.php ?>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<script type="module">
  import tailwind from 'https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4'
  
  tailwind.config = {
    theme: {
      extend: {
        colors: {
          brand: {
            DEFAULT: '#22c55e',
            dark: '#16a34a',
            light: '#4ade80',
          },
          admin: {
            DEFAULT: '#3b82f6',
            dark: '#2563eb',
            light: '#60a5fa',
          }
        },
        fontFamily: {
          sans: ['Poppins', 'sans-serif'],
        }
      }
    }
  }
</script>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">