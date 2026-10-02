<?php
/**
 * TawasulOS Design Tokens
 * Exact CSS variables matching https://github.com/emadymb1/tawasul-school-os-ui
 */
class DesignTokens
{
    // Cream canvas, deep green ink, gold + coral accents, soft mint/sage tiles
    public static function cssVariables(): string
    {
        return "
        --background: oklch(0.958 0.013 88);
        --foreground: oklch(0.29 0.045 155);
        --card: oklch(0.995 0.004 90);
        --card-foreground: oklch(0.29 0.045 155);
        --popover: oklch(0.995 0.004 90);
        --popover-foreground: oklch(0.29 0.045 155);
        --primary: oklch(0.31 0.05 156);
        --primary-foreground: oklch(0.965 0.014 90);
        --secondary: oklch(0.93 0.02 92);
        --secondary-foreground: oklch(0.31 0.05 156);
        --muted: oklch(0.935 0.015 92);
        --muted-foreground: oklch(0.52 0.03 155);
        --accent: oklch(0.91 0.035 120);
        --accent-foreground: oklch(0.31 0.05 156);
        --destructive: oklch(0.63 0.175 27);
        --destructive-foreground: oklch(0.985 0.01 90);
        --border: oklch(0.9 0.015 92);
        --input: oklch(0.92 0.015 92);
        --ring: oklch(0.55 0.08 158);
        --gold: oklch(0.775 0.115 80);
        --gold-foreground: oklch(0.29 0.045 155);
        --coral: oklch(0.63 0.175 27);
        --coral-foreground: oklch(0.985 0.01 90);
        --mint: oklch(0.915 0.032 168);
        --mint-foreground: oklch(0.31 0.05 156);
        --sage: oklch(0.9 0.045 125);
        --sage-foreground: oklch(0.31 0.05 156);
        --leaf: oklch(0.62 0.08 158);
        --leaf-foreground: oklch(0.985 0.01 90);
        --radius: 1.5rem;
        --font-sans: 'Tajawal', 'Cairo', ui-sans-serif, system-ui, sans-serif;
        --font-display: 'Cairo', 'Tajawal', ui-sans-serif, system-ui, sans-serif;
        ";
    }

    // Convert oklch to hex approximations for older browsers
    public static function hexFallbacks(): array
    {
        return [
            'primary' => '#1e3a5f',
            'primary-foreground' => '#f8faf8',
            'gold' => '#d4a84a',
            'gold-foreground' => '#1f2937',
            'coral' => '#e0654a',
            'coral-foreground' => '#fefcf9',
            'mint' => '#ccfbf1',
            'mint-foreground' => '#134e4a',
            'sage' => '#d1fae5',
            'sage-foreground' => '#134e4a',
            'leaf' => '#059669',
            'leaf-foreground' => '#f0fdf4',
            'background' => '#fafaf5',
            'foreground' => '#1f2937',
            'card' => '#ffffff',
            'card-foreground' => '#1f2937',
            'border' => '#e5e7eb',
            'muted' => '#f3f4f6',
            'muted-foreground' => '#6b7280',
            'secondary' => '#e8f0fe',
            'secondary-foreground' => '#1f2937',
            'accent' => '#dcfce7',
            'accent-foreground' => '#1f2937',
            'destructive' => '#dc2626',
            'destructive-foreground' => '#ffffff',
        ];
    }
}
