@include('partials.flash-messages')
<header class="bg-indigo-600">
    <nav class="flex h-16 items-center justify-between mx-auto w-full sm:max-w-7xl">
        <menu-toggle>
            <div slot-scope="{show, toggle}" class="flex items-center justify-between">
                <div
                    class="bg-indigo-600 block gap-4 text-sm text-white lg:flex lg:items-center"
                    :class="{'absolute w-full top-4 lg:relative lg:top-auto': show}"
                >
                    <a
                        href="{{ auth()->check() ? route('dashboard') : url('/') }}"
                        class="block px-4"
                        :class="{'pb-4 lg:pb-0': show}"
                    >
                        @svg('logo')
                    </a>
                    @if (auth()->check())
                        <x-nav-item route="dashboard">Dashboard</x-nav-item>
                        <x-nav-item route="conferences.index">Conferences</x-nav-item>
                        <x-nav-item route="bios.index">Bios</x-nav-item>
                        <x-nav-item route="talks.index">Talks</x-nav-item>
                        <x-mobile-nav-item route="account.show">Account</x-mobile-nav-item>
                        @if (auth()->user()->profile_slug)
                            <x-mobile-nav-item
                                url="{{ route('speakers-public.show', auth()->user()->profile_slug) }}"
                                :show="auth()->user()->enable_profile"
                            >
                                Public Speaker Profile
                            </x-mobile-nav-item>
                        @endif
                        <x-mobile-nav-item route="log-out">Log out</x-mobile-nav-item>
                    @else
                        <x-nav-item route="what-is-this">How it Works</x-nav-item>
                        <x-nav-item route="speakers-public.index">Our speakers</x-nav-item>
                        <x-nav-item route="conferences.index">Conferences</x-nav-item>
                        <x-mobile-nav-item route="login">Sign in with email</x-mobile-nav-item>
                        <x-mobile-nav-item url="{{ url('login/github') }}">Sign in with GitHub</x-mobile-nav-item>
                    @endif
                </div>
                <x-button.mobile-nav/>
            </div>
        </menu-toggle>

        @if (auth()->guest())
            <x-menu class="mr-4">
                <x-slot
                    name="trigger"
                    class="hidden lg:block bg-indigo-700 border border-white px-3 py-2 rounded text-sm text-white"
                >
                    Sign in
                </x-slot>
                <x-slot name="items">
                    <x-menu.item route="login">Sign in with email</x-menu.item>
                    <x-menu.item url="{{ url('login/github') }}">Sign in with GitHub</x-menu.item>
                </x-slot>
            </x-menu>
        @else
            <x-menu class="mr-4">
                <x-slot name="trigger">
                    <img
                        src="{{ auth()->user()->profile_picture_thumb }}"
                        class="hidden inline rounded-full w-8 lg:block"
                    >
                </x-slot>
                <x-slot name="items">
                    <x-menu.item route="account.show">Account</x-menu.item>
                    @if (auth()->user()->profile_slug)
                        <x-menu.item
                            url="{{ route('speakers-public.show', auth()->user()->profile_slug) }}"
                            :show="auth()->user()->enable_profile"
                        >
                            Public Speaker Profile
                        </x-menu.item>
                    @endif
                    <x-menu.item route="log-out">Log out</x-menu.item>
                </x-slot>
            </x-menu>
        @endif
    </nav>
</header>
