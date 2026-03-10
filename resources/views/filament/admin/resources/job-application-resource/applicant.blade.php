<div>
    <div class="font-medium text-sm">{{ $name }}</div>
    @if($email)
        <div class="text-xs text-gray-500">{{ $email }}</div>
    @endif
    @if($phone)
        <div class="text-xs text-gray-400">{{ $phone }}</div>
    @endif
</div>
