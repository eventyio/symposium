@extends('app')

@section('content')

<x-panel class="w-full md:w-1/2 mx-auto">
    <h2 class="text-2xl text-center my-4">No new signups</h2>
    <div class="text-center pb-4">
        <p class="text-base mt-4 mb-8">
            This project is being closed down. Please try <a href="https://eventy.io/" class="text-blue-600 hover:text-blue-800 underline">Eventy</a> instead!
        </p>
        <a href="/" class="py-2 rounded inline-flex items-center justify-center space-x-2 font-semibold px-8 text-lg bg-white border border-gray-300 text-gray-500 block mt-4 w-full md:w-auto md:mt-0"><span>Return Home</span></a>
    </div>
</x-panel>

@endsection
