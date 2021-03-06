<?php
error_reporting(0);
// To change these values, create a file called config.php and copy/paste them there.
$server_name = "Server Name";
$server_desc = "Description";
$server_hostname = $windows ? $_SERVER['SERVER_NAME'] : `hostname -f`;
$server_ip = $_SERVER['SERVER_ADDR'];

// Please put the link to your image here using a FQDN.
$server_bkg = "http://myserver.com/img/image.png";

if(is_file("config.php")) {
    include "config.php";
}

// Detect Windows systems
$windows = defined('PHP_WINDOWS_VERSION_MAJOR');

// Get system status
if($windows) {

    // Uptime parsing was a mess...
    $uptime = 'Error';

    // Assuming C: as the system drive
    $disk_stats = explode(' ',trim(preg_replace('/\s+/',' ',preg_replace('/[^0-9 ]+/','',`fsutil volume diskfree c:`))));
    $disk = round($disk_stats[0] / $disk_stats[1] * 100);

    $disk_total = '';
    $disk_used = '';

    // Memory checking is slow on Windows, will only set over AJAX to allow page to load faster
    $memory = 0;
    $mem_total = 0;
    $mem_used = 0;

    $swap = 0;
    $swap_total = 0;
    $swap_used = 0;

} else {

    $initial_uptime = shell_exec("cut -d. -f1 /proc/uptime");
    $days = floor($initial_uptime / 60 / 60 / 24);
    $hours = $initial_uptime / 60 / 60 % 24;
    $mins = $initial_uptime / 60 % 60;
    $secs = $initial_uptime % 60;

    if($days > "0") {
        $uptime = $days . "d " . $hours . "h";
    } elseif ($days == "0" && $hours > "0") {
        $uptime = $hours . "h " . $mins . "m";
    } elseif ($hours == "0" && $mins > "0") {
        $uptime = $mins . "m " . $secs . "s";
    } elseif ($mins < "0") {
        $uptime = $secs . "s";
    } else {
        $uptime = "Error retreving uptime.";
    }

    // Check disk stats
    $disk_result = `df -P | grep /dev/$`;
    if(!trim($disk_result)) {
        $disk_result = `df -P | grep /$`;
    }
    $disk_result = explode(" ", preg_replace("/\s+/", " ", $disk_result));

    $disk_total = intval($disk_result[1]);
    $disk_used = intval($disk_result[2]);
    $disk = intval(rtrim($disk_result[4], "%"));

    // Check current RAM usage
    $mem_result = trim(`free -mo | grep Mem`);
    $mem_result = explode(" ", preg_replace("/\s+/", " ", $mem_result));
    $mem_total = intval($mem_result[1]);
    $mem_used = $mem_result[2] - $mem_result[5] - $mem_result[6];
    $memory = round($mem_used / $mem_total * 100);

    // Check current swap usage
    $swap_result = trim(`free -mo | grep Swap`);
    $swap_result = explode(" ", preg_replace("/\s+/", " ", $swap_result));
    $swap_total = $swap_result[1];
    $swap_used = $swap_result[2];
    $swap = round($swap_used / $swap_total * 100);
}

if(!empty($_GET['json'])) {

    // Determine number of CPUs
    $num_cpus = 1;
    if (is_file('/proc/cpuinfo')) {
        $cpuinfo = file_get_contents('/proc/cpuinfo');
        preg_match_all('/^processor/m', $cpuinfo, $matches);
        $num_cpus = count($matches[0]);
    } else if ('WIN' == strtoupper(substr(PHP_OS, 0, 3))) {
        $process = @popen('wmic cpu get NumberOfCores', 'rb');
        if (false !== $process) {
            fgets($process);
            $num_cpus = intval(fgets($process));
            pclose($process);
        }
    } else {
        $process = @popen('sysctl -a', 'rb');
        if (false !== $process) {
            $output = stream_get_contents($process);
            preg_match('/hw.ncpu: (\d+)/', $output, $matches);
            if ($matches) {
                $num_cpus = intval($matches[1][0]);
            }
            pclose($process);
        }
    }

    if($windows) {

        // Get stats for Windows
        $cpu = intval(trim(preg_replace('/[^0-9]+/','',`wmic cpu get loadpercentage`)));
        $memory_stats = explode(' ',trim(preg_replace('/\s+/',' ',preg_replace('/[^0-9 ]+/','',`systeminfo | findstr Memory`))));
        $memory = round($memory_stats[4] / $memory_stats[0] * 100);

    } else {

        // Get stats for linux using simplest possible methods
        if(function_exists("sys_getloadavg")) {
            $load = sys_getloadavg();
            $cpu = $load[0] * 100 / $num_cpus;
        } elseif(`which uptime`) {
            $str = substr(strrchr(`uptime`,":"),1);
            $avs = array_map("trim",explode(",",$str));
            $cpu = $avs[0] * 100 / $num_cpus;
        } elseif(`which mpstat`) {
            $cpu = 100 - round(`mpstat 1 2 | tail -n 1 | sed 's/.*\([0-9\.+]\{5\}\)$/\\1/'`);
        } elseif(is_file('/proc/loadavg')) {
            $cpu = 0;
            $output = `cat /proc/loadavg`;
            $cpu = substr($output,0,strpos($output," "));
        } else {
            $cpu = 0;
        }

    }

    header("Content-type: application/json");
    
    // Pass data to the site to use for the knobs and general info.
    exit(json_encode(array(
        'uptime' => $uptime,
        'disk' => $disk,
        'disk_total' => $disk_total,
        'disk_used' => $disk_used,
        'cpu' => $cpu,
        'num_cpus' => $num_cpus,
        'memory' => $memory,
        'memory_total' => $mem_total,
        'memory_used' => $mem_used,
        'swap' => $swap,
        'swap_total' => $swap_total,
        'swap_used' => $swap_used,
    )));
}

?>

<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title><?php echo $server_name; ?></title>
        <script src="//ajax.googleapis.com/ajax/libs/jquery/1.11.1/jquery.min.js"></script>
        <script src="js/jqknob.js"></script>
        <link rel="stylesheet" href="css/main.css" />
        <link rel="stylesheet" href="css/style.css" />
        <style>
            #wrapper {
                background-image: url(../img/bkg.jpg);
            }
        </style>
        <script>
            function update() {
                $.post('<?php echo basename(__FILE__); ?>?json=1', function(data) {

                    // Update footer
                    $('#uptime').text(data.uptime);
                    $('#k-cpu').val(data.cpu).trigger("change");
                    $('#k-memory').val(data.memory).trigger("change");
                    if(data.swap_total) {
                        $('#k-swap').val(data.swap).trigger("change");
                    }

                    // Update details
                    $('#dt-disk-used').text(Math.round(data.disk_used / 10485.76) / 100);
                    $('#dt-mem-used').text(data.memory_used);
                    $('#dt-num-cpus').text(data.num_cpus);
                    if(data.swap_total) {
                        $('#dt-swap_used').text(data.swap_used);
                    }
                    window.setTimeout(update, 3000);

                },'json');
            }
            $(document).ready(function() {
                // Show ring charts
                $("#k-disk, #k-memory, #k-swap, #k-cpu").knob({
                    readOnly: true,
                    width: 40,
                    height: 40,
                    thickness: 0.2,
                    fontWeight: 'normal',
                    bgColor: 'rgba(127,127,127,0.15)', // 50% grey with a low opacity, should work with most backgrounds
                    fgColor: '#ccc'
                });
                // Start AJAX update loop
                update();
            });
        </script>
    </head>
    <body>
        <div class="menu push-menu-bottom">
            <div class="left">
                <h2><?php echo $server_hostname; ?></h2>
                <?php echo $server_ip; ?>

            </div>
            <div class="right">
                <b>Disk:</b> <span id="dt-disk-used"><?php echo round($disk_used / 1048576, 2); ?></span> GB / <?php echo round($disk_total / 1048576, 2); ?> GB<br>
                <b>Memory:</b> <span id="dt-mem-used"><?php echo round($mem_used); ?></span> MB / <?php echo (512 * round(round($mem_total) / 512)); ?> MB<br>
                <?php if($swap_total !== "0") { ?>
                    <b>Swap:</b> <span id="dt-swap-used"><?php echo $swap_used ?></span> MB / <?php echo $swap_total ?> MB<br>
                <?php } else { ?>
                    <b>Swap:</b> N/A<br>
                <?php }?>
                <b>CPU Cores:</b> <span id="dt-num-cpus"></span>
            </div>
        </div><!-- /push menu bottom -->
        <div id="wrapper">
            <h1><?php echo $server_name; ?></h1>
            <p><?php echo $server_desc; ?></p>
            <footer>
                <div class="left">
                    <?php if(!$windows && !empty($uptime)) { ?>
                        Uptime: <span id="uptime"><?php echo $uptime; ?></span>&emsp;
                    <?php } ?>
                    Disk usage: <input id="k-disk" value="<?php echo $disk; ?>">&emsp;
                    Memory: <input id="k-memory" value="<?php echo $memory; ?>">&emsp;
                    <?php if($swap_total !== "0") { ?>
                        Swap: <input id="k-swap" value="<?php echo $swap; ?>">&emsp;
                    <?php } ?>
                    CPU: <input id="k-cpu" value="0">&emsp;
                </div>
                <div class="right">
                    <button class="nav-toggler toggle-push-bottom">Additional Information</button>
                </div>
            </footer>
        </div>
        <script src="js/application.js"></script>
    </body>
</html>
