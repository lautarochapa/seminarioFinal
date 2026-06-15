param(
    [string] $Email = "admin@cccontrol.test",
    [string] $Password = "password123",
    [string] $Username = "admin",
    [string] $Name = "Admin",
    [string] $Lastname = "Test",
    [string] $Role = "3"
)

$ErrorActionPreference = "Stop"

$root = Split-Path -Parent $PSScriptRoot
$php = Join-Path $root ".runtime\php8229\php.exe"

if (-not (Test-Path $php)) {
    $php = "php"
}

$code = @"
require 'vendor/autoload.php';
`$app = require 'bootstrap/app.php';
`$kernel = `$app->make(Illuminate\Contracts\Console\Kernel::class);
`$kernel->bootstrap();
Illuminate\Support\Facades\DB::table('users')->updateOrInsert(
    ['email' => '$Email'],
    [
        'name' => '$Name',
        'lastname' => '$Lastname',
        'username' => '$Username',
        'password' => Illuminate\Support\Facades\Hash::make('$Password'),
        'nivel_acceso' => '$Role',
        'created_at' => now(),
        'updated_at' => now(),
    ]
);
`$userId = Illuminate\Support\Facades\DB::table('users')->where('email', '$Email')->value('id');
`$roleId = Illuminate\Support\Facades\DB::table('roles')->where('code', 'super_admin')->value('id');
if (`$userId && `$roleId) {
    Illuminate\Support\Facades\DB::table('user_roles')->updateOrInsert(
        ['user_id' => `$userId, 'role_id' => `$roleId],
        ['created_at' => now()]
    );
}
echo 'Usuario listo: $Email'.PHP_EOL;
"@

Push-Location $root
try {
    & $php -d error_reporting=8191 -d display_errors=0 -r $code
} finally {
    Pop-Location
}
