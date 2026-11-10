@props(['message' => 'Perubahan belum disimpan. Periksa isian berikut:'])

@if ($errors->any())
    <x-alert variant="error" role="alert" class="mb-4">
        <p class="font-semibold">{{ $message }}</p>
        <ul class="mt-1 list-disc pl-5">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </x-alert>
@endif
