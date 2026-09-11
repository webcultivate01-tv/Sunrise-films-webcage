<?php
/**
 * The panel palette, in one place, for the Tailwind CDN build.
 *
 * Included in the <head> of every layout immediately after the Tailwind
 * script, so `brand-*`, `ink`, `canvas` and `line` are available to every
 * view. Keep this in step with the tokens in public/assets/css/app.css.
 */
?>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    brand: {
                        50:  '#eef0ff',
                        100: '#e0e3ff',
                        200: '#c6c9ff',
                        300: '#a5a6fb',
                        400: '#8b85f5',
                        500: '#6d63ec',
                        600: '#5145e5',
                        700: '#4338ca',
                        800: '#372fa3',
                        900: '#2f2a80',
                    },
                    ink:    '#111735',
                    canvas: '#f4f5fa',
                    line:   '#e8eaf3',
                },
                backgroundImage: {
                    'brand-gradient': 'linear-gradient(135deg, #5b52e8 0%, #7c5ce8 100%)',
                },
            },
        },
    };
</script>
