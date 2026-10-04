<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center justify-center rounded-xl border border-transparent bg-clinic-500 px-5 py-3 text-sm font-bold text-white shadow-sm transition hover:bg-clinic-600 focus:outline-none focus:ring-2 focus:ring-clinic-500 focus:ring-offset-2']) }}>
    {{ $slot }}
</button>
