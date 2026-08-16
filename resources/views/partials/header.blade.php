<header class="border-b border-stone-200 bg-white">
    <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-white focus:px-4 focus:py-2 focus:font-semibold focus:text-brand-700 focus:ring-2 focus:ring-brand-500">
        Saltar al contenido
    </a>

    <div class="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
        <a href="{{ route('home') }}" class="flex items-center gap-2 text-lg font-bold text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
            <span aria-hidden="true" class="inline-flex h-8 w-8 items-center justify-center rounded-md bg-brand-600 font-bold text-white">EA</span>
            El Area Gym
        </a>

        <nav aria-label="Principal">
            @auth
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="rounded-md px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100 hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                        Cerrar sesión
                    </button>
                </form>
            @else
                <a href="{{ route('login') }}" class="rounded-md px-3 py-2 text-sm font-semibold text-stone-700 hover:bg-stone-100 hover:text-stone-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">
                    Iniciar sesión
                </a>
            @endauth
        </nav>
    </div>
</header>
