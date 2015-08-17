window.progressInterval;
window.prevpc;
window.hasError = false;
window.finished = false;
window.pollingPeriod = 1000;
window.updatePeriod = 250;
window.fileCheckUpdatePeriod = 5000;
window.lastData = null;
window.lastUpdate;
window.progressFn = 'var/progress-' + Math.floor(Math.random()*100000) + '.json';

$(function() {
    // toggle tooltips
    $('[data-toggle="tooltip"]').tooltip();

    $('#file-table .expand').click(function(){
        $(this).parent().next('tr').slideToggle(200);
        $(this).parent().next('tr').find('.slider').slideToggle(200);
        $(this).children('span').toggleClass('glyphicon-chevron-down').toggleClass('glyphicon-chevron-up');
    });

    // store the table name in the orig data attribute for each table
    $('.tableName').each(function() {
        $(this).data('orig', $(this).text());
    });
    // Same as above
    $('.databaseName').each(function() {
        $(this).data('orig', $(this).text());
    });

    // make sure we support html5 storage
    if (!supports_html5_storage()) {
        $('#supports_html5_storage').show();
    } else {
        getStoredState();
        /*database        = window.localStorage.getItem('database');
        table           = window.localStorage.getItem('table');
        processor       = window.localStorage.getItem('processor');
        if(database !== null){
            $('#inputDb').val(database);
        }
        if(table !== null){
            $('#inputTable').val(table);
        }
        if(table !== null){
            $('#processor').val(processor);
        }*/
        updateTableDb();
    }

    // When we change the table/database name, it displays it in the right place, unless it's empty, then we revert to the original
    $('#inputTable').keyup(updateTableDb);
    $('#inputDb').keyup(updateTableDb);
    $('input[type=text],input[type=number]').keyup(setStoredState);
    $('input, select').change(setStoredState);

    checkFiles(false);
    window.fileCheckIntervalID = setInterval(checkFiles, window.fileCheckUpdatePeriod);

    // Main file processing process handler
    $('.process-file').click(function() {

        var ctypes = {};
        $(this).parent().parent().next().find('select').each(function(i, a){
            ctypes[$(this).data('col')] = a.value;
        });
        ctypes = JSON.stringify(ctypes);

        $('#script-output').html("<h2>Processing...</h2>");
        var fn = $(this).data('fn');
        var jp = $(this).data('jp');
        var db = $('#inputDb').val();
        var table = $('#inputTable').val();
        var processor = $('#processor').val();
        var hh = $('#inputHh').is(":checked") ? 'true' : 'false';
        var replace = $('#inputReplace').is(":checked") ? 'true' : 'false';
        var chunks = $('#inputChunks').val();
        var delimiter = $('#delimiter').val();
        var escapechar = $('#escapechar').val();
        var quotechar = $('#quotechar').val();
        window.finished = false;

        var data = {
            fn: fn,
            replace: replace,
            progressFn: window.progressFn,
            chunks: chunks,
            jp: jp,
            db: db,
            table: table,
            hh: hh,
            processor: processor,
            delimiter: delimiter,
            escapechar: escapechar,
            quotechar: quotechar,
            columnTypes: ctypes
        };

        var url = 'CSVRunner/App.php?';
        url += 'fn='+fn+'&';
        url += 'replace='+replace+'&';
        url += 'progressFn='+window.progressFn+'&';
        url += 'chunks='+chunks+'&';
        url += 'jp='+jp+'&';
        url += 'db='+db+'&';
        url += 'table='+table+'&';
        url += 'hh='+hh+'&';
        url += 'processor='+processor+'&';
        url += 'delimiter='+delimiter+'&';
        url += 'escapechar='+escapechar+'&';
        url += 'quotechar='+quotechar+'&';
        url += 'columnTypes='+ctypes;
        console.log(url);

        $.getJSON('CSVRunner/App.php', data,
            function(data) {
                console.log("ALL DONE", data);
                clearInterval(window.progressInterval);
                window.finished = true;
                if (typeof data.error == 'undefined' || data.error === true) {
                    displayError(data);
                } else {
                    checkProgress();
                    $('.tertiary-status').remove();
                    if (!$('#script-progress').hasClass('hidden')) {
                        $('#script-progress').fadeOut(200, function() {
                            $('#script-progress').addClass('hidden');
                        });
                    }
                    if (!$('#script-progress-mini').hasClass('hidden')) {
                        $('#script-progress-mini').fadeOut(200, function() {
                            $('#script-progress-mini').addClass('hidden');
                        });
                    }
                    if ($('#done-message').hasClass('hidden')) {
                        $('#done-message').removeClass('hidden').fadeIn(200);
                        setTimeout(function() {
                            $('#done-message').fadeOut(200, function() {
                                $('#done-message').addClass('hidden');
                            });
                        }, 1300);
                    }
                    var d = new Date();
                    $output = $('<h4>Done! ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString() + '</h4>');
                    $output.append('<span id="typed-cursor" class="blinking">|</span>');
                    $('#script-output').html($output);
                }
            }
        ).error(function(data) {
            window.hasError = true;
            console.log("ERROR", data);
            displayError(data);
        });
        setTimeout(function() {
            window.progressInterval = setInterval(checkProgress, window.updatePeriod);
        }, 750);
    });
});

function checkFiles(updated) {
    updated = (updated === undefined) ? true : updated;
    var url = 'CSVRunner/GetFiles.php';
    $.getJSON(url, {}, function(data){
        console.log(data);
        var ids = new Array();
        $.each(data, function(key, val){
            var rowid = '#file-row-'+val.idbase;
            ids.push(rowid);
            if($(rowid).length > 0){
                updateRow(rowid, val, updated);
            } else {
                createRow(rowid, val, true);
            }
        });
        $('.main-row').each(function(i,el){
            if(ids.indexOf('#'+$(el).attr('id')) === -1){
                $(el).fadeOut(1000, function(){$(this).remove();});
                $(el).next().fadeOut(1000, function(){$(this).remove();});
            }
        });
    });
}

function createRow(rowid, val, updated) {
    var baseUrl = $('#base-url-php').data('base-url');
    var $mainRow = $('<tr>').addClass('main-row').attr('id',rowid.substr(1));
    $mainRow.append($('<td>').addClass('expand').append($('<span class="glyphicon glyphicon-chevron-down"></span>')).click(function(){
        $(this).parent().next('tr').slideToggle(200);
        $(this).parent().next('tr').find('.slider').slideToggle(200);
        $(this).children('span').toggleClass('glyphicon-chevron-down').toggleClass('glyphicon-chevron-up');
    }));
    $mainRow.append($('<td>'+val.basename+'</td>'));
    $mainRow.append($('<td class="file-size-updated">'+val.size+'</td>'));
    $mainRow.append($('<td class="file-numlines-updated">'+val.rowcount+'</td>'));
    $mainRow.append($('<td>').addClass('dump-file').append( $('<a>').attr('href','#script-progress').addClass('process-file').data('jp',false).data('local-fn','input/'+val.basename).data('fn',val.fn).append($('<span><i class="glyphicon glyphicon-import"></i> Dump to <span class="databaseName">databasename</span>.<span class="tableName">tablename</span></span>')) ) );
    $mainRow.append($('<td data-toggle="tooltip" data-placement="top" title="Warning: This will delete the file permanently, it will not be recoverable">').addClass('delete-file').append($('<a>').attr('href','http://'+baseUrl+'?delete=true&fn='+val['data-fn']).append($('<span>Delete File <i class="glyphicon glyphicon-trash"></i> <i class="glyphicon glyphicon-warning-sign"></i></span>')) ));
    var $tbody = $('<tbody>');
    $.each(val.cols, function(key,colname){
        var $trow = $('<tr>');
        $trow.append($('<td>'+colname+'</td>'));
        var $select = $('<select>');
        $.each(val.coltypes, function(key2, coltype){
            $select.append($('<option value="'+coltype+'">'+coltype+'</option>'));
        });
        $trow.append($select);
        $trow.append($('<td>This feature has not been implemented yet.</td>'));
        $tbody.append($trow);
    });
    var $secondaryRow = $('<tr>').addClass('secondary-row');
    $secondaryRow.append(
        $('<td colspan=6>').append(
            $('<div class="slider">').append(
                $('<table class="table table-hover table-striped">').append(
                    $('<thead><tr><th>Column</th><th>Type</th><th>Action</th></tr></thead>')
                ).append($tbody)
            )
        )
    );
    $('#main-file-table tbody').append($mainRow).append($secondaryRow);
    updateTableDb();
}

function updateRow(rowid, val, updated) {
    if($(rowid + ' td:nth-child(3)').html() !== val.size){
        $(rowid + ' td:nth-child(3)').html(val.size);
        if(updated){
            $(rowid + ' td:nth-child(3)').addClass('file-size-updated');
        }
    } else  if($(rowid + ' td:nth-child(3)').hasClass('file-size-updated')) {
        $(rowid + ' td:nth-child(3)').removeClass('file-size-updated');
    }
    if($(rowid + ' td:nth-child(4)').html()/1 !== val.rowcount){
        $(rowid + ' td:nth-child(4)').html(val.rowCount);
        if(updated){
            $(rowid + ' td:nth-child(4)').addClass('file-numlines-updated');
        }
    } else  if($(rowid + ' td:nth-child(4)').hasClass('file-numlines-updated')) {
        $(rowid + ' td:nth-child(4)').removeClass('file-numlines-updated');
    }
}

function checkFileSizes(updated){
    updated = (updated === undefined) ? true : updated;
    $('.process-file').each(function(i,el){
        var fn = decodeURIComponent($(el).data('local-fn'));
        $.ajax({
            url: fn,
            cache: false
        }).done(function(a){
            var numLines = getNumLines(a);
            if($(el).parent().prev().html()/1 !== numLines){
                $(el).parent().prev().html(numLines);
                if(updated){
                    $(el).parent().prev().addClass('file-numlines-updated');
                }
            } else if($(el).parent().prev().hasClass('file-numlines-updated')) {
                $(el).parent().prev().removeClass('file-numlines-updated');
            }
            var fileSize = formatBytes(a.length,1,true);
            if($(el).parent().prev().prev().html() !== fileSize){
                $(el).parent().prev().prev().html(fileSize);
                if(updated){
                    $(el).parent().prev().prev().addClass('file-size-updated');
                }
            } else if($(el).parent().prev().prev().hasClass('file-size-updated')) {
                $(el).parent().prev().prev().removeClass('file-size-updated');
            }
        });
    });
}

function getNumLines(string) {
    return (string.match(/\n/g) || []).length;
}

function formatBytes(bytes, precision, wu) {
    precision = (precision === undefined) ? 2    : precision;
    wu        = (wu        === undefined) ? true : wu;

    units = new Array('B', 'KB', 'MB', 'GB', 'TB');

    bytes = Math.max(bytes, 0);
    pow = Math.floor((bytes ? Math.log(bytes) : 0) / Math.log(1024));
    pow = Math.min(pow, units.length - 1);

    // Uncomment one of the following alternatives
    bytes /= Math.pow(1024, pow);
    // bytes /= (1 << (10 * pow));

    var ppow = Math.pow(10,precision);
    r = (Math.round(bytes * ppow)/ppow).toFixed(precision);
    if(wu) r += ' ' + units[pow];

    return r;
}

function setStoredState() {
    console.log('setStoredState');
    $('input[type=checkbox]').each(function(i, el){
        if($(el).attr('id') !== undefined){
            window.localStorage.setItem($(el).attr('id'),$(el).prop('checked'));
        }
    });
    $('input[type!=checkbox], select').each(function(i, el){
        if($(el).attr('id') !== undefined){
            window.localStorage.setItem($(el).attr('id'),$(el).val());
        }
    });
}

function getStoredState() {
    $('input[type=checkbox]').each(function(i, el){
        if($(el).attr('id') !== undefined){
            var isChecked = window.localStorage.getItem($(el).attr('id'));
            if(isChecked !== null){
                $(el).prop('checked',isChecked === 'true');
            }
        }
    });
    $('input[type!=checkbox], select').each(function(i, el){
        if($(el).attr('id') !== undefined){
            var val = window.localStorage.getItem($(el).attr('id'));
            if(val !== null){
                $(el).val(val);
            }
        }
    });
}

function updateTableDb(){
    $('.databaseName').each(function() {
        var t = ($('#inputDb').val()) ? $('#inputDb').val() : $(this).data('orig');
        $(this).text(t);
    });
    $('.tableName').each(function() {
        var t = ($('#inputTable').val()) ? $('#inputTable').val() : $(this).data('orig');
        $(this).text(t);
    });
    setStoredState();
}

function displayError(data) {
    clearInterval(window.progressInterval);
    console.log(data);
    var msg = 'No Message';
    if (typeof data.message !== 'undefined') msg = data.message;
    else if (typeof data.responseText !== 'undefined') msg = data.responseText;
    $output = $('<div class="alert alert-danger" role="alert"><strong>Oh no!</strong> Something went wrong, please try again.</div><p>Server message: <pre><code>' + msg + '</code></pre></p>');
    $output.append('<span id="typed-cursor" class="blinking">|</span>');
    $('#script-output').html($output);
    return true;
}

function createAndInsertStatusBars(num) {
    var statusBars = Array;
    var statuses = [
        'progress-bar-success',
        'progress-bar-info',
        'progress-bar-warning',
        'progress-bar-danger'
    ];
    for (i = 0; i < num; i++) {
        var newStatus = statuses[i % 4];
        var $bar = $('#progress-bar-start').clone();
        $bar.addClass('tertiary-status')
            .addClass(newStatus)
            .attr('id', 'tertiary-status-' + i)
            .attr('aria-valuenow', 0)
            .attr('aria-valuemin', 0)
            .attr('aria-valuemax', 100)
            .css('width', '0%');
        $('#script-progress').append($bar);
        var $bar2 = $('#progress-bar-start-mini').clone();
        $bar2.addClass('tertiary-status-mini')
            .addClass(newStatus)
            .attr('id', 'tertiary-status-mini-' + i)
            .attr('aria-valuenow', 0)
            .attr('aria-valuemin', 0)
            .attr('aria-valuemax', 100)
            .css('width', '0%');
        $('#script-progress-mini').append($bar2);
    }
    return statusBars;
}

function checkProgress(createStatusBars) {
    console.log('progress!');
    if (typeof createStatusBars === "undefined") createStatusBars = false;
    if (window.finished === true) return;
    url = window.progressFn;

    var d = new Date();
    var n = d.getTime();

    if ((n - window.lastUpdate) > window.pollingPeriod || window.lastData == null) {

        $.getJSON(url, function(data) {

            console.log(data);

            var d = new Date();
            window.lastUpdate = d.getTime();
            window.lastData = data;

            updateDisplay(data);
            return null;
        }).fail(function() {
            // clearInterval(window.progressInterval);
        });
    } else {
        var data = $.extend({}, window.lastData);
        data.stage.completeItems = Math.max(1, Math.min((data.stage.completeItems + Math.floor(((new Date().getTime() / 1000) - data.stage.curTime) * (data.stage.rate * (window.updatePeriod / 1000)))), data.stage.totalItems));
        data.stage.pcComplete = Math.max(0.01, Math.min(((data.stage.completeItems) / data.stage.totalItems), 1));
        data.stage.timeRemaining = (data.stage.totalItems - data.stage.completeItems) / data.stage.rate;
        updateDisplay(data);
    }
}

function updateDisplay(data) {
    if (typeof data.totalStages !== 'undefined' && $('.tertiary-status').length < 1) {
        console.log("Created Status Bars");
        createAndInsertStatusBars(data.totalStages);
    }
    var $output;
    if (typeof data.message == 'undefined' || data.error === true || data.stage.stageNum === -1) {
        return displayError(data);
    }
    $output = data.message;

    if ($('#script-progress').hasClass('hidden')) {
        $('#script-progress').hide().removeClass('hidden').fadeIn(200);
    }

    if ($('#script-progress-mini').hasClass('hidden')) {
        $('#script-progress-mini').hide().removeClass('hidden').fadeIn(200);
    }

    if (window.prevpc === data.stage.pcComplete & data.stage.rate !== null) {
        data.stage.completeItems = Math.max(1, Math.min((data.stage.completeItems + Math.floor(((new Date().getTime() / 1000) - data.stage.curTime) * data.stage.rate * (window.updaePeriod / 1000))), data.stage.totalItems));
        data.stage.pcComplete = Math.max(0.01, Math.min(((data.stage.completeItems) / data.stage.totalItems), 1));
        data.stage.timeRemaining = (data.stage.totalItems - data.stage.completeItems) / data.stage.rate;
    } else {
        window.prevpc = data.stage.pcComplete;
    }

    $output = $('<div>');
    $output.append($('<h4>' + Math.ceil((((data.stage.stageNum - 1) * 100) / (data.totalStages)) + (data.stage.pcComplete * 100 / (data.totalStages))) + '% complete</h4>'));
    if (data.stage.name !== null)
        $output.append($('<h4>Stage ' + data.stage.stageNum + ': ' + data.stage.name + '</h4>'));
    if (data.stage.message !== null)
        $output.append($('<p>Server message: <pre><code>' + data.stage.message + '</code></pre></p>'));
    if (data.stage.totalItems !== null)
        $output.append($('<p>' + data.stage.completeItems + ' of ' + data.stage.totalItems + ' processed.</p>'));
    if (data.stage.timeRemaining !== null)
        $output.append($('<p>Remaining time: ' + Math.ceil(data.stage.timeRemaining * 10) / 10 + ' seconds (est)</p>'));
    if (data.stage.rate !== null)
        $output.append($('<p>Currently processing at ' + Math.ceil(data.stage.rate * 10) / 10 + ' /second</p>'));

    for (i = (data.stage.stageNum - 1); i > 0; i--) {
        $('#tertiary-status-' + (i))
            .attr('aria-valuenow', (1 / (data.totalStages)) * 100)
            .css('width', (1 / (data.totalStages)) * 100 + "%");
    }
    for (i = (data.stage.stageNum - 1); i > 0; i--) {
        $('#tertiary-status-mini-' + (i))
            .attr('aria-valuenow', (1 / (data.totalStages)) * 100)
            .css('width', (1 / (data.totalStages)) * 100 + "%");
    }

    var percentOfTotal = (((1 / (data.totalStages)) * data.stage.pcComplete) * 100);
    $('#tertiary-status-' + (data.stage.stageNum - 1))
        .attr('aria-valuenow', percentOfTotal)
        .css('width', percentOfTotal + "%");
    $('#tertiary-status-' + (data.stage.stageNum - 1) + ' span').text(Math.ceil(percentOfTotal * 100) + "%");
    $('#tertiary-status-mini-' + (data.stage.stageNum - 1))
        .attr('aria-valuenow', percentOfTotal)
        .css('width', percentOfTotal + "%");
    $('#tertiary-status-mini-' + (data.stage.stageNum - 1) + ' span').text(Math.ceil(percentOfTotal * 100) + "%");

    $output.append('<span id="typed-cursor" class="blinking">|</span>');

    $('#script-output').html($output);
}

function supports_html5_storage() {
    try {
        return 'localStorage' in window && window['localStorage'] !== null;
    } catch (e) {
        return false;
    }
}