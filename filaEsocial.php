<?php
error_reporting(0);

require_once("libs/db_stdlib.php");
require_once("libs/db_utils.php");
require_once("libs/db_app.utils.php");
require_once("libs/db_libdicionario.php");
require_once("dbforms/db_funcoes.php");
//require_once("dbforms/db_classesgenericas.php");
/*require_once("std/label/rotulo.php");
require_once("std/label/RotuloDB.php");*/
require_once("bootstrap.php");

//require_once("libs/db_autoload.php");

require_once("model/esocial/FilaESocialTask.model.php");
// require_once("model/configuracao/DBLogXML.model.php");
// require_once("model/configuracao/TaskManager.model.php");
// require_once("src/RecursosHumanos/ESocial/Integracao/Recurso.php");
// require_once("src/RecursosHumanos/ESocial/Model/Formulario/Tipo.php");
// require_once("src/RecursosHumanos/ESocial/Integracao/ESocial.php");
require_once("classes/db_esocialcertificado_classe.php");
// require_once("src/V3/Extension/Registry.php");
// require_once("src/Core/Config.php");
use \ECidade\V3\Extension\Registry as Registry;
use \ECidade\Core\Config as AppConfig;

// Trilha de Auditoria Universal (Principio 5 da Constituicao)
@db_query("SELECT fc_startsession();");

$fila = new FilaESocialTask();

Registry::set('app.config', new AppConfig());
Registry::get('app.config')->merge(array(

    'charset' => 'UTF-8',

    'php.display_errors' => true,

    'php.error_reporting' => E_ALL & ~E_DEPRECATED & ~E_STRICT,

    'app.api' => array(
        'centraldeajuda' => 'http://centraldeajuda.dbseller.com.br/help/api/index.php/',
        'esocial' => array(
            'url' => 'http://ecidade_sidecar_gov/sped-esocial/',
            'login' => '', // login do cliente
            'password' => '' // senha do cliente
        )
    ),

    'app.proxy' => array(),

    'app.request.session.attachOn' => '*.php',

    'app.request.session.readOnlyOn' => '{skins/*,*.js,*.css}',

    'app.request.asset.cacheable.extension' => array('js', 'css', 'jpg', 'jpeg', 'png', 'bmp', 'ttf', 'gif'),

    'app.error.log' => true,

    'app.error.log.mask' => "{type} - {message} in {file} on line {line}\n{trace}",

    'app.error.log.mask.trace' => "#{index} {file}:{line} - {class}{type}{function}({args})\n",

    'app.events' => array('app.error' => '\ECidade\V3\Error\EventHandler'),

    'app.log.verbosity' => \ECidade\V3\Extension\Logger::ERROR,

    'db.client_encoding' => 'LATIN1',

));

switch ($argv[1]) {
    case 'consultar':
        $fila->consultar();
        break;

    default:
        $fila->iniciar();
        break;
}
