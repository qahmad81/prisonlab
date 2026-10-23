<?php
// Generates inline SVG logo for AI providers
function getProviderSvg($provider, $size = 28) {
    $p = strtolower(trim($provider));
    $p = ltrim($p, '~');

    // Deepseek: Iconic whale/dolphin with fin in blue
    if (strpos($p, 'deepseek') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#1e3a8a"/>
            <path d="M7 18C7 13 11 9 17 9C23 9 26 12 26 15C26 18.5 22.5 21 17 21C13.5 21 11.5 22.5 10 24C10 21 7 20.5 7 18Z" fill="#38bdf8"/>
            <path d="M16 9C15 6 12 5 10 6C12 7.5 13 10 13.5 11" stroke="#60a5fa" stroke-width="2" stroke-linecap="round"/>
            <circle cx="21" cy="14" r="1.5" fill="#ffffff"/>
            <path d="M22 17C21 18 19 18.5 17 18.5" stroke="#0369a1" stroke-width="1.5" stroke-linecap="round"/>
        </svg>';
    }

    // OpenAI: Minimalist iconic green/teal swirl
    if (strpos($p, 'openai') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#0f172a"/>
            <path d="M16 6C13.5 6 11.5 7.5 10.5 9.5C9.5 9.2 8.5 9.5 7.8 10.2C6.8 11.2 6.8 12.8 7.5 13.8C6.5 14.8 6.2 16.2 6.8 17.5C7.5 19 9 20 10.8 19.8V22C10.8 23.5 12 24.8 13.5 25.2C14.5 25.5 15.5 25.2 16.2 24.5C17.2 25.5 18.8 25.8 20.2 25C21.2 24.5 21.8 23.5 21.8 22.5C23 22 24 20.8 24.2 19.2C24.5 17.5 23.8 16 22.5 15.2C23 14 22.8 12.5 21.8 11.5C20.8 10.5 19.2 10.2 18 10.8C17.5 9.5 16.2 6 16 6Z" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="16" cy="16" r="3" fill="#10b981"/>
        </svg>';
    }

    // Google: 4-color stylized Gemini spark / G
    if (strpos($p, 'google') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#182234"/>
            <path d="M16 6C16 11.5 11.5 16 6 16C11.5 16 16 20.5 16 26C16 20.5 20.5 16 26 16C20.5 16 16 11.5 16 6Z" fill="url(#googGrad)"/>
            <defs>
                <linearGradient id="googGrad" x1="6" y1="6" x2="26" y2="26" gradientUnits="userSpaceOnUse">
                    <stop stop-color="#4285F4"/>
                    <stop offset="0.33" stop-color="#EA4335"/>
                    <stop offset="0.66" stop-color="#FBBC05"/>
                    <stop offset="1" stop-color="#34A853"/>
                </linearGradient>
            </defs>
        </svg>';
    }

    // Anthropic: Terracotta / warm minimalist geometric symbol
    if (strpos($p, 'anthropic') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#291811"/>
            <path d="M10 24L16 8L22 24" stroke="#d97706" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 19H20" stroke="#f59e0b" stroke-width="2.5" stroke-linecap="round"/>
        </svg>';
    }

    // Mistral AI: Orange geometric wind / stairs
    if (strpos($p, 'mistral') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#2b1408"/>
            <rect x="7" y="9" width="5" height="5" fill="#f97316"/>
            <rect x="20" y="9" width="5" height="5" fill="#f97316"/>
            <rect x="7" y="14" width="18" height="5" fill="#ea580c"/>
            <rect x="12" y="19" width="8" height="5" fill="#c2410c"/>
        </svg>';
    }

    // Meta (Llama): Infinity symbol in meta blue
    if (strpos($p, 'meta') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#0b192e"/>
            <path d="M8 16C8 13 10.5 11 13 13.5L19 18.5C21.5 21 24 19 24 16C24 13 21.5 11 19 13.5L13 18.5C10.5 21 8 19 8 16Z" stroke="#3b82f6" stroke-width="2.5" stroke-linecap="round"/>
        </svg>';
    }

    // Qwen (Alibaba): Purple / blue prism hexagon
    if (strpos($p, 'qwen') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#1e1035"/>
            <path d="M16 7L24 11.5V20.5L16 25L8 20.5V11.5L16 7Z" stroke="#a855f7" stroke-width="2.2" stroke-linejoin="round"/>
            <path d="M16 13L20 16L16 19L12 16L16 13Z" fill="#c084fc"/>
        </svg>';
    }

    // xAI (Grok): Sharp black & white minimalist X
    if (strpos($p, 'x-ai') !== false || strpos($p, 'xai') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#000000"/>
            <path d="M9 9L23 23M23 9L9 23" stroke="#ffffff" stroke-width="2.8" stroke-linecap="round"/>
        </svg>';
    }

    // Cohere: Coral cellular blob
    if (strpos($p, 'cohere') !== false) {
        return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="8" fill="#2d1515"/>
            <circle cx="13" cy="14" r="5" fill="#f43f5e"/>
            <circle cx="20" cy="18" r="4.5" fill="#fb7185"/>
        </svg>';
    }

    // Default Fallback: Sleek futuristic AI chip icon
    $colors = ['#3b82f6', '#8b5cf6', '#ec4899', '#06b6d4', '#10b981', '#f59e0b'];
    $hash = abs(crc32($p));
    $col = $colors[$hash % count($colors)];

    return '<svg width="' . $size . '" height="' . $size . '" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
        <rect width="32" height="32" rx="8" fill="#111827"/>
        <rect x="9" y="9" width="14" height="14" rx="3" stroke="' . $col . '" stroke-width="2"/>
        <circle cx="16" cy="16" r="3" fill="' . $col . '"/>
        <line x1="16" y1="5" x2="16" y2="9" stroke="' . $col . '" stroke-width="2" stroke-linecap="round"/>
        <line x1="16" y1="23" x2="16" y2="27" stroke="' . $col . '" stroke-width="2" stroke-linecap="round"/>
        <line x1="5" y1="16" x2="9" y2="16" stroke="' . $col . '" stroke-width="2" stroke-linecap="round"/>
        <line x1="23" y1="16" x2="27" y2="16" stroke="' . $col . '" stroke-width="2" stroke-linecap="round"/>
    </svg>';
}
