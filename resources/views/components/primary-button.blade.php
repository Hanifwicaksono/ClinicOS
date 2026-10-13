<button {{ $attributes->merge(['type' => 'submit', 'class' => 'clinic-button-primary']) }}>
    {{ $slot }}
</button>
