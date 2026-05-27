<?php
require_once __DIR__ . '/vendor/autoload.php';
// Load env
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/config');
$dotenv->load();

require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/config/database.php';


// CORS
header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }

$pdo = (new Connection())->connect();
$auth = new Auth($pdo);
$user = new User($pdo);
$service = new Service($pdo);
$slot = new Slot($pdo);
$appointment = new Appointment($pdo);
$admin = new Admin($pdo);
$report = new Report($pdo);

// Router
$method = $_SERVER['REQUEST_METHOD'];
$param  = explode('/', trim($_GET['params'] ?? '', '/'));

$resource = $param[0] ?? '';
$sub = $param[1] ?? '';
$id  = $param[2] ?? null;

switch ($resource) {

    case 'auth':
        if ($method !== 'POST') respond_error('Method not allowed', 405);
        if ($sub === 'register') $auth->register();
        elseif ($sub === 'login') $auth->login();
        else respond_error('Endpoint not found', 404);
        break;

    case 'users':
        if ($sub !== 'profile') respond_error('Endpoint not found', 404);
        if ($method === 'GET') $user->getProfile();
        elseif ($method === 'PUT') $user->updateProfile();
        else respond_error('Method not allowed', 405);
        break;

    case 'services':
        if ($method === 'GET') $service->getAll();
        elseif ($method === 'POST') $service->create();
        else respond_error('Method not allowed', 405);
        break;

    case 'slots':
        if ($method === 'GET') $slot->getAll();
        elseif ($method === 'POST') $slot->create();
        else respond_error('Method not allowed', 405);
        break;

    case 'appointments':
        if ($method === 'POST') $appointment->create();
        elseif ($method === 'GET' && $sub === 'user' && $id)
            $appointment->getByUser($id);
         elseif ($method === 'PUT') $appointment->updateStatus();
        else respond_error('Endpoint not found', 404);
        break;

    case 'admin':
        if ($method !== 'GET') respond_error('Method not allowed', 405);
        if ($sub === 'appointments') $admin->getAppointments();
        elseif ($sub === 'slots') $admin->getSlots();
        else respond_error('Endpoint not found', 404);
        break;

    case 'reports':
        if ($method !== 'GET') respond_error('Method not allowed', 405);
        if ($sub === 'wait-time') $report->waitTime();
        elseif ($sub === 'service-demand') $report->serviceDemand();
        else respond_error('Endpoint not found', 404);
        break;

    default:
        respond_error('Endpoint not found', 404);
}