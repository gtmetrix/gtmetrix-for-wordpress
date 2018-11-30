jQuery(function ($) {

    if ($.inArray(pagenow, new Array('toplevel_page_gfw_settings', 'gtmetrix_page_gfw_settings', 'toplevel_page_gfw_tests', 'gtmetrix_page_gfw_schedule')) > 0) {
        $('.if-js-closed').removeClass('if-js-closed').addClass('closed');
        postboxes.add_postbox_toggles(pagenow);
    }

    if($.fn.tooltip) {
        $( '.tooltip' ).tooltip({
            show: false,
            hide: false
        });
    }

    if ($('#gfw_url').length) {
        $( '#gfw_url' ).autocomplete({   
            source: function( request, response ) {
                $.ajax({
                    url: ajaxurl,
                    dataType: 'json',
                    data: {
                        action: 'autocomplete',
                        term: request.term
                    },
                    success: function( data ) {
                        response( $.map( data, function( item ) {
                            return {
                                label: item.title,
                                value: item.permalink
                            };
                        }));
                    }
                });
            },
            minLength: 2
        });
    }

    function placeholderSupport() {
        var input = document.createElement('input');
        var supported = ('placeholder' in input);
        if (!supported) {
            $('.gfw-placeholder-alternative').show();
        }
    }

    placeholderSupport();

    $( '#gfw-scan' ).dialog({
        autoOpen: false,
        height: 'auto',
        width: 350,
        draggable: true,
        modal: true,
        buttons: {
            'Close': function() {
                $( this ).dialog( 'close' );
            }
        }
    });

    $('#gfw-parameters').submit(function(event) {
        event.preventDefault();
        $('#gfw-screenshot').css('background-image','url(../wp-content/plugins/gtmetrix-for-wordpress/images/loading.gif)');
        $('#gfw-screenshot .gfw-message').text('').hide();
        $('#gfw-scanner').show();
        $( '#gfw-scan' ).dialog( 'open' );
        q(0);

        $.ajax({
            url: ajaxurl,
            dataType: 'json',
            type: 'POST',
            data: {
                action: 'save_report',
                fields: $(this).serialize(),
                security : gfwObject.gfwnonce
            },
            cache: false,
            success: function(data) {
                if (data.error) {
                    $('#gfw-scanner').hide();
                    $('#gfw-screenshot').css('background-image','url(../wp-content/plugins/gtmetrix-for-wordpress/images/exclamation.png)');
                    $('#gfw-screenshot .gfw-message').html( data.error ).show();
                } else {
                    $('#gfw-screenshot').css('background-image','url(' + data.screenshot + ')');
                    window.setTimeout(
                        function() {
                            $('#gfw-scan').dialog('close');
                            location.reload();
                        },
                        1000
                    );
                }
            }
        });
    });

    function q(e) {
        var n = $('#gfw-scanner'),
        r = n.height() ? !0 : !1;
        !r && !n.height() ? (setTimeout(function () {
            q();
        }, 500), r = !0) : n.animate({
            top: (e ? '-' : '+') + '=221'
        }, 2E3, function () {
            if ($('#gfw-scan').dialog('isOpen')) {
                q(!e);
            } else {
                $('#gfw-scanner').css('top', -7);
            }
        });
    }

    $('table.gfw-table').on('click', 'td.gfw-toggle', function() {
        if ($(this).parents('tr').hasClass('report-expanded')) {
            $(this).parents('tr').removeClass('report-expanded').addClass('report-collapsed').next().hide();
        } else if ($(this).parents('tr').hasClass('report-collapsed')) {
            $(this).parents('tr').removeClass('report-collapsed').addClass('report-expanded').next().show();
        } else {
            var newRow = '<tr><td colspan="' + $(this).parents('tr').find('td').length + '" style="padding:0"></td></tr>';
            $(this).parents('tr').addClass('report-expanded').after(newRow);
            var recordId = $(this).parents('tr').attr('id').substring(5);
            $(this).parents('tr').next().find('td').load(ajaxurl, {
                action: 'expand_report',
                id: recordId
            });
        }
        return false;
    });

    $(document).on('click', '.gfw-open-graph', function(event) {
        event.preventDefault();
        var eventId = $(this).attr('href');
        var graph = $(this).attr('id');

        $.ajax({
            url: ajaxurl,
            cache: false,
            dataType: 'json',
            data: {
                action: 'report_graph',
                id: eventId,
                graph: graph
            },
            success:  function( series ) {
                var options = {
                    series: {
                        lines: {
                            show: true
                        },
                        points: {
                            show: true
                        }},
                    xaxis: {
                        mode: 'time',
                        timeformat: '%b %d %H:%M%P'
                    },
                    grid: {
                        backgroundColor: {
                        colors: ['#fff', '#eee']
                    }},
                    legend: {
                        container: '#gfw-graph-legend',
                        noColumns: 2
                    }};

                switch (graph) {
                    case 'gfw-scores-graph':
                        graphTitle = 'PageSpeed and YSlow Scores';
                        options.yaxis = {
                            ticks: 5,
                            min: 0,
                            max: 100,
                            tickFormatter: function (val) {
                                return val + '%';
                            }
                        };
                        break;
                    case 'gfw-times-graph':
                        graphTitle = 'Page Load Times';
                        options.yaxis = {
                            ticks: 5,
                            min: 0,
                            tickFormatter: function (val) {
                                return val.toFixed(1) + ' s';
                            }
                        };
                        break;
                    case 'gfw-sizes-graph':
                        graphTitle = 'Page Sizes';
                        options.yaxis = {
                            ticks: 5,
                            min: 0,
                            tickFormatter: function (val) {
                                return val + ' KB';
                            }
                        };
                        break;
                }

                var placeholder = $('#gfw-flot-placeholder');
                $( '#gfw-graph' ).dialog( 'open' );
                $( '#gfw-graph' ).dialog( 'option', 'title', graphTitle );
                $.plot(placeholder, series, options);

            }
        });
    });

    $( '#gfw-confirm-delete' ).dialog({
        autoOpen: false,
        resizable: false,
        modal: true,
        buttons: {
            'Yes': function() {
                window.location.href = $(this).data('url');
            },
            'No': function() {
                $( this ).dialog( 'close' );
            }
        }
    });

    $(document).on('click', '.gfw-delete-icon', function(event) {
        event.preventDefault();
        $('#gfw-confirm-delete').data('url', event.target);
        $( '#gfw-confirm-delete' ).dialog( 'open' );
    });

    $( '#gfw-video' ).dialog({
        autoOpen: false,
        height: 'auto',
        width: 'auto',
        draggable: true,
        resizable: true,
        modal: true,
        buttons: {
            'Close': function() {
                $( this ).dialog( 'close' );
            }
        },
        close: function(){
            $('#gfw-video iframe').remove();
        }
    });

    $(document).on('click', '.gfw-video-icon', function(event) {
        event.preventDefault();
        $('#gfw-video').prepend($('<iframe height="483" width="560" scrolling="no" frameborder="0" mozallowfullscreen="true" webkitallowfullscreen="true" allowfullscreen="true" />').attr('src', $(this).attr('href'))).dialog('open');
    });

    $( '#gfw-graph' ).dialog({
        autoOpen: false,
        height: 'auto',
        width: 850,
        draggable: true,
        modal: true,
        buttons: {
            'Close': function() {
                $( this ).dialog( 'close' );
            }
        }
    });

    $('.gfw-conditions').on('change', 'select[name^="gfw_condition"]', function() {
        $(this).siblings('select:not(.' + $(this).val() + ')').hide();
        $(this).siblings('select.' + $(this).val()).show();
    });

    $('#gfw-add-condition a').bind('click', function() {
        $('.gfw-conditions:hidden:first').show().find('.gfw-condition').removeAttr('disabled').trigger('change');
        if ($('.gfw-conditions:visible').length == 4)
            $(this).parents('tr').hide();
    });

    $(document).on('click', '.gfw-remove-condition', function() {
        $(this).parents('tr').hide().find('.gfw-condition').attr('disabled', 'disabled');
        $('#gfw-add-condition').show();
    });

    if (! $('#gfw-notifications').attr('checked'))
        $('.gfw-conditions select:visible').attr('disabled', 'disabled');

    $('input#gfw-notifications').bind('change', function() {
        if ($(this).is(':checked')) {
            $('.gfw-conditions select:visible').removeAttr('disabled');
            if ($('.gfw-conditions:visible').length < 4) {
                $('#gfw-add-condition').show();
            }
        } else {
            $('.gfw-conditions select:visible').attr('disabled', 'disabled');
            $('#gfw-add-condition').hide();
        }
        return false;
    });

    $('#gfw-test-front').bind('click', function() {
        $('#gfw_url').val($('#gfw-front-url').val());
        $('#gfw-parameters').submit();
        return false;
    });

    $('#gfw-reset').bind('click', function() {
        $.ajax({
            url: ajaxurl,
            cache: false,
            data: {
                action: 'reset',
                security : gfwObject.gfwnonce
            },
            success: function() {
                $('#gfw-reset').val('Done').attr('disabled', 'disabled');
            }
        });
    });

});
