@props([
    'user' => filament()->auth()->user(),
])

<x-filament::avatar
    :src="filament()->getUserAvatarUrl($user)"
    :alt="__('filament-panels::layout.avatar.alt', ['name' => $user?->name ?? $user?->username ?? $user?->email ?? 'User'])"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-avatar'])
    "
/>
