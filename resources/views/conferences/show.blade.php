@extends('app')

@section('headerScripts')
<script>
    Symposium.talks = {!! json_encode($talks) !!};
</script>
@endsection

@section('content')

@if ($conference->isFlagged())
    <x-alert.warning>An issue has been reported for this conference.</x-alert.warning>
@endif

<x-panel.conference size="xl" :conference="$conference"></x-panel.conference>

<div class="flex flex-col items-end mt-6 mx-auto w-full">
</div>

<x-panel size="xl" title="My Talks" class="mt-6">
    <talks-on-conference-page conference-id="{{ $conference->id }}"></talks-on-conference-page>
</x-panel>

@endsection
