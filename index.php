<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Load CSV</title>

        <!-- Bootstrap CSS -->
        <link href="//netdna.bootstrapcdn.com/bootstrap/3.1.1/css/bootstrap.min.css" rel="stylesheet">

        <style>
        .warning { padding: 6px 6px 0 6px; margin: 6px 0; border: 1px solid #aaa; border-radius: 3px; color: red;}
        #supports_html5_storage {display: none;}
        .page-header { background: #333; margin: 0 auto 12px auto; padding-top: 12px; border-bottom: 4px solid #888;}
        .page-header h1 { margin: 0; color: white; padding: 0; text-shadow: 2px 1px 2px rgba(0,0,0,0);}
        #script-output {min-height: 120px; border-radius: 3px; border: 1px solid #888; padding: 6px; background: #002458; color: white; font-family: 'Consolas', 'Courier New', Courier, 'Lucida Sans Typewriter', 'Lucida Typewriter', monospace;;}
        h2 {maring: 8px auto;}
        .blinking{-webkit-animation:1s blink step-end infinite;-moz-animation:1s blink step-end infinite;-ms-animation:1s blink step-end infinite;-o-animation:1s blink step-end infinite;animation:1s blink step-end infinite}@keyframes blink{from,to{color:transparent}50%{color:#fff}}@-moz-keyframes blink{from,to{color:transparent}50%{color:#fff}}@-webkit-keyframes blink{from,to{color:transparent}50%{color:#fff}}@-ms-keyframes "blink"{from,to{color:transparent}50%{color:#fff}}@-o-keyframes blink{from,to{color:transparent}50%{color:#fff}}
        </style>

        <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
        <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
        <!--[if lt IE 9]>
          <script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
          <script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
        <![endif]-->
    </head>
    <body>
        <div class="page-header">
            <div class="container">
                <h1>CSVDump <small>Dump CSV files into a MySQL Database</small></h1>
            </div>
        </div>
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
<?php
// Just check that our Config file exists and display a message otherwise
if(!file_exists('./Core/Config.php')):
    if(file_exists('./Core/Config.example.php')){
        $msg = "<p>Edit <code>/Core/Config.example.php</code> with your database details and save as <code>/Core/Config.php</code>.</p>";
    } else {
        $msg = "<p>Also missing <code>/Core/Config.example.php</code>, please <a href='https://github.com/manticorp/CSVDump'>re-download the repository.</a></p>";
    }
?>
                    <div class="warning" id="config_file_not_exists">
                        <p>You are missing the /Core/Config.php file.</p>
                        <?php echo $msg; ?>
                    </div>
                </div>
            </div>
        </div>
<?php
// Else display the interface
else:
    include "./Core/Runner.php";

    $baseurl = explode('?', $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'])[0];

    $msg   = null;
    $error = false;
    if (isset($_GET['delete']) && isset($_GET['fn'])) {
        if (file_exists($_GET['fn'])) {
            unlink($_GET['fn']);
            $msg = "File $_GET[fn] deleted";
        } else {
            $msg   = "File $_GET[fn] not found";
            $error = true;
        }
    }

    $processors = CSVRunner::getProcessors();
?>
          <h2>Input</h2>
          <label for="inputDb">Database
            <input type="text" name="db"    id="inputDb"    class="form-control" value=""    title="" placeholder="e.g. test">
          </label>
          <label for="inputTable">Table
            <input type="text" name="table" id="inputTable" class="form-control" value="" title="" placeholder="e.g. testTable">
          </label>
          <label for="processor">Processor Class
            <select name="processor" id="processor" class="form-control" required="required">
              <option value="">No Processing</option>
              <?php foreach ($processors as $p) printf("<option value=\"%s\">%s</option>\n", $p, $p); ?>
            </select>
          </label>
          <button class="btn btn-primary btn-small" type="button" data-toggle="collapse" data-target="#advancedOptions" aria-expanded="false" aria-controls="advancedOptions">
            Advanced Options
          </button>
          <div class="collapse" id="advancedOptions">
            <div class="well">
              <label for="inputChunks">Chunks
                <input type="number" name="chunks" id="inputChunks" class="form-control" value="100" min="5" max="10000" step="1" title="Processing step size">
              </label>
              <label for="delimiter">Field Delimiter
                <select name="delimiter" id="delimiter" class="form-control" required="required">
                  <option value="">Auto Detect</option>
                  <?php foreach (CSVRunner::$delimiters as $p) printf("<option value=\"%s\">%s</option>\n", $p, str_replace("\t","\\t",$p)); ?>
                </select>
              </label>
              <label for="quotechar">Quote Char
                <select name="quotechar" id="quotechar" class="form-control" required="required">
                  <option value="">Auto Detect</option>
                  <?php foreach (CSVRunner::$quoteChars as $p) printf("<option value=\"%s\">%s</option>\n", $p, str_replace("\t","\\t",$p)); ?>
                </select>
              </label>
              <label for="escapechar">Escape Char
                <select name="escapechar" id="escapechar" class="form-control" required="required">
                  <option value="">Auto Detect</option>
                  <?php foreach (CSVRunner::$escapeChars as $p) printf("<option value=\"%s\">%s</option>\n", $p, str_replace("\t","\\t",$p)); ?>
                </select>
              </label>
              <div class="checkbox">
                <label for="inputReplace">
                  <input type="checkbox" checked value="" name="hh" id="inputReplace" title="Whether to replace existing data">
                  Replace Data
                </label>
              </div>
              <div class="checkbox">
                <label>
                  <input type="checkbox" checked value="" name="hh" id="inputHh" title="Whether your csv has headers">
                  Has Headers
                </label>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php if ($msg !== null): ?>
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
          <div class="alert alert-<?php echo ($error) ? 'danger' : 'success';?>">
            <button type="button" class="close" data-dismiss="alert" aria-hidden="true">&times;</button>
            <strong><?php echo ($error) ? '<i class="glyphicon glyphicon-warning-sign"></i> Warning' : 'Success';?></strong> <?php echo $msg;?>
          </div>
        </div>
      </div>
      <?php endif; //($msg !== null) ?>
      <div class="row">
        <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
          <div class="table-responsive">
            <table class="table table-hover table-striped">
              <thead>
                <tr>
                  <th>Filename</th>
                  <th>Size</th>
                  <th>Rows (est.)</th>
                  <th>Dump</th>
                  <th>Delete</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach (glob(APPLICATION_PATH . '/input/*.csv') as $fn): ?>
                <tr>
                  <td><?php echo basename($fn);?></td>
                  <td><?php echo CSVRunner::fileSizeString($fn, 1);?></td>
                  <td><?php echo CSVRunner::numRowsInFile($fn);?></td>
                  <td><a href='#' class='process-file' data-jp='false' data-fn='<?php echo urlencode(realpath($fn));?>'>Dump to <span class="databaseName"><?php echo $db['db'] . '</span>.<span class="tableName">' . CSVRunner::getDBName($fn);?></span></a></td>
                  <td><a href='http://<?=$baseurl;?>?delete=true&fn=<?php echo urlencode(realpath($fn));?>'>Delete File</a></td>
                </tr>
                <?php endforeach;?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div class="row">
          <div class="col-xs-12 col-sm-12 col-md-12 col-lg-12">
              <h2>Script Output Display</h2>
                  <div class="progress hidden" id="script-progress">
                    <div class="progress-bar progress-bar-striped active" id="progress-bar-start" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" style="width: 0">
                      <span class="sr-only">0% Complete</span>
                    </div>
                  </div>
              <div id="script-output">Nothing to output<span id="typed-cursor" class="blinking">|</span></div>
          </div>
      </div>
    </div>

    <!-- jQuery -->
    <script src="//code.jquery.com/jquery.js"></script>

    <script type="text/javascript">

window.progressInterval;
window.prevpc;
window.hasError = false;
window.finished = false;
window.pollingPeriod = 1000;
window.updatePeriod  = 250;
window.lastData = null;
window.lastUpdate;
window.progressFn = 'var/progress' + Math.random() + '.json';

$(function(){
    if(!supports_html5_storage()){
      $('#supports_html5_storage').show();
    }
    $('.tableName').each(function(){
      $(this).data('orig', $(this).text());
    });
    $('#inputTable').keyup(function(e){
      $('.tableName').each(function(){
        var t = ($('#inputTable').val()) ? $('#inputTable').val() : $(this).data('orig');
        $(this).text(t);
      });
    });
    $('.databaseName').each(function(){
      $(this).data('orig', $(this).text());
    });
    $('#inputDb').keyup(function(e){
      $('.databaseName').each(function(){
        var t = ($('#inputDb').val()) ? $('#inputDb').val() : $(this).data('orig');
        $(this).text(t);
      });
    });
    $('.process-file').click(function(){
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
        $.getJSON('Core/App.php',
            {
              fn:fn,
              replace:replace,
              progressFn:window.progressFn,
              chunks: chunks,
              jp:jp,
              db:db,
              table:table,
              hh:hh,
              processor:processor,
              delimiter:delimiter,
              escapechar:escapechar,
              quotechar:quotechar
            },
            function(data){
                console.log("ALL DONE", data);
                clearInterval(window.progressInterval);
                window.finished = true;
                if(typeof data.error == 'undefined' || data.error === true){
                    displayError(data);
                } else {
                    checkProgress();
                    $('.tertiary-status').remove();
                    if(!$('#script-progress').hasClass('hidden')){
                        $('#script-progress').fadeOut(200,function(){$('#script-progress').addClass('hidden');});
                    }
                    var d = new Date();
                    $output = $('<h4>Done! ' + d.toLocaleDateString() + ' ' + d.toLocaleTimeString() + '</h4>');
                    $output.append('<span id="typed-cursor" class="blinking">|</span>');
                    $('#script-output').html($output);
                }
            }
        ).error(function(data){
            window.hasError = true;
            console.log("ERROR", data);
            displayError(data);
        });
        setTimeout(function(){
          window.progressInterval = setInterval(checkProgress, window.updatePeriod);
        }, 750);
    });
});

function displayError(data){
    clearInterval(window.progressInterval);
    console.log(data);
    var msg = 'No Message';
    if(typeof data.message !== 'undefined') msg = data.message;
    else if(typeof data.responseText !== 'undefined') msg = data.responseText;
    $output = $('<div class="alert alert-danger" role="alert"><strong>Oh no!</strong> Something went wrong, please try again.</div><p>Server message: <pre><code>'+msg+'</code></pre></p>');
    $output.append('<span id="typed-cursor" class="blinking">|</span>');
    $('#script-output').html($output);
    return true;
}

function createAndInsertStatusBars(num){
    var statusBars = Array;
    var statuses = [
        'progress-bar-success',
        'progress-bar-info',
        'progress-bar-warning',
        'progress-bar-danger'
    ];
    for(i=0; i<num; i++){
        var newStatus = statuses[i%4];
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

function checkProgress(createStatusBars){
    console.log('progress!');
    if(typeof createStatusBars === "undefined") createStatusBars = false;
    if(window.finished === true) return;
    url = window.progressFn;

    var d = new Date();
    var n = d.getTime();

    if((n - window.lastUpdate) > window.pollingPeriod || window.lastData == null){

        $.getJSON(url, function(data){

            console.log(data);

            var d = new Date();
            window.lastUpdate = d.getTime();
            window.lastData = data;

            updateDisplay(data);
            return null;
        }).fail(function(){
            clearInterval(window.progressInterval);
        });
    } else {
        var data = $.extend({},window.lastData);
        data.stage.completeItems = Math.max(1,Math.min((data.stage.completeItems + Math.floor(((new Date().getTime()/1000)-data.stage.curTime)*(data.stage.rate*(window.updatePeriod/1000)))), data.stage.totalItems));
        data.stage.pcComplete = Math.max(0.01,Math.min(((data.stage.completeItems)/data.stage.totalItems),1));
        data.stage.timeRemaining = (data.stage.totalItems - data.stage.completeItems)/data.stage.rate;
        updateDisplay(data);
    }
}

function updateDisplay(data){
    if(typeof data.totalStages !== 'undefined' && $('.tertiary-status').length < 1 ){
        console.log("Created Status Bars");
        createAndInsertStatusBars(data.totalStages);
    }
    var $output;
    if(typeof data.message == 'undefined' || data.error === true || data.stage.stageNum === -1){
        return displayError(data);
    }
    $output = data.message;

    if($('#script-progress').hasClass('hidden')){
        $('#script-progress').hide().removeClass('hidden').fadeIn(200);
    }

    if(window.prevpc === data.stage.pcComplete & data.stage.rate !== null){
        data.stage.completeItems = Math.max(1,Math.min((data.stage.completeItems + Math.floor(((new Date().getTime()/1000)-data.stage.curTime)*data.stage.rate*(window.updaePeriod/1000))), data.stage.totalItems));
        data.stage.pcComplete = Math.max(0.01,Math.min(((data.stage.completeItems)/data.stage.totalItems),1));
        data.stage.timeRemaining = (data.stage.totalItems - data.stage.completeItems)/data.stage.rate;
    } else {
        window.prevpc = data.stage.pcComplete;
    }

    $output = $('<div>');
    $output.append($('<h4>'+Math.ceil( ( ((data.stage.stageNum-1)*100)/(data.totalStages) ) + (data.stage.pcComplete*100/(data.totalStages)) )+'% complete</h4>'));
    if(data.stage.name!==null)
        $output.append($('<h4>Stage ' + data.stage.stageNum + ': '+data.stage.name+'</h4>'));
    if(data.stage.message!==null)
        $output.append($('<p>Server message: <pre><code>'+data.stage.message+'</code></pre></p>'));
    if(data.stage.totalItems!==null)
        $output.append($('<p>' + data.stage.completeItems+ ' of ' + data.stage.totalItems + ' processed.</p>'));
    if(data.stage.timeRemaining!==null)
        $output.append($('<p>Remaining time: ' + Math.ceil(data.stage.timeRemaining*10)/10 + ' seconds (est)</p>'));
    if(data.stage.rate!==null)
        $output.append($('<p>Currently processing at ' + Math.ceil(data.stage.rate*10)/10 + ' /second</p>'));

    for(i = (data.stage.stageNum-1); i > 0; i--){
        $('#tertiary-status-'+(i))
            .attr('aria-valuenow', (1/(data.totalStages))*100)
            .css('width', (1/(data.totalStages))*100+"%");
    }

    var percentOfTotal = (((1/(data.totalStages))*data.stage.pcComplete)*100);
    $('#tertiary-status-'+(data.stage.stageNum-1))
        .attr('aria-valuenow', percentOfTotal)
        .css('width', percentOfTotal+"%");
    $('#tertiary-status-' + (data.stage.stageNum-1) +' span').text(Math.ceil(percentOfTotal*100)+"%");

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
    </script>
<?php endif; ?>

    <!-- Bootstrap JavaScript -->
    <script src="//netdna.bootstrapcdn.com/bootstrap/3.1.1/js/bootstrap.min.js"></script>
  </body>
</html>
