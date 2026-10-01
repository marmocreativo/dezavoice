@php
    $user = auth()->user();
    $membership = $user->memberships()->active()->first();
    $avatarUrl = $user->avatar_path
        ? \Illuminate\Support\Facades\Storage::disk('public')->url($user->avatar_path)
        : null;
@endphp

<div style="padding: 16px; border-bottom: 1px solid rgba(255,255,255,0.08); margin-bottom: 8px;">
    <div style="display: flex; align-items: center; gap: 10px;">
        @if($avatarUrl)
            <img src="{{ $avatarUrl }}" alt="{{ $user->name }}" style="width: 40px; height: 40px; border-radius: 9999px; object-fit: cover; flex-shrink: 0;">
        @else
            <div style="width: 40px; height: 40px; border-radius: 9999px; background: #FF6A1A; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 15px; flex-shrink: 0;">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
        @endif
        <div style="min-width: 0;">
            <div style="font-weight: 600; color: white; font-size: 14px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                {{ $user->name }}
            </div>
            @if($membership)
                <div style="font-size: 11px; color: #9CA3AF; text-transform: capitalize;">
                    {{ str_replace('_', ' ', $membership->role) }}
                </div>
            @endif
        </div>
    </div>
</div>