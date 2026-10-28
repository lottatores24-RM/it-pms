{{--
    IT-PMS mark: a board of three task columns on the brand gradient.
    Self-coloured, so it reads on both the dark sidebar and light pages.
--}}
@php
$gradientId = 'itpms-logo-'.uniqid();
@endphp

<svg viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="IT-PMS" {{ $attributes }}>
    <defs>
        <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="40" y2="40" gradientUnits="userSpaceOnUse">
            <stop offset="0" stop-color="#6366f1" />
            <stop offset="1" stop-color="#7c3aed" />
        </linearGradient>
    </defs>
    <rect width="40" height="40" rx="10" fill="url(#{{ $gradientId }})" />
    <rect x="9" y="10" width="6" height="20" rx="3" fill="#fff" />
    <rect x="17" y="10" width="6" height="14" rx="3" fill="#fff" fill-opacity="0.8" />
    <rect x="25" y="10" width="6" height="9" rx="3" fill="#fff" fill-opacity="0.6" />
</svg>
