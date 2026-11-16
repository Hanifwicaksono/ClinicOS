@props(['messages' => $errors->all()])
@if (count($messages))
    <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm text-red-800" role="alert" tabindex="-1">
        <p class="font-bold">Periksa kembali data Anda.</p>
        <ul class="mt-2 list-inside list-disc">
            @foreach ($messages as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif
