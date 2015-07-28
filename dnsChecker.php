<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
    <script src="http://code.jquery.com/jquery-latest.js"></script>
    <script src="includes/bootstrap/js/bootstrap.min.js"></script>
    <link href="includes/bootstrap/css/bootstrap.min.css" rel="stylesheet">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        span.update-ip {
            float: right;
            color: grey;
            cursor: pointer;
        }
        span.update-ip:hover {
            color: forestgreen;
        }
        #adminko {
            position: fixed;
            bottom: 5px;
            z-index: 5;
            border: 1px solid black;
            padding: 10px 17px;
            border-radius: 13px;
            background: #ffffff;
        }
        .open-arrow > span {
            font-size: 40px;
            border-left: 2px solid grey;
            border-radius: 70px;
            position: absolute;
            top: 162px;
            left: -50px;
            z-index: 3;
            padding: 33px 10px;
            color: grey;
            cursor: pointer;
        }
        .open-arrow > span:hover {
            color: forestgreen;
            border-left: 2px solid forestgreen;
            left: -52px;
        }
    </style>
</head>
<body>
<div class="container" style="padding: 0; width:1300px;">
<?php

define('PATH_TO_FILE', __DIR__ . DIRECTORY_SEPARATOR . 'dns');

$dnsRecords = json_decode(file_get_contents(PATH_TO_FILE), true);
$dnsRecords = (is_null($dnsRecords) || !$dnsRecords) ? [] : $dnsRecords;

if (isset($_POST['ip'])) {
    switch ($_POST['action']) {
        case 'add':
            $dnsRecords[$_POST['ip']] = $_POST['description'];
            file_put_contents(PATH_TO_FILE, json_encode($dnsRecords));
            die ('add IP ' . $_POST['ip']);
            break;
        case 'del':
            unset($dnsRecords[$_POST['ip']]);
            file_put_contents(PATH_TO_FILE, json_encode($dnsRecords));
            die ('IP ' . $_POST['ip'] . ' удален.');
            break;
        case 'update-ip':
            require_once 'phpQuery.php';
            die (checkSpamByIp($_POST['ip']));
            break;
        default:
            die ('Unknown option');
    }
}

if (!empty($dnsRecords)) {
    require_once 'phpQuery.php';
    foreach ($dnsRecords as $ip => $desc) {
        echo checkSpamByIp($ip);
    }

}

function checkSpamByIp($ip) {
    $postData = [];
    $postData[] = 'IP='.$ip;

    $postData = implode ('&', $postData);

    $curl_connection = curl_init('http://www.dnsbl.info/dnsbl-database-check.php');
    curl_setopt($curl_connection, CURLOPT_POST, 1);
    curl_setopt($curl_connection, CURLOPT_USERAGENT, 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_10_1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/37.0.2062.124 Safari/537.36');
    curl_setopt($curl_connection, CURLOPT_POSTFIELDS, $postData);
    curl_setopt($curl_connection, CURLOPT_COOKIEJAR, 'dns.cookie');
    curl_setopt($curl_connection, CURLOPT_COOKIEFILE, 'dns.cookie');
    curl_setopt($curl_connection, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_connection, CURLOPT_HEADER, 'X-Requested-With: XMLHttpRequest');
    curl_setopt($curl_connection, CURLOPT_HEADER, 'Referer: http://www.dnsbl.info/dnsbl-database-check.php');
    $answer = curl_exec($curl_connection);
    curl_close($curl_connection);

    $answer = phpQuery::newDocument($answer);
    foreach ($answer->find('table.body_sub_body a') as $a) {
        pq($a)->attr('href', 'http://www.dnsbl.info' . pq($a)->attr('href'));
    }
    $some = '<span class="glyphicon glyphicon-repeat update-ip" aria-hidden="true"></span>'; pq($some)->appendTo($answer->find('h1.body_sub_header'));
    $answer = $answer->find('h1.body_sub_header')->htmlOuter() . $answer->find('table.body_sub_body')->htmlOuter();
    return '
                <div id="'. $ip . '" class="col-xs-12  col-lg-6" style="padding: 0">
                    ' . $answer  . '
                </div>
            ';
}
?>

    <div id="adminko">
        <div class="open-arrow"><span class="glyphicon glyphicon-chevron-left" aria-hidden="true"></span></div>
        <span class="body_sub_body">
            Legend:<br>
            <img src="includes/img/ball_green_15x15.gif" height="15" width="15" alt=""> = Not Listed<br>
            <img src="includes/img/ball_red_15x15.gif" height="15" width="15" alt=""> = Listed<br>
            <img src="includes/img/ball_blue_15x15.gif" height="15" width="15" alt=""> = Timeout Error<br>
            <img src="includes/img/ball_grey_15x15.gif" height="15" width="15" alt=""> = Offline<br>
        </span>
        <br>
        <table class="table table-condensed table-hover table-striped table-bordered" style="max-width: 250px;">
            <thead>
                <tr>
                    <th>IPs</th>
                    <th>Description</th>
                    <th> ... </th>
                </tr>
            </thead>
            <tbody>
            <?
                foreach ($dnsRecords as $ip => $desc) {
                    echo "<tr><td>$ip</td><td>$desc</td><td><a href='#'>del</a></td></tr>";
                }
            ?>
            </tbody>
        </table>
        <p class="msg"></p>
        <form action="" method="POST">
            <label for="ip">IP</label><br>
            <input type="text" name="ip" id="ip" required="required"><br>
            <label for="description">Описание</label><br>
            <textarea name="description" id="description"></textarea><br>
            <input type="hidden" name="action" value="add">
            <button name="action" value="add" class="btn btn-success">Add</button>
        </form>
    </div>
    <script>

        $('#adminko').find('form').submit(function(event) {
            event.preventDefault();
            $.ajax({
                url:     'dnsChecker.php',
                type:     'POST',
                dataType: 'html',
                data: $('#adminko form').serialize(),
                success: function(response) {
                    $('p.msg').text(response.substring(response.lastIndexOf('>') + 1).trim());
                    $('#adminko tbody tr:last').after('<tr><td>' + $('#ip').val() + '</td><td>'+ $('#description').val() +'</td><td><a href="#">del</a></td></tr>');
                    setDelButtons();
                },
            });
        });
        function setDelButtons() {
            $('#adminko').find('a').click(function (event) {
                event.preventDefault();
                var tr = $(this).closest('tr');
                var ip = tr.find('td:first').text();

                if (!confirm('Точно удалить IP = ' + ip + '?')) return false;
                $.ajax({
                    url: 'dnsChecker.php',
                    type: 'POST',
                    dataType: 'html',
                    data: {'ip': ip, 'action': 'del'},
                    success: function (response) {
                        $('p.msg').text(response.substring(response.lastIndexOf('>') + 1).trim());
                        tr.css('display', 'none');
                        fixArrowHeight();
                    },
                });
            });
        }
        function setUpdButtons() {
            $('span.update-ip').click(function () {
                var it = $(this).closest('div');
                var next = it.next();
                var ip = it.attr('id');
                it.remove();
                $.ajax({
                    url: 'dnsChecker.php',
                    type: 'POST',
                    dataType: 'html',
                    data: {'ip': ip, 'action': 'update-ip'},
                    success: function (response) {
                        next.before(response.substring(response.lastIndexOf('container-fluid') + 17).trim());
                        setUpdButtons();
                    },
                });
            });
        }
        function fixArrowHeight() {
            $('.open-arrow span').css('top', (adminPanel.outerHeight() / 2) - ($('.open-arrow span').outerHeight() / 2));
        }
        var adminPanel = $('div#adminko');
        console.log(adminPanel.outerHeight());
        console.log($('.open-arrow span').outerHeight());
        adminPanel.css('right', -1 * adminPanel.outerWidth(true));
        $('.open-arrow').click(function() {
            if (parseFloat(adminPanel.css('right')) < 0) {
                adminPanel.animate({
                   right: '+=' + (adminPanel.outerWidth(true) + 5),
                });
                $('.open-arrow').find('span').removeClass('glyphicon-chevron-left').addClass('glyphicon-chevron-right');
            } else {
                adminPanel.animate({
                    right: '-=' + (adminPanel.outerWidth(true) + 5),
                });
                $('.open-arrow').find('span').removeClass('glyphicon-chevron-right').addClass('glyphicon-chevron-left');
            }
        });
        setDelButtons();
        setUpdButtons();
        fixArrowHeight();
    </script>
</div>
</body>
</html>