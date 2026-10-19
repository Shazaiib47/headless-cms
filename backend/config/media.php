<?php

return [

    // Where uploads are stored. Cloud storage later is just a different disk.
    'disk' => env('MEDIA_DISK', 'public'),

    'directory' => 'media',

    // Largest accepted upload, in kilobytes.
    'max_kilobytes' => 5120,

    // Decompression-bomb guard: refuse images with more pixels than this.
    'max_pixels' => 16_000_000,

    // Longest side after processing. Larger images are scaled down.
    'max_dimension' => 2560,

    'thumbnail_width' => 480,

    'jpeg_quality' => 85,

    'webp_quality' => 85,

    // The only accepted types, detected from file contents. Value = stored extension.
    'allowed' => [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ],

];
