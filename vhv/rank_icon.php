<?php
// Flat long-shadow rank icons. One deterministic SVG is produced for every rank 1-50.
$rank = filter_input(INPUT_GET, 'rank', FILTER_VALIDATE_INT);
$rank = ($rank && $rank >= 1 && $rank <= 50) ? $rank : 1;

$themes = [
    'champion' => ['#244A56', '#F5B72C'], 'knight' => ['#C83232', '#F1B43B'],
    'rising-star' => ['#F7B92B', '#D98520'], 'heart-guard' => ['#31BFA4', '#F4B82B'],
    'sunshine' => ['#E14A3D', '#FFC33A'], 'ncd-guardian' => ['#D94A42', '#FFFFFF'],
    'health-shield' => ['#2BBFA4', '#F8D45C'], 'community-pillar' => ['#F3B52C', '#FFFFFF'],
    'seedling' => ['#34BFA4', '#F7D052'], 'teamwork' => ['#244A56', '#F5B72C'],
    'spark' => ['#D94A42', '#FFF1A8'], 'clover' => ['#F3B52C', '#2E9E84'],
    'wisdom' => ['#244A56', '#F3B52C'], 'sunrise' => ['#34BFA4', '#FFC33A'],
];

function themeForRank($rank) {
    if ($rank === 1) return 'champion'; if ($rank === 2) return 'knight'; if ($rank === 3) return 'rising-star';
    if ($rank === 4) return 'heart-guard'; if ($rank === 5) return 'sunshine';
    $themes = ['ncd-guardian', 'health-shield', 'community-pillar', 'seedling', 'teamwork', 'spark', 'clover', 'wisdom', 'sunrise'];
    return $themes[(int)floor(($rank - 6) / 5)] ?? 'sunrise';
}

$theme = themeForRank($rank);
[$bg, $accent] = $themes[$theme];
$shadow = 'M58 56 L100 98 L100 110 L58 110 Z';

$symbols = [
    'champion' => '<path d="M30 68h40l-3 12H33zM34 65l-7-30 15 11 8-24 8 24 15-11-7 30z" fill="#F5B72C"/><circle cx="50" cy="52" r="5" fill="#D9F7F0"/>',
    'knight' => '<path d="M50 22l24 10v20c0 17-10 27-24 34-14-7-24-17-24-34V32z" fill="#F5B72C"/><path d="M50 34v38M36 53h28" stroke="#C83232" stroke-width="7"/>',
    'rising-star' => '<path d="M50 21l8 19 21 1-16 14 5 22-18-11-18 11 5-22-16-14 21-1z" fill="#FFF0A0"/><path d="M42 79h16l4 8H38z" fill="#A6641F"/>',
    'heart-guard' => '<path d="M50 81C22 65 28 34 42 34c5 0 8 3 8 7 0-4 3-7 8-7 14 0 20 31-8 47z" fill="#FFF5C0"/><path d="M47 44h6v9h9v6h-9v9h-6v-9h-9v-6h9z" fill="#E14A3D"/>',
    'sunshine' => '<circle cx="50" cy="52" r="17" fill="#FFF1A2"/><g stroke="#FFF1A2" stroke-width="6" stroke-linecap="round"><path d="M50 22v-8M50 90v-8M20 52h-8M88 52h-8M29 31l-6-6M77 79l-6-6M71 31l6-6M29 73l-6 6"/></g>',
    'ncd-guardian' => '<path d="M50 22l25 10v21c0 17-10 28-25 35-15-7-25-18-25-35V32z" fill="#FFF"/><path d="M46 36h8v12h12v8H54v12h-8V56H34v-8h12z" fill="#D94A42"/>',
    'health-shield' => '<path d="M50 20l23 10v22c0 16-9 27-23 35-14-8-23-19-23-35V30z" fill="#F8D45C"/><path d="M39 50h22M50 39v22" stroke="#2BBFA4" stroke-width="7"/>',
    'community-pillar' => '<path d="M28 77h44M34 72V42h32v30M31 37h38v5H31z" fill="#FFF"/><circle cx="40" cy="51" r="5" fill="#F3B52C"/><circle cx="60" cy="51" r="5" fill="#F3B52C"/>',
    'seedling' => '<path d="M50 84V50" stroke="#F7D052" stroke-width="7" stroke-linecap="round"/><path d="M50 59C30 57 29 39 31 31c16 2 21 13 19 28zM52 67c5-19 20-21 29-16-4 15-16 20-29 16z" fill="#F7D052"/>',
    'teamwork' => '<circle cx="35" cy="43" r="9" fill="#F5B72C"/><circle cx="65" cy="43" r="9" fill="#F5B72C"/><circle cx="50" cy="70" r="9" fill="#F5B72C"/><path d="M42 48l8 15 8-15M42 48h16M50 63v-8" stroke="#FFF" stroke-width="4"/>',
    'spark' => '<path d="M53 18L31 57h17l-2 27 23-40H52z" fill="#FFF1A8"/>',
    'clover' => '<path d="M50 52c-19-24-35-8-22 5-15 13 3 29 22 5 19 24 37 8 22-5 15-13-3-29-22-5z" fill="#2E9E84"/><path d="M50 67v17" stroke="#2E9E84" stroke-width="6" stroke-linecap="round"/>',
    'wisdom' => '<path d="M28 36c11-5 22-2 22 1 0-3 11-6 22-1v38c-11-5-22-2-22 1 0-3-11-6-22-1z" fill="#F3B52C"/><path d="M50 37v38" stroke="#244A56" stroke-width="3"/>',
    'sunrise' => '<path d="M24 76h52M30 68h40" stroke="#FFC33A" stroke-width="5" stroke-linecap="round"/><path d="M34 66a16 16 0 0132 0" fill="#FFC33A"/><g stroke="#FFC33A" stroke-width="4" stroke-linecap="round"><path d="M50 30v-8M34 36l-6-6M66 36l6-6"/></g>',
];

header('Content-Type: image/svg+xml; charset=utf-8');
header('Cache-Control: public, max-age=604800');
?>
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" role="img" aria-label="ไอคอนฉายาอันดับ <?= $rank ?>">
  <circle cx="50" cy="50" r="48" fill="<?= $bg ?>"/>
  <path d="<?= $shadow ?>" fill="#0D2C54" opacity=".22"/>
  <?= $symbols[$theme] ?>
  <rect x="31" y="78" width="38" height="15" rx="7.5" fill="#0D2C54" opacity=".9"/>
  <text x="50" y="89" text-anchor="middle" font-family="Arial, sans-serif" font-weight="800" font-size="10" fill="#fff">#<?= $rank ?></text>
</svg>
