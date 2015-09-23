<?php
set_time_limit(0);

require_once __DIR__.'/autoload.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Tests\Vivait\DelayedEventBundle\app\AppKernel;

$kernel = new AppKernel('test', true);
$application = new Application($kernel);

$application->run();
