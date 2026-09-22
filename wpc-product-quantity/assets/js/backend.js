'use strict';

(function ($) {
    $(function () {
        init_options();
        init_terms();
        init_roles();
        init_sortable();
        init_simulator();
    });

    $(document).on('change',
        '.woopq_active_input, .woopq_active_select, select.woopq_type',
        function () {
            init_options($(this).closest('.woopq_settings_form'));
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
            nonce: woopq_admin_vars.nonce,
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
            nonce: woopq_admin_vars.nonce,
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
            ($(e.target).closest('.woopq-item-remove').length === 0) &&
            ($(e.target).closest('.woopq-item-summary').length === 0)) {
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

    $(document).on('click touch', '.woopq-item-summary', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $item = $(this).closest('.woopq-item');
        var key = $item.find('.woopq-item-name-key').text().replace('#', '').trim();
        var ruleName = '#' + key;

        var isOverride = $item.closest('.woopq_product_settings').length > 0;

        // Apply for
        var applyInc = $item.find('[name$="[apply_inc]"]').val() === 'all' ? 'Include all' : 'Include either';
        var applyText = $item.find('.woopq-item-name-apply').text().trim();

        // User roles
        var rolesInc = $item.find('[name$="[roles_inc]"]').val() === 'all' ? 'Include all' : 'Include either';
        var rolesText = [];
        $item.find('.woopq_roles_select option:selected').each(function () {
            rolesText.push($(this).text().trim());
        });

        // Type & Values
        var typeText = $item.find('.woopq_type option:selected').text().trim();
        var typeVal = $item.find('.woopq_type').val();

        var min = $item.find('input[name$="[min]"]').val();
        var step = $item.find('input[name$="[step]"]').val();
        var max = $item.find('input[name$="[max]"]').val();
        var defVal = $item.find('input[name$="[value]"]').val();
        var values = $item.find('textarea[name$="[values]"]').val();

        // Build HTML
        var html = '<div class="woopq-sum-section">';
        html += '<div class="woopq-sum-status active"><span class="woopq-sum-dot"></span> Active</div>';
        if (key && key !== 'default') {
            html += '<div class="woopq-sum-badge">#' + key + '</div>';
        }
        html += '</div>';

        if (!isOverride && key !== 'default') {
            html += '<div class="woopq-sum-section">';
            html += '<div class="woopq-sum-label">Apply for</div>';
            if (applyText === 'all products' || applyText === '') {
                html += '<div class="woopq-sum-detail"><strong class="woopq-sum-type">' + applyText + '</strong></div>';
            } else {
                html += '<div class="woopq-sum-detail"><strong class="woopq-sum-type">' + applyInc + '</strong> (' + applyText + ')</div>';
            }
            html += '</div>';
        }

        html += '<div class="woopq-sum-section">';
        html += '<div class="woopq-sum-label">User roles</div>';
        html += '<div class="woopq-sum-detail"><strong class="woopq-sum-type">' + rolesInc + '</strong>';
        if (rolesText.length > 0) {
            html += ' (' + rolesText.join(', ') + ')';
        }
        html += '</div></div>';

        html += '<div class="woopq-sum-section">';
        html += '<div class="woopq-sum-label">Quantity Settings</div>';
        html += '<div class="woopq-sum-detail"><span><strong>Type:</strong> ' + typeText + '</span></div>';
        if (typeVal === 'default') {
            html += '<div class="woopq-sum-detail">';
            html += '<span><strong>Min:</strong> ' + (min !== '' ? min : '0') + '</span> &bull; ';
            html += '<span><strong>Step:</strong> ' + (step !== '' ? step : '1') + '</span> &bull; ';
            html += '<span><strong>Max:</strong> ' + (max !== '' ? max : '&infin;') + '</span> &bull; ';
            html += '<span><strong>Default:</strong> ' + (defVal !== '' ? defVal : '0') + '</span>';
            html += '</div>';
        } else {
            html += '<div class="woopq-sum-detail"><pre style="margin-top: 5px; background: #f1f5f9; padding: 10px; border-radius: 4px; font-size: 12px; white-space: pre-wrap; font-family: monospace;">' + values + '</pre></div>';
        }
        html += '</div>';

        if ($('#woopq-summary-modal').length === 0) {
            $('body').append('<div id="woopq-summary-modal"><div class="woopq-summary-content"></div></div>');
        }

        $('#woopq-summary-modal').attr('title', 'Rule Summary ' + ruleName).find('.woopq-summary-content').html(html);
        if ($.fn.dialog) {
            $('#woopq-summary-modal').dialog({
                modal: true,
                width: 520,
                dialogClass: 'wpc-dialog woopq-dialog woopq-summary-dialog',
                open: function () {
                    $('.ui-widget-overlay').bind('click', function () {
                        $('#woopq-summary-modal').dialog('close');
                    });
                },
                buttons: {
                    'Close': function () {
                        $(this).dialog('close');
                    }
                }
            });
        } else {
            alert("Dialog not loaded!");
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
                            nonce: woopq_admin_vars.nonce,
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

    function init_options($context) {
        $context = $context || $(document);

        $context.find('.woopq_active_input:checked').addBack('.woopq_active_input:checked').each(function () {
            if ($(this).val() == 'overwrite') {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').show();
            } else {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').hide();
            }
        });

        $context.find('.woopq_active_select').addBack('.woopq_active_select').each(function () {
            if ($(this).val() == 'overwrite') {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').show();
            } else {
                $(this).closest('.woopq_settings_form').find('.woopq_show_if_overwrite').hide();
            }
        });

        $context.find('select.woopq_type').addBack('select.woopq_type').each(function () {
            var _val = $(this).val();

            $(this).closest('.woopq_settings_form').find('.woopq_show_if_type').hide();
            $(this).closest('.woopq_settings_form').find('.woopq_show_if_type_' + _val).show();
        });
    }

    $(document).on('click', '.woopq_expand_all', function (e) {
        e.preventDefault();
        $('.woopq-item').addClass('active');
    });

    $(document).on('click', '.woopq_collapse_all', function (e) {
        e.preventDefault();
        $('.woopq-item').removeClass('active');
    });

    // ──── Simulator ─────────────────────────────────────────────
    function init_simulator() {
        if ($('#woopq-sim-product').length) {
            $('#woopq-sim-product').select2({
                ajax: {
                    url: ajaxurl,
                    dataType: 'json',
                    delay: 250,
                    data: function (params) {
                        return {
                            term: params.term,
                            action: 'woocommerce_json_search_products_and_variations',
                            security: typeof woocommerce_admin_meta_boxes !== 'undefined' ?
                                woocommerce_admin_meta_boxes.search_products_nonce :
                                (typeof woopq_admin_vars !== 'undefined' ? woopq_admin_vars.search_nonce : '')
                        };
                    },
                    processResults: function (data) {
                        var terms = [];
                        if (data) {
                            $.each(data, function (id, text) {
                                terms.push({
                                    id: id,
                                    text: text
                                });
                            });
                        }
                        return {
                            results: terms
                        };
                    },
                    cache: true
                },
                minimumInputLength: 3
            });
        }

        $(document).on('click touch', '#woopq-sim-run', function (e) {
            e.preventDefault();

            var $btn = $(this);
            var product_id = $('#woopq-sim-product').val();
            var role = $('#woopq-sim-role').val();
            var $results = $('#woopq-sim-results');
            var $spinner = $('#woopq-sim-spinner');

            if (!product_id) {
                alert('Please select a product to simulate.');
                return;
            }

            $btn.prop('disabled', true);
            $spinner.addClass('is-active');
            $results.slideUp().empty();

            $.post(ajaxurl, {
                action: 'woopq_simulate',
                nonce: woopq_admin_vars.nonce,
                product_id: product_id,
                role: role
            }, function (response) {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');

                if (response.success) {
                    $results.html(response.data.html).slideDown();
                } else {
                    $results.html('<div style="color: #d63638; padding: 10px; background: #fcf0f1; border-left: 4px solid #d63638;">' + (response.data.message || 'An error occurred.') + '</div>').slideDown();
                }
            }).fail(function () {
                $btn.prop('disabled', false);
                $spinner.removeClass('is-active');
                alert('AJAX error during simulation.');
            });
        });

        $(document).on('click touch', '#woopq-sim-reset', function (e) {
            e.preventDefault();
            if ($('#woopq-sim-product').length) {
                $('#woopq-sim-product').val(null).trigger('change');
            }
            $('#woopq-sim-role').val('woopq_guest');
            $('#woopq-sim-results').slideUp().empty();
        });
    }
})(jQuery);