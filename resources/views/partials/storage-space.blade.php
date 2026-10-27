@php
    $usedBytes = $user->usedStorageBytes();
    $quotaBytes = $user->storage_quota_bytes;
    $freeBytes = max(0, $quotaBytes - $usedBytes);
@endphp

<p>
    Zauzeto:
    {{ number_format($usedBytes / 1_000_000_000, 1, '.', '') }}/{{ number_format($quotaBytes / 1_000_000_000, 1, '.', '') }} GB
    ({{ number_format($freeBytes, 0, ',', '.') }} B slobodno)
</p>
