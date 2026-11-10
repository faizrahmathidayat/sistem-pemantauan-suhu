<div {{ $attributes->merge(['class' => 'overflow-x-auto print:overflow-visible']) }}>
    <table class="w-full min-w-full text-left">{{ $slot }}</table>
</div>
