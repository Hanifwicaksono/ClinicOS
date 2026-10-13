<button {{ $attributes->merge(['type' => 'submit', 'class' => 'clinic-button-danger']) }}>
    {{ $slot }}
</button>
