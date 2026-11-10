@if (session('status'))
    <x-alert variant="success" role="status" class="mb-4">{{ session('status') }}</x-alert>
@endif
@if (session('error'))
    <x-alert variant="error" role="alert" class="mb-4">{{ session('error') }}</x-alert>
@endif
