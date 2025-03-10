'use strict';

(function ($) {
    $(function () {
        init_options();
        init_terms();
        init_roles();
        init_sortable();
    });

    $(document).on('change',
        '.woopq_active_input, .woopq_active_select, select.woopq_type',
        function () {
            init_options();
        });

    $(document).on('change', '.woopq_apply', function () {
        init_terms();
    });

    $(document).on('click touch', '.woopq-add-rule-btn', function () {
        let $this = $(this), product_id = $this.data('product_id'),
            is_variation = $this.data('is_variation'),
            $rules = $this.closest('.woopq-rules-wrapper').find('.woopq-rules');

        $this.prop('disabled', true);
        $rules.addClass('woopq-items-loading');

        $.post(ajaxurl, {
            action: 'woopq_add_rule',
            product_id: product_id,
            is_variation: is_variation,
        }, function (response) {
            $rules.append(response);
            $this.prop('disabled', false);
            $rules.find('.woopq-item:last-child').addClass('active');
            $rules.removeClass('woopq-items-loading');
            init_options();
            init_terms();
            init_roles();
        });
    });

    $(document).on('click touch', '.woopq-item-duplicate', function () {
        let $this = $(this), product_id = $this.data('product_id'),
            is_variation = $this.data('is_variation'),
            $rules = $this.closest('.woopq-rules'),
            $rule = $this.closest('.woopq-rule'),
            rule_data = $rule.find('input, select, button, textarea').serialize() ||
                0;

        $rules.addClass('woopq-items-loading');

        $.post(ajaxurl, {
            action: 'woopq_add_rule',
            product_id: product_id,
            is_variation: is_variation,
            rule_data: rule_data,
        }, function (response) {
            $(response).addClass('active').insertAfter($rule);
            $rules.removeClass('woopq-items-loading');
            init_options();
            init_terms();
            init_roles();
        });
    });

    $(document).on('change', '.woopq_apply, .woopq_apply_val', function () {
        init_apply_label($(this).closest('.woopq-item'));
    });

    $(document).on('click touch', '.woopq-item-header', function (e) {
        if (($(e.target).closest('.woopq-item-duplicate').length === 0) &&
            ($(e.target).closest('.woopq-item-remove').length === 0)) {
            $(this).closest('.woopq-item').toggleClass('active');
        }
    });

    $(document).on('click touch', '.woopq-item-remove', function () {
        var r = confirm(
            'Do you want to remove this rule? This action cannot undo.');

        if (r == true) {
            $(this).closest('.woopq-item').remove();
        }
    });

    $('#woocommerce-product-data').on('woocommerce_variations_loaded', function () {
        init_options();
        init_terms();
        init_roles();
    });

    function init_terms() {
        $('.woopq_terms').each(function () {
            var $this = $(this);
            var apply = $this.closest('.woopq-item').find('.woopq_apply').val();

            if (apply === 'woopq_all') {
                $this.closest('.woopq-item').find('.hide_if_apply_all').hide();
            } else {
                $this.closest('.woopq-item').find('.hide_if_apply_all').show();
            }

            $this.selectWoo({
                ajax: {
                    url: ajaxurl, dataType: 'json', delay: 250, data: function (params) {
                        return {
                            q: params.term, action: 'woopq_search_term', taxonomy: apply,
                        };
                    }, processResults: function (data) {
                        var options = [];

                        if (data) {
                            $.each(data, function (index, text) {
                                options.push({id: text[0], text: text[1]});
                            });
                        }
                        return {
                            results: options,
                        };
                    }, cache: true,
                }, minimumInputLength: 1,
            });

            if ($this.data(apply) !== undefined && $this.data(apply) !== '') {
                $this.val(String($this.data(apply)).split(',')).change();
            } else {
                $this.val([]).change();
            }
        });
    }

    function init_sortable() {
        $('.woopq-rules').sortable({
            handle: '.woopq-item-move',
        });
    }

    function init_roles() {
        $('.woopq_roles_select').selectWoo();
    }

    function init_apply_label($item) {
        let apply = $item.find('.woopq_apply').val(),
            apply_val = $item.find('.woopq_apply_val').val().join(),
            apply_label = '';

        if (apply === 'woopq_all' || $item.hasClass('woopq-item-default')) {
            apply_label = 'all products';
        } else {
            apply_label = apply + ': ' + apply_val;
        }

        $item.find('.woopq-item-name-apply').html(apply_label);
    }

    function init_options() {
        $('.woopq_active_input:checked').each(function () {
            if ($(this).val() == 'overwrite') {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').show();
            } else {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').hide();
            }
        });

        $('.woopq_active_select').each(function () {
            if ($(this).val() == 'overwrite') {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').show();
            } else {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').hide();
            }
        });

        $('select.woopq_type').each(function () {
            var _val = $(this).val();

            $(this).closest('.woopq_settings_form').find('.woopq_show_if_type').hide();
            $(this).closest('.woopq_settings_form').find('.woopq_show_if_type_' + _val).show();
        });
    }
})(jQuery);