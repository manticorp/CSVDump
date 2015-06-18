window.progressInterval;
window.prevpc;
window.hasError = false;
window.finished = false;
window.pollingPeriod = 1000;
window.updatePeriod = 250;
window.lastData = null;
window.lastUpdate;
window.progressFn = 'var/progress' + Math.random() + '.json';

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
        database        = window.localStorage.getItem('database');
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
        }
        updateTableDb();
    }

    // When we change the table/database name, it displays it in the right place, unless it's empty, then we revert to the original
    $('#inputTable').keyup(updateTableDb);
    $('#inputDb').keyup(updateTableDb);

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

function updateTableDb(){
    $('.databaseName').each(function() {
        var t = ($('#inputDb').val()) ? $('#inputDb').val() : $(this).data('orig');
        $(this).text(t);
    });
    $('.tableName').each(function() {
        var t = ($('#inputTable').val()) ? $('#inputTable').val() : $(this).data('orig');
        $(this).text(t);
    });
    window.localStorage.setItem('database',  $('#inputDb').val());
    window.localStorage.setItem('table',     $('#inputTable').val());
    window.localStorage.setItem('processor', $('#processor').val());
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
            clearInterval(window.progressInterval);
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

    var percentOfTotal = (((1 / (data.totalStages)) * data.stage.pcComplete) * 100);
    $('#tertiary-status-' + (data.stage.stageNum - 1))
        .attr('aria-valuenow', percentOfTotal)
        .css('width', percentOfTotal + "%");
    $('#tertiary-status-' + (data.stage.stageNum - 1) + ' span').text(Math.ceil(percentOfTotal * 100) + "%");

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