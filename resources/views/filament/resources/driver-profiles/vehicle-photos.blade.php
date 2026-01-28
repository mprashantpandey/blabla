<div class="grid grid-cols-2 gap-4">
    @foreach($photos as $photo)
        <div>
            <img src="{{ $photo->getUrl() }}" alt="Vehicle Photo" class="w-full rounded-lg">
            <p class="text-sm text-gray-500 mt-1">{{ $photo->created_at->format('M d, Y') }}</p>
        </div>
    @endforeach
</div>

