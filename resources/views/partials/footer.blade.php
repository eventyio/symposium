@php
    $styles = $is_home ? 'bg-black text-gray-500' : 'text-gray-600 bg-white border-t border-gray-300';
@endphp

<div class="{{ $styles }} py-4 w-full">
    <div class="max-w-md mx-auto font-sans text-lg sm:max-w-6xl">
        <footer>
            <div class="flex flex-col justify-between px-8 text-center lg:flex-row xl:px-0 lg:text-left">
                <div class="hover:underline">
                    <a href="http://eventy.io" target="_blank">&copy; Eventy {{ date('Y') }}</a>
                </div>
            </div>
        </footer>
    </div>
</div>

