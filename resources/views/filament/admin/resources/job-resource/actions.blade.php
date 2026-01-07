<button type="button" 
        onclick="if(confirm('Are you sure you want to delete this job?')) { Livewire.emit('deleteJob', {{ $record->id }}); }"
        class="inline-flex items-center px-3 py-1 text-sm font-medium text-white bg-red-600 rounded hover:bg-red-700">
    Delete
</button>



