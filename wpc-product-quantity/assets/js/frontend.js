'use strict';

(function ($) {
    var woopq_timeout = null;

    $(function () {
        woopq_init_qty();
    });

    $(document).on('keyup', '[name="quantity"]', function () {
        if ($(this).closest('.variations_form').length &&
            $(this).closest('.woopq-quantity').length) {
            $(this).closest('.variations_form').data('woopq_changed', 1);
        }
    });

    $(document).on('click touch',
        '.woopq-quantity-input-plus, .woopq-quantity-input-minus',
        function () {
            if ($(this).closest('.variations_form').length &&
                $(this).closest('.woopq-quantity').length) {
                $(this).closest('.variations_form').data('woopq_changed', 1);
            }
        });

    $(document).on('found_variation', function (e, t) {
        var $quantity = $(e['target']).closest('.variations_form').find('[name="quantity"]').closest('.woopq-quantity');
        var value = parseFloat($quantity.find('.qty').val());
        var changed = $(e['target']).closest('.variations_form').data('woopq_changed');

        if (t.woopq_qty != undefined) {
            $quantity.replaceWith(woopq_decode_entities(t.woopq_qty));
        }

        if (changed === undefined) {
            // did not change qty
            woopq_init_qty_e($(e['target']).closest('.variations_form').find('[name="quantity"]').closest('.woopq-quantity'), t.woopq_min, t.woopq_max, t.woopq_step,
                t.woopq_value);
        } else {
            woopq_init_qty_e($(e['target']).closest('.variations_form').find('[name="quantity"]').closest('.woopq-quantity'), t.woopq_min, t.woopq_max, t.woopq_step,
                value);
        }

        $(document.body).trigger('woopq_replace_qty');
        //TODO: check if is woobt/ woosb/ woosg/ woofs
    });

    $(document).on('reset_data', function (e) {
        var $quantity = $(e['target']).closest('.variations_form').find('[name="quantity"]').closest('.woopq-quantity');
        var value = parseFloat($quantity.find('.qty').val());
        var changed = $(e['target']).closest('.variations_form').data('woopq_changed');
        var variable_qty = $(e['target']).closest('.variations_form').find('.woopq-quantity-variable').attr('data-qty');

        if (variable_qty != undefined) {
            $quantity.replaceWith(woopq_decode_entities(variable_qty));
        }

        if (changed === undefined) {
            woopq_init_qty_e($(e['target']).closest('.variations_form').find('[name="quantity"]').closest('.woopq-quantity'), null, null, null, null);
        } else {
            woopq_init_qty_e($(e['target']).closest('.variations_form').find('[name="quantity"]').closest('.woopq-quantity'), null, null, null, value);
        }

        $(document.body).trigger('woopq_reset_qty');
        //TODO: check if is woobt/ woosb/ woosg/ woofs
    });

    $(document).on('woosq_loaded', function () {
        $('#woosq-popup ').find('.woopq-quantity').each(function () {
            woopq_init_qty_e($(this));
        });
    });

    if (woopq_vars.auto_correct === 'out_of_focus') {
        $(document).on('focusout', '.woopq-quantity input.qty', function () {
            var $this = $(this);

            woopq_disable_atc($this);
            woopq_check_qty($this);
        });
    } else {
        $(document).on('keyup', '.woopq-quantity input.qty', function () {
            var $this = $(this);

            woopq_disable_atc($this);

            if (woopq_timeout != null) clearTimeout(woopq_timeout);
            woopq_timeout = setTimeout(woopq_check_qty, woopq_vars.timeout, $this);
        });
    }

    $(document).on('click touch',
        '.woopq-quantity-input-plus, .woopq-quantity-input-minus',
        function () {
            // get values
            var $qty = $(this).closest('.woopq-quantity-input').find('input.qty'), val = parseFloat($qty.val()),
                max = parseFloat($qty.attr('max')),
                min = parseFloat($qty.attr('min')), step = $qty.attr('step');

            woopq_disable_atc($qty);

            // format values
            if (!val || val === '' || val === 'NaN') {
                val = 0;
            }

            if (max === '' || max === 'NaN') {
                max = '';
            }

            if (min === '' || min === 'NaN') {
                min = 0;
            }

            if (step === 'any' || step === '' || step === undefined ||
                parseFloat(step) === 'NaN') {
                step = 1;
            } else {
                step = parseFloat(step);
            }

            // change the value
            if ($(this).is('.woopq-quantity-input-plus')) {
                if (max && (val + step >= max)) {
                    $qty.val(max);
                } else {
                    $qty.val((val + step).toFixed(woopq_decimal_places(step)));
                }
            } else {
                if (min && (val - step <= min)) {
                    $qty.val(min);
                } else if (val > 0) {
                    $qty.val((val - step).toFixed(woopq_decimal_places(step)));
                }
            }

            woopq_check_qty($qty);
        });
})(jQuery);

function woopq_init_qty(min = null, max = null, step = null, val = null) {
    jQuery('.woopq-quantity-hidden').each(function () {
        var $this = jQuery(this);
        var $this_atc = $this.closest('form.cart').find('[name="add-to-cart"]');

        if ($this_atc.length) {
            if ($this.hasClass('woopq-quantity-disabled')) {
                $this_atc.addClass('woopq-atc-disabled');
            } else {
                $this_atc.removeClass('woopq-atc-disabled');
            }
        }
    });

    jQuery('.woopq-quantity').each(function () {
        woopq_init_qty_e(jQuery(this), min, max, step, val);
    });
}

function woopq_init_qty_e($e, min = null, max = null, step = null, val = null) {
    var $qty = $e.find('.qty');
    var _min = parseFloat((min != null) ? min : $e.attr('data-min'));
    var _max = parseFloat((max != null) ? max : $e.attr('data-max'));
    var _step = parseFloat((step != null) ? step : $e.attr('data-step'));
    var _val = parseFloat((val != null) ? val : $e.attr('data-value'));

    woopq_disable_atc($qty);

    $qty.attr('min', _min).attr('max', _max).attr('step', _step).val(_val);

    jQuery(document.body).trigger('woopq_init_qty', [$qty, _val, _min, _max, _step]);

    woopq_check_qty($qty);
}

function woopq_check_qty($qty) {
    var val = parseFloat($qty.val());
    var min = parseFloat($qty.attr('min'));
    var max = parseFloat($qty.attr('max'));
    var step = parseFloat($qty.attr('step'));
    var fix = Math.pow(10, Number(woopq_decimal_places(step)) + 2);

    if ((step === '') || isNaN(step) || step <= 0) {
        step = 1;
    }

    if ((min === '') || isNaN(min) || min < 0) {
        min = step;
    }

    if ((val === '') || isNaN(val) || val < 0 || val < min) {
        val = min;
    }

    var remainder_before = woopq_float_remainder(
        Math.round(fix * (val - min)) / fix, step);

    if (remainder_before > 0) {
        if (woopq_vars.rounding === 'up') {
            val = (val * fix - remainder_before * fix + step * fix) / fix;
        } else {
            val = (val * fix - remainder_before * fix) / fix;
        }
    }

    if (!isNaN(min) && (val < min)) {
        val = min;
    }

    if (!isNaN(max) && (val > max)) {
        val = max;
    }

    var remainder = woopq_float_remainder(Math.round(fix * (val - min)) / fix,
        step);

    if (remainder > 0) {
        val = (val * fix - remainder * fix) / fix;
    }

    $qty.val(val).trigger('change');
    woopq_enable_atc($qty);

    jQuery(document.body).trigger('woopq_check_qty', [$qty, val, min, max, step]);
}

function woopq_enable_atc($qty) {
    $qty.closest('form.cart').find('.single_add_to_cart_button').removeClass('woopq-disabled');
}

function woopq_disable_atc($qty) {
    $qty.closest('form.cart').find('.single_add_to_cart_button').addClass('woopq-disabled');
}

function woopq_decimal_places(num) {
    var match = ('' + num).match(/(?:\.(\d+))?(?:[eE]([+-]?\d+))?$/);

    if (!match) {
        return 0;
    }

    return Math.max(0, // Number of digits right of decimal point.
        (match[1] ? match[1].length : 0)
        // Adjust for scientific notation.
        - (match[2] ? +match[2] : 0));
}

function woopq_float_remainder(val, step) {
    var valDecCount = (val.toString().split('.')[1] || '').length;
    var stepDecCount = (step.toString().split('.')[1] || '').length;
    var decCount = valDecCount > stepDecCount ? valDecCount : stepDecCount;
    var valInt = parseInt(val.toFixed(decCount).replace('.', ''));
    var stepInt = parseInt(step.toFixed(decCount).replace('.', ''));

    return (valInt % stepInt) / Math.pow(10, decCount);
}

function woopq_decode_entities(encodedString) {
    var textArea = document.createElement('textarea');
    textArea.innerHTML = encodedString;

    return textArea.value;
}