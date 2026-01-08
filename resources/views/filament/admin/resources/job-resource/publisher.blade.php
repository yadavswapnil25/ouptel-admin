<div class="flex items-center gap-2">
    <img src="{{ $avatar }}" alt="{{ $name }}" class="w-8 h-8 rounded-full object-cover" onerror="this.src='{{ asset('images/default-avatar.png') }}'">
    <div>
        <div class="font-medium">{{ $name }}</div>
        @if($username)
            <div class="text-xs text-gray-500">@{{ $username }}</div>
        @endif
    </div>
</div>


