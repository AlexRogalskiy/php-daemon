<?php

require_once __DIR__ . '/../vendor/autoload.php';
// configs
require_once __DIR__ . '/../configs/daemon.config.php';
// utilities
require_once __DIR__ . '/utils/utils.php';
// logging
require_once __DIR__ . '/utils/logger.php';
// entry point
require_once __DIR__ . '/daemon-service.php';

use Logger\DaemonLogger;

//Используем микрофреймворк Silex
/*$app = new Silex\Application();
$app['debug'] = true;
//И шаблонизатор Twig, который легко интегрируется в Silex
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../views'
));
//При заходе в корень нашего сайта, сработает контроллер описанный в анонимной функции ниже
$app->get('/', function() use ($app) {
    //TimerClass определён в библиотеке super_lib.
    //Благодаря сгенерированному autoloader.php нужный файл библиотеки подключится автоматически
    $timer = new TimerClass();
    //Функция get_ip() определена в библиотеке super_lib.
    //Благодаря сгенерированному autoloader.php нужный файл библиотеки подключится автоматически
    $ip = get_ip();
    $templateVars = array(
        'msg' => 'Super Hello World',
        'time' => $timer->getCurrentTime(),
        'ip' => $ip
    );
    //Рендрим шаблон и выводим его в браузер пользователя
    return $app['twig']->render('layout.twig', $templateVars);
});
$app->finish(function() {
    //Класс MyCompanyNamespace\SuperLogger определён в Composer-пакете mycompany/superlogger
    //Благодаря сгенерированному autoloader.php нужный файл с описанием класса подключится автоматически
    $logger = new MyCompanyNamespace\SuperLogger();
    $logger->writeLog('log.txt', 'Someone visited the page');
});
$app->run();
*/

// create new child process
/*$child_pid = pcntl_fork();
if ($child_pid) {
// exit the parent process
    exit();
}
// make the child process the main one
posix_setsid();*/

// read user input
$handle = fopen("php://stdin", "rb");
$file_name = fgets($handle);
if(empty($file_name) || !file_exists($file_name)) {
	$file_name = Configs\DEFAULT_FILE_NAME;
}
$key = Utils\Utils::getFileId($file_name, Configs\DEFAULT_PROJECT_ID);

$daemon = new DaemonService($key);
$daemon->run();


















