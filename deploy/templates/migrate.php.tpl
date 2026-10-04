<?php
// Script de mise à jour de la base CN95 — généré par deploy/deploy.sh.
// Usage unique : protégé par un token, il se supprime après exécution.

const TOKEN = '__TOKEN__';
const WITH_SEED = __WITH_SEED__;
const EXPECTED = '__MIGRATIONS__'; // migrations attendues (information)

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');

$given = (string) ($_GET['token'] ?? '');
if (!hash_equals(TOKEN, $given)) {
    http_response_code(403);
    exit('Accès refusé.');
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Dotenv\Dotenv;

chdir($root);
(new Dotenv())->bootEnv($root . '/.env');
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] = 'prod';
$_SERVER['APP_DEBUG'] = $_ENV['APP_DEBUG'] = '0';

require_once $root . '/src/Kernel.php';
$kernel = new App\Kernel('prod', false);
$app = new Application($kernel);
$app->setAutoExit(false);

$steps = [
    ['cache:clear', '--no-warmup' => true],
    ['doctrine:migrations:migrate', '--no-interaction' => true, '--allow-no-migration' => true],
];
if (WITH_SEED) {
    $steps[] = ['app:seed'];
}
$steps[] = ['cache:warm-up'];

$ok = true;
echo '<!doctype html><meta charset="utf-8"><title>Migration CN95</title><body style="font-family:monospace">';
echo '<h1>Migration CN95</h1><p>Migrations attendues : ' . htmlspecialchars(EXPECTED) . '</p>';
foreach ($steps as $step) {
    $name = $step[0];
    $input = new ArrayInput(['command' => $name] + array_slice($step, 1));
    $input->setInteractive(false);
    $out = new BufferedOutput();
    try {
        $code = $app->run($input, $out);
    } catch (Throwable $e) {
        $code = 1;
        $out->writeln($e->getMessage());
    }
    $ok = $ok && $code === 0;
    echo '<h2>' . htmlspecialchars($name) . ' — ' . ($code === 0 ? 'OK' : 'ÉCHEC') . '</h2><pre>'
        . htmlspecialchars($out->fetch()) . '</pre>';
    if ($code !== 0) {
        break;
    }
}

if ($ok) {
    @unlink(__FILE__);
    echo '<p><strong>Terminé. Ce script a été supprimé.</strong></p>';
} else {
    echo '<p><strong>Échec : le script est conservé pour une nouvelle tentative '
        . '(supprimez-le manuellement une fois terminé).</strong></p>';
}
