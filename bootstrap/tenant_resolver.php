<?php

use Illuminate\Support\Str;

const CACHE_TENANTS_PHP = __DIR__ . DS . 'cache/tenants.php';

function resolveTenantDatabase()
{

    if (php_sapi_name() === 'cli') {
        return config('database.connections.pgsql.database', env('DB_DATABASE', 'dbportal'));
    }

    $host   = str_replace('-', '', $_SERVER['HTTP_HOST'] ?? '');
    $tenant =  Str::replaceFirst('.' . config('app.default_host'), '', $host);

    $tenants = getTenants();
    if (in_array($tenant, $tenants)) {
        return $tenant;
    }

    return config('database.connections.pgsql.database', env('DB_DATABASE', 'dbportal'));
}

function getTenants()
{
    if (file_exists(CACHE_TENANTS_PHP)) {
        return require_once CACHE_TENANTS_PHP;
    }

    $tenantsLocation = __DIR__ . '/../tenants';
    if (!file_exists($tenantsLocation)) {
        return [];
    }

    $databaseNames =  file($tenantsLocation, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $databaseNames = array_values($databaseNames);

    createDataBasesCacheFile($databaseNames);

    return $databaseNames;


}

function createDataBasesCacheFile(array $dataBases) {

    $fileContent = '<?php' . PHP_EOL;
    $fileContent .= PHP_EOL;
    $fileContent .= 'return [' . PHP_EOL;

    foreach ($dataBases as $name) {
        $fileContent .= "    '$name'," . PHP_EOL;
    }

    $fileContent .= '];' . PHP_EOL;

    file_put_contents(CACHE_TENANTS_PHP, $fileContent);
}
