<nav class="flex flex-1 justify-end gap-2">
    @auth
        <a
            href="{{ url('/dashboard') }}"
            class="clinic-button-primary"
        >
            Dashboard
        </a>
    @else
        <a
            href="{{ route('login') }}"
            class="clinic-button-secondary"
        >
            Masuk
        </a>

        @if (Route::has('register'))
            <a
                href="{{ route('register') }}"
                class="clinic-button-primary"
            >
                Daftar
            </a>
        @endif
    @endauth
</nav>
