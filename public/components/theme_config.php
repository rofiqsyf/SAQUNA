<style>
/* SAQUNA Design System - CSS Variables for Native Tailwind Theming (Adapted to User's Palette) */
:root {
  /* User-Defined Base Variables (Light Mode) */
  --bg-primary: #f0f2f5; 
  --bg-secondary: #ffffff; 
  --text-primary: #333333; 
  --text-secondary: #666666; 
  --accent-color: #4caf50; 
  --accent-text: #ffffff; 
  --border-color: #dddddd; 
  --shadow: 0 2px 5px rgba(0, 0, 0, 0.1); 
  --input-bg: #ffffff; 
  --input-text: #333333; 
  --badge-bg: #e0e0e0; 
  --badge-text: #666666; 

  /* Tailwind Mappings (Light Mode) */
  --color-primary: var(--accent-color);
  --color-on-primary: var(--accent-text);
  --color-primary-container: #c8e6c9;
  --color-on-primary-container: #1b5e20;
  --color-primary-fixed: #a5d6a7;
  --color-on-primary-fixed: #003300;
  --color-primary-fixed-dim: #81c784;
  --color-on-primary-fixed-variant: #388e3c;
  --color-inverse-primary: #81c784;
  
  --color-secondary: var(--text-secondary);
  --color-on-secondary: var(--bg-secondary);
  --color-secondary-container: var(--badge-bg);
  --color-on-secondary-container: var(--text-primary);
  --color-secondary-fixed: var(--badge-bg);
  --color-on-secondary-fixed: var(--text-primary);
  --color-secondary-fixed-dim: #cccccc;
  --color-on-secondary-fixed-variant: var(--text-secondary);
  
  --color-tertiary: var(--accent-color);
  --color-on-tertiary: var(--accent-text);
  --color-tertiary-container: #c8e6c9;
  --color-on-tertiary-container: #1b5e20;
  --color-tertiary-fixed: #a5d6a7;
  --color-on-tertiary-fixed: #003300;
  --color-tertiary-fixed-dim: #81c784;
  --color-on-tertiary-fixed-variant: #388e3c;
  
  --color-background: var(--bg-primary);
  --color-on-background: var(--text-primary);
  --color-surface: var(--bg-primary);
  --color-on-surface: var(--text-primary);
  --color-surface-variant: #e0e0e0;
  --color-on-surface-variant: var(--text-secondary);
  --color-surface-dim: #d8dadb;
  --color-surface-bright: var(--bg-secondary);
  
  --color-surface-container-lowest: var(--bg-secondary);
  --color-surface-container-low: var(--bg-secondary);
  --color-surface-container: var(--bg-secondary);
  --color-surface-container-high: var(--bg-primary);
  --color-surface-container-highest: #e0e0e0;
  
  --color-inverse-surface: var(--text-primary);
  --color-inverse-on-surface: var(--bg-secondary);
  
  --color-outline: var(--text-secondary);
  --color-outline-variant: var(--border-color);
  
  --color-error: #ba1a1a;
  --color-on-error: #ffffff;
  --color-error-container: #ffdad6;
  --color-on-error-container: #93000a;

  --color-glass-bg: rgba(255, 255, 255, 0.7);
  --color-glass-border: rgba(255, 255, 255, 0.4);
  --color-mint-glow: rgba(76, 175, 80, 0.3);
  --color-success-badge: var(--badge-bg);
  --color-surface-tint: var(--accent-color);
}

.dark {
  /* Restored Base Variables for Depth & Detail (No Pure Black, No White, No Gray) */
  --bg-primary: #050a08; 
  --bg-secondary: #0a1410; 
  --text-primary: #cfe8df; 
  --text-secondary: #8baba0; 
  --accent-color: #559178; 
  --accent-text: #050a08; 
  --border-color: #152e25; 
  --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.5); 
  --input-bg: #0c1c16; 
  --input-text: #cfe8df; 
  --badge-bg: #11261e; 
  --badge-text: #89d6b5; 

  /* Tailwind Mappings (Dark Mode) */
  --color-primary: var(--accent-color);
  --color-on-primary: var(--accent-text);
  --color-primary-container: #11261e;
  --color-on-primary-container: #89d6b5;
  --color-primary-fixed: #89d6b5;
  --color-on-primary-fixed: #002115;
  --color-primary-fixed-dim: #559178;
  --color-on-primary-fixed-variant: #11261e;
  --color-inverse-primary: #196b50;
  
  --color-secondary: #8baba0;
  --color-on-secondary: #050a08;
  --color-secondary-container: #152e25;
  --color-on-secondary-container: #cfe8df;
  --color-secondary-fixed: #cfe8df;
  --color-on-secondary-fixed: #050a08;
  --color-secondary-fixed-dim: #8baba0;
  --color-on-secondary-fixed-variant: #152e25;
  
  --color-tertiary: #b3ccc3;
  --color-on-tertiary: #041f19;
  --color-tertiary-container: #1d352e;
  --color-on-tertiary-container: #cfe8df;
  --color-tertiary-fixed: #cfe8df;
  --color-on-tertiary-fixed: #091f1a;
  --color-tertiary-fixed-dim: #b3ccc3;
  --color-on-tertiary-fixed-variant: #354b45;
  
  --color-background: var(--bg-primary);
  --color-on-background: var(--text-primary);
  --color-surface: var(--bg-secondary);
  --color-on-surface: var(--text-primary);
  --color-surface-variant: var(--input-bg);
  --color-on-surface-variant: var(--text-secondary);
  --color-surface-dim: #050a08;
  --color-surface-bright: #152e25;
  
  --color-surface-container-lowest: #020504;
  --color-surface-container-low: #050a08;
  --color-surface-container: var(--bg-secondary);
  --color-surface-container-high: #0d1a15;
  --color-surface-container-highest: #152e25;
  
  --color-inverse-surface: #cfe8df;
  --color-inverse-on-surface: #050a08;
  
  --color-outline: var(--text-secondary);
  --color-outline-variant: var(--border-color);
  
  --color-error: #ffb4ab;
  --color-on-error: #690005;
  --color-error-container: #93000a;
  --color-on-error-container: #ffdad6;

  --color-glass-bg: rgba(10, 20, 16, 0.75);
  --color-glass-border: rgba(85, 145, 120, 0.15);
  --color-mint-glow: transparent;
  --color-success-badge: #0a3d25;
  --color-surface-tint: var(--accent-color);
}

/* Base resets & utilities tied to CSS vars */
body {
    background-color: var(--color-background);
    color: var(--color-on-background);
    background-image: none !important;
}

input:not([type="checkbox"]):not([type="radio"]):not([type="file"]), select, textarea {
    background-color: var(--input-bg) !important;
    color: var(--input-text) !important;
    border: 1px solid var(--border-color) !important;
}

input::placeholder, textarea::placeholder {
    color: var(--text-secondary) !important;
}

.dark .bg-blob, .dark .floating-blob {
    display: none !important;
}
.dark .bg-mesh {
    background-image: none !important;
}

/* KILL ALL HARDCODED WHITES AND GRAYS IN DARK MODE */
.dark [class*='bg-gradient-'] {
    background-image: none !important;
}

.dark .bg-white,
.dark .bg-white\/40,
.dark .bg-white\/50,
.dark .bg-white\/60,
.dark .bg-white\/80 {
    background-color: var(--color-surface-container) !important;
    border-color: var(--color-outline-variant) !important;
    color: var(--color-on-surface) !important;
}

.dark .bg-gray-50, .dark .bg-gray-100, .dark .bg-gray-200, .dark .bg-gray-300, 
.dark .bg-gray-400, .dark .bg-gray-500, .dark .bg-gray-600, .dark .bg-gray-700, 
.dark .bg-gray-800, .dark .bg-gray-900 {
    background-color: var(--color-surface-container-highest) !important;
    border-color: var(--color-outline-variant) !important;
}

.dark .text-gray-500, .dark .text-gray-600, .dark .text-gray-700, 
.dark .text-gray-800, .dark .text-gray-900, .dark .text-gray-400, .dark .text-gray-300 {
    color: var(--color-on-surface-variant) !important;
}
</style>

<script id="tailwind-config">
tailwind.config = {
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        "primary": "var(--color-primary)",
        "on-primary": "var(--color-on-primary)",
        "primary-container": "var(--color-primary-container)",
        "on-primary-container": "var(--color-on-primary-container)",
        "primary-fixed": "var(--color-primary-fixed)",
        "on-primary-fixed": "var(--color-on-primary-fixed)",
        "primary-fixed-dim": "var(--color-primary-fixed-dim)",
        "on-primary-fixed-variant": "var(--color-on-primary-fixed-variant)",
        "inverse-primary": "var(--color-inverse-primary)",
        
        "secondary": "var(--color-secondary)",
        "on-secondary": "var(--color-on-secondary)",
        "secondary-container": "var(--color-secondary-container)",
        "on-secondary-container": "var(--color-on-secondary-container)",
        "secondary-fixed": "var(--color-secondary-fixed)",
        "on-secondary-fixed": "var(--color-on-secondary-fixed)",
        "secondary-fixed-dim": "var(--color-secondary-fixed-dim)",
        "on-secondary-fixed-variant": "var(--color-on-secondary-fixed-variant)",
        
        "tertiary": "var(--color-tertiary)",
        "on-tertiary": "var(--color-on-tertiary)",
        "tertiary-container": "var(--color-tertiary-container)",
        "on-tertiary-container": "var(--color-on-tertiary-container)",
        "tertiary-fixed": "var(--color-tertiary-fixed)",
        "on-tertiary-fixed": "var(--color-on-tertiary-fixed)",
        "tertiary-fixed-dim": "var(--color-tertiary-fixed-dim)",
        "on-tertiary-fixed-variant": "var(--color-on-tertiary-fixed-variant)",
        
        "background": "var(--color-background)",
        "on-background": "var(--color-on-background)",
        "surface": "var(--color-surface)",
        "on-surface": "var(--color-on-surface)",
        "surface-variant": "var(--color-surface-variant)",
        "on-surface-variant": "var(--color-on-surface-variant)",
        "surface-dim": "var(--color-surface-dim)",
        "surface-bright": "var(--color-surface-bright)",
        
        "surface-container-lowest": "var(--color-surface-container-lowest)",
        "surface-container-low": "var(--color-surface-container-low)",
        "surface-container": "var(--color-surface-container)",
        "surface-container-high": "var(--color-surface-container-high)",
        "surface-container-highest": "var(--color-surface-container-highest)",
        
        "inverse-surface": "var(--color-inverse-surface)",
        "inverse-on-surface": "var(--color-inverse-on-surface)",
        
        "outline": "var(--color-outline)",
        "outline-variant": "var(--color-outline-variant)",
        
        "error": "var(--color-error)",
        "on-error": "var(--color-on-error)",
        "error-container": "var(--color-error-container)",
        "on-error-container": "var(--color-on-error-container)",
        
        "glass-bg": "var(--color-glass-bg)",
        "glass-border": "var(--color-glass-border)",
        "mint-glow": "var(--color-mint-glow)",
        "success-badge": "var(--color-success-badge)",
        "surface-tint": "var(--color-surface-tint)",
      },
      borderRadius: {
        "DEFAULT": "0.25rem",
        "lg": "0.5rem",
        "xl": "0.75rem",
        "full": "9999px"
      },
      spacing: {
        "stack-xs": "4px",
        "unit": "8px",
        "stack-sm": "12px",
        "stack-md": "24px",
        "gutter": "24px",
        "stack-lg": "40px",
        "margin-page": "48px",
        "stack-xl": "64px",
        "container-max": "1440px"
      },
      fontFamily: {
        "display-lg": ["Outfit"],
        "headline-lg": ["Outfit"],
        "headline-md": ["Outfit"],
        "title-lg": ["Outfit"],
        "body-lg": ["Inter"],
        "body-md": ["Inter"],
        "body-sm": ["Inter"],
        "label-md": ["Inter"]
      },
      fontSize: {
        "display-lg": ["48px", {"lineHeight": "56px", "letterSpacing": "-0.02em", "fontWeight": "700"}],
        "headline-lg": ["32px", {"lineHeight": "40px", "letterSpacing": "-0.01em", "fontWeight": "600"}],
        "headline-md": ["24px", {"lineHeight": "32px", "fontWeight": "600"}],
        "title-lg": ["20px", {"lineHeight": "28px", "fontWeight": "500"}],
        "body-lg": ["18px", {"lineHeight": "28px", "fontWeight": "400"}],
        "body-md": ["16px", {"lineHeight": "24px", "fontWeight": "400"}],
        "body-sm": ["14px", {"lineHeight": "20px", "fontWeight": "400"}],
        "label-md": ["12px", {"lineHeight": "16px", "fontWeight": "600"}]
      },
      boxShadow: {
        "mint-glow": "0 20px 40px -15px var(--color-mint-glow)"
      }
    },
  },
}
</script>
