<x-mail::layout>
    {{-- Header --}}
    <x-slot:header>
        <x-mail::header :url="config('app.url')">
            {{ config('app.name') }}
        </x-mail::header>
    </x-slot:header>

    {{-- Body --}}
    {{ $slot }}

    {{-- Subcopy --}}
    @isset($subcopy)
        <x-slot:subcopy>
            <x-mail::subcopy>
                {{ $subcopy }}
            </x-mail::subcopy>
        </x-slot:subcopy>
    @endisset

    {{-- Footer — intentionally empty. footer.blade.php (the customized component this renders
        into) already prints its own copyright + unsubscribe line unconditionally, so putting a
        copyright line here too used to make every email show it twice. --}}
    <x-slot:footer>
        <x-mail::footer>
        </x-mail::footer>
    </x-slot:footer>
</x-mail::layout>
