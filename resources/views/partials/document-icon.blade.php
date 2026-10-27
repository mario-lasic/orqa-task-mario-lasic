@php
    $extension = strtolower($document->extension ?? '');

    $icon = match ($extension) {
        'pdf' => 'file-earmark-pdf',
        'doc', 'docx' => 'file-earmark-word',
        'xls', 'xlsx', 'csv' => 'file-earmark-excel',
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'file-earmark-image',
        'mp3', 'wav', 'ogg' => 'file-earmark-music',
        'zip', 'rar', '7z' => 'file-earmark-zip',
        'txt', 'md' => 'file-earmark-text',
        default => 'file-earmark',
    };
@endphp

<i class="bi bi-{{ $icon }} me-2" aria-hidden="true"></i>
