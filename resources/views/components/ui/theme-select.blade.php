@props([
    'options' => [],
    'placeholder' => 'Select Option',
    'class' => 'w-full',
    'triggerClass' => '',
    'searchable' => false,
    'align' => 'left',
])

<x-ui.themed-select
    :options="$options"
    :placeholder="$placeholder"
    :class="$class"
    :triggerClass="$triggerClass"
    :searchable="$searchable"
    :align="$align"
    {{ $attributes }}
>
    {{ $slot }}
</x-ui.themed-select>
