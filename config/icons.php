<?php
// config/icons.php - คลังไอคอน Neumorphic Disc Medical Icons สไตล์ Soft-UI 3D ตามมาตรฐานระบบ
// ให้ความสอดคล้อง (Consistent Visual Design) ทุกหน้ารายการ ปรับเข้ากับทั้ง Light & Dark Mode

if (!function_exists('get_neu_svg_path')) {
    function get_neu_svg_path($name) {
        $icons = [
            // 1. First Aid / Medical Kit
            'medical-kit' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M8 6a2 2 0 012-2h4a2 2 0 012 2v1h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h3V6zm2 1h4V6h-4v1zm2 4a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/>',
            'first-aid' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M8 6a2 2 0 012-2h4a2 2 0 012 2v1h3a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V9a2 2 0 012-2h3V6zm2 1h4V6h-4v1zm2 4a1 1 0 011 1v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 110-2h1v-1a1 1 0 011-1z"/>',
            
            // 2. Heart Pulse / ECG Wave
            'heart-pulse' => '
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" opacity="0.2"/>
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35zM4 11h3.2l1.6-3.2 2.8 6.4 2-4 1.2 1.6 1.6-.8H20v2h-2.8l-1.6.8-1.6-2.2-2 4-2.8-6.4-1.6 3.2H4v-2z"/>',

            // 3. Thermometer
            'thermometer' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M15 13.5V5a3 3 0 00-6 0v8.5a5 5 0 106 0zM12 4a1 1 0 00-1 1v3h2V5a1 1 0 00-1-1zm1 5h-2v2h2V9zm0 3h-2v1.8a3 3 0 102 0V12z"/>',

            // 4. Clipboard Report / Medical Record
            'clipboard-record' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M9 3a2 2 0 00-2 2H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2-2H9zm0 2h6V4H9v1zm-2 5h10v2H7v-2zm0 4h10v2H7v-2zm0 4h6v2H7v-2z"/>
                <circle cx="17" cy="17" r="4" fill="currentColor"/>
                <path d="M17 15v4m-2-2h4" stroke="#ffffff" stroke-width="1.5" stroke-linecap="round"/>',

            // 5. Syringe / Injection / DTX Blood Test
            'syringe' => '
                <path d="M19.7 4.3a1 1 0 00-1.4 0l-1.8 1.8-1.4-1.4-1.4 1.4 1.4 1.4-6.8 6.8H6.5l-2 2 1.5 1.5-3 3 1.4 1.4 3-3 1.5 1.5 2-2v-1.8l6.8-6.8 1.4 1.4 1.4-1.4-1.4-1.4 1.8-1.8a1 1 0 000-1.4zM9.5 13.7l4.8-4.8.7.7-2 2 1.4 1.4 2-2 .7.7-4.8 4.8-2.8-2.8z"/>',

            // 6. Lab Flask / Beaker
            'flask' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 2a1 1 0 00-1 1v1h6V3a1 1 0 00-1-1h-4zm-1 4v5.3l-4.7 7.8A3 3 0 006.9 23h10.2a3 3 0 002.6-4.9L15 11.3V6H9zm1.5 8h7l2.4 4H4.1l2.4-4h3z"/>',

            // 7. DNA Double Helix / Genetics / Family
            'dna' => '
                <path d="M5.5 3.5l1.4-1.4 13.6 13.6-1.4 1.4-2.8-2.8-2.1 2.1 1.4 1.4-1.4 1.4-1.4-1.4-2.1 2.1 2.8 2.8-1.4 1.4L2.1 11.5l1.4-1.4 2.8 2.8 2.1-2.1-1.4-1.4 1.4-1.4 1.4 1.4 2.1-2.1-4.4-4.4zm9.9 5.7l-2.1 2.1 2.8 2.8 2.1-2.1-2.8-2.8zm-5.6 5.6l-2.1 2.1 2.8 2.8 2.1-2.1-2.8-2.8z"/>',

            // 8. Weight Scale / Body Shape / Obesity
            'weight-scale' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4 5a3 3 0 013-3h10a3 3 0 013 3v14a3 3 0 01-3 3H7a3 3 0 01-3-3V5zm8 0a4 4 0 00-3.9 3.1h7.8A4 4 0 0012 5zm-1 4a1 1 0 112 0v1h-2V9z"/>',

            // 9. Medicine Bottles
            'medicine-bottles' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M6 3a1 1 0 011-1h4a1 1 0 011 1v1H6V3zm-2 3a2 2 0 012-2h6a2 2 0 012 2v13a2 2 0 01-2 2H6a2 2 0 01-2-2V6zm4 4a1 1 0 011-1h1v-1a1 1 0 112 0v1h1a1 1 0 110 2h-1v1a1 1 0 11-2 0v-1h-1a1 1 0 01-1-1zm10-3a1 1 0 011-1h3a1 1 0 011 1v1h-5V7zm-1 3a2 2 0 012-2h3a2 2 0 012 2v9a2 2 0 01-2 2h-3a2 2 0 01-2-2v-9z"/>',

            // 10. Capsules / Pills
            'capsules' => '
                <path d="M6.5 13.5l4-4a4.24 4.24 0 016 6l-4 4a4.24 4.24 0 01-6-6zm3-1l-2 2a2.24 2.24 0 003.17 3.17l2-2-3.17-3.17zm8-7a4.24 4.24 0 00-6 0l-1 1 3.17 3.17 1-1a2.24 2.24 0 013.17 3.17l-1 1 3.17 3.17 1-1a4.24 4.24 0 00-3.51-9.51z"/>',

            // 11. Pill Blister Pack
            'blister-pack' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M5 4a2 2 0 012-2h10a2 2 0 012 2v16a2 2 0 01-2 2H7a2 2 0 01-2-2V4zm4 4a2 2 0 110-4 2 2 0 010 4zm6 0a2 2 0 110-4 2 2 0 010 4zm-6 6a2 2 0 110-4 2 2 0 010 4zm6 0a2 2 0 110-4 2 2 0 010 4zm-6 6a2 2 0 110-4 2 2 0 010 4zm6 0a2 2 0 110-4 2 2 0 010 4z"/>',

            // 12. Mobile Health / Smartphone Pulse / Self-Screening
            'mobile-health' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M6 3a2 2 0 012-2h8a2 2 0 012 2v18a2 2 0 01-2 2H8a2 2 0 01-2-2V3zm2 2v12h8V5H8zm3.5 15a1 1 0 102 0 1 1 0 00-2 0zm-1.5-9h1.2l.8-1.5 1.5 3 1-2 .7.5H15v1.5h-1.1l-.9-1.2-1.5 3-.8-1.8H10V11z"/>',

            // 13. Ambulance / Emergency Referral
            'ambulance' => '
                <path d="M19 11l2 3v3h-2a2 2 0 01-4 0H9a2 2 0 01-4 0H3V7a2 2 0 012-2h10a2 2 0 012 2v4h2zm-2 0V7H5v8h.5a2 2 0 013.8 0h5.4a2 2 0 013.8 0H19v-2.5l-1.7-1.5h-.3zM7 17a1 1 0 100-2 1 1 0 000 2zm10 0a1 1 0 100-2 1 1 0 000 2zm-7-9v2H8v2h2v2h2v-2h2v-2h-2V8h-2z"/>',

            // 14. X-Ray / Scan / Health Surveillance
            'xray' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4 4a2 2 0 012-2h12a2 2 0 012 2v16a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm8 3a3 3 0 100 6 3 3 0 000-6zm-4 8a5 5 0 018 0H8z"/>',

            // 15. Sleep / Rest (Zzz)
            'sleep' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4 14a4 4 0 014-4h8a4 4 0 014 4v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zm4-2a2 2 0 00-2 2v2h12v-2a2 2 0 00-2-2H8zm8-7h4v2l-2.6 3H20v2h-5V12l2.6-3H15V5zm-6 2h3v1.5l-2 2.5h2v1.5H9V12l2-2.5H9V7z"/>',

            // 16. Doctor / Health Worker / VHV Personnel
            'doctor' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a4 4 0 100 8 4 4 0 000-8zM9 13a4 4 0 00-4 4v4a1 1 0 001 1h12a1 1 0 001-1v-4a4 4 0 00-4-4H9zm2 3h2v2h-2v-2zm0-4h2v2h-2v-2z"/>',
            'health-worker' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2a4 4 0 100 8 4 4 0 000-8zM9 13a4 4 0 00-4 4v4a1 1 0 001 1h12a1 1 0 001-1v-4a4 4 0 00-4-4H9zm2 3h2v2h-2v-2zm0-4h2v2h-2v-2z"/>',

            // Additional Complementary Glyphs (Standardized to the same style)
            'nutrition' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 3c1.5 0 2.5 1 3 2 1.5-.5 3 .5 3.5 2 1 2.5 0 6-2 9-1.5 2.5-3 4-4.5 4s-3-1.5-4.5-4c-2-3-3-6.5-2-9 .5-1.5 2-2.5 3.5-2 .5-1 1.5-2 3-2zm0 4c-1.5 0-3 2-3 5 0 2 1.5 4 3 4s3-2 3-4c0-3-1.5-5-3-5z"/>',
            
            'exercise' => '
                <path d="M13.5 5.5a2 2 0 11-4 0 2 2 0 014 0zm-3.3 4.2l-2.4 4.5 1.7.9 1.8-3.4 1.8 1.4-2.1 4.7 1.8.8 2.6-5.8-2.6-2-.6-1.1zm3.8 2.1l-1.5-1.2.6-1.1a2.5 2.5 0 013.2-1.1l2.4 1.1-.7 1.6-2.1-.9-.8 1.6zM6 19.5l3.5-3.5 1.4 1.4L7.4 21H3v-2h3v.5z"/>',

            'sugar-sweet' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M7 6a3 3 0 013-3h4a3 3 0 013 3v1h1a2 2 0 012 2v9a3 3 0 01-3 3H7a3 3 0 01-3-3V9a2 2 0 012-2h1V6zm2 1h6V6a1 1 0 00-1-1h-4a1 1 0 00-1 1v1zm-3 4v7a1 1 0 001 1h10a1 1 0 001-1v-7H6zm4 2h4v2h-4v-2z"/>',

            'salt-sodium' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 2h4a1 1 0 011 1v2H9V3a1 1 0 011-1zm-2 5h8l1.5 12A3 3 0 0114.5 22h-5a3 3 0 01-3-3L8 7zm3 4a1 1 0 100-2 1 1 0 000 2zm3 3a1 1 0 100-2 1 1 0 000 2zm-3 3a1 1 0 100-2 1 1 0 000 2z"/>',

            'no-substance' => '
                <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-7.9 10a7.9 7.9 0 011.8-5L17 18.1A7.9 7.9 0 0112 20a8 8 0 01-7.9-8zm13.8 5L6.9 5.9A7.9 7.9 0 0112 4a8 8 0 017.9 8c0 1.9-.7 3.6-1.8 5z"/>',

            'shield-check' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2L4 5v6.09c0 5.05 3.41 9.76 8 10.91 4.59-1.15 8-5.86 8-10.91V5l-8-3zm3.7 8.7l-4.2 4.2-2.2-2.2 1.4-1.4 0.8 0.8 2.8-2.8 1.4 1.4z"/>',

            'analytics-bars' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M4 3a1 1 0 011 1v14h14a1 1 0 110 2H4a1 1 0 01-1-1V4a1 1 0 011-1zm4 11a1 1 0 011-1h2a1 1 0 011 1v3H8v-3zm5-5a1 1 0 011-1h2a1 1 0 011 1v8h-4V9zm5-3a1 1 0 011-1h2a1 1 0 011 1v11h-4V6z"/>',

            'users-group' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M16 11a3 3 0 100-6 3 3 0 000 6zm-8 0a3 3 0 100-6 3 3 0 000 6zm0 2c-2.67 0-8 1.34-8 4v3h16v-3c0-2.66-5.33-4-8-4zm8 1.9c1.6.8 3 2.1 3 3.1v2h5v-2c0-2.2-4.1-3.3-8-3.1z"/>',

            'refresh-repeat' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M17.65 6.35A7.958 7.958 0 0012 4c-4.42 0-7.99 3.58-7.99 8s3.57 8 7.99 8c3.73 0 6.84-2.55 7.73-6h-2.08A5.99 5.99 0 0112 18c-3.31 0-6-2.69-6-6s2.69-6 6-6c1.66 0 3.14.69 4.22 1.78L13 11h7V4l-2.35 2.35z"/>',

            'warning-alert' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2L1 21h22L12 2zm0 4.5l8.5 14.5H3.5L12 6.5zm-1 6v4h2v-4h-2zm0 6v2h2v-2h-2z"/>',

            'search-inspect' => '
                <path fill-rule="evenodd" clip-rule="evenodd" d="M10 4a6 6 0 100 12 6 6 0 000-12zm-8 6a8 8 0 1114.32 4.906l4.387 4.387a1 1 0 01-1.414 1.414l-4.387-4.387A8 8 0 012 10z"/>'
        ];

        return $icons[$name] ?? $icons['heart-pulse'];
    }
}

if (!function_exists('render_neu_icon')) {
    function render_neu_icon($name, $size = 'md', $colorClass = '', $extraStyle = '') {
        $path = get_neu_svg_path($name);
        $sizeClass = in_array($size, ['xs', 'sm', 'md', 'lg', 'xl']) ? $size : 'md';
        $styleAttr = !empty($extraStyle) ? 'style="' . htmlspecialchars($extraStyle) . '"' : '';
        
        return '
        <div class="neu-disc-icon ' . htmlspecialchars($sizeClass . ' ' . $colorClass) . '" ' . $styleAttr . '>
            <svg viewBox="0 0 24 24" fill="currentColor" xmlns="http://www.w3.org/2000/svg">
                ' . $path . '
            </svg>
        </div>';
    }
}
