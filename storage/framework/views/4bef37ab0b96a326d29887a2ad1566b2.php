<?php $attributes ??= new \Illuminate\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Illuminate\View\ComponentAttributeBag::extractPropNames((['label', 'name' => null, 'required' => false]));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Illuminate\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter((['label', 'name' => null, 'required' => false]), 'is_string', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>
<?php
    $helpTexts = config('helpTexts');
    // Try exact match, then without asterisk, then normalized
    $key = $label;
    $helpText = $helpTexts[$key]
        ?? $helpTexts[rtrim($key, ' *')]
        ?? $helpTexts[trim(preg_replace('/\s*\*$/', '', $key))]
        ?? null;
?>

<div class="field <?php echo e($attributes->get('class')); ?>">
    <label>
        <?php echo e($label); ?><?php echo e($required ? ' *' : ''); ?>

        <?php if($helpText): ?>
            <button type="button" class="field-help-btn"
                onclick="toggleHelp(this)"
                aria-label="Ayuda para <?php echo e($label); ?>">?</button>
        <?php endif; ?>
    </label>
    <?php if($helpText): ?>
        <div class="field-help-box"><?php echo e($helpText); ?></div>
    <?php endif; ?>
    <?php echo e($slot); ?>

</div>
<?php /**PATH C:\laragon\www\punta\resources\views/components/field-help.blade.php ENDPATH**/ ?>